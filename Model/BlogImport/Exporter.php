<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport;

use Magefan\ShopifyBlogExport\Model\BlogImport\Source\SourceInterface;
use Magefan\ShopifyBlogExport\Model\BlogImport\Source\SourcePool;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Steps of an export, each one short enough for a single admin AJAX request.
 *
 * The browser drives the export (start, send until everything is sent, finish, then status
 * polling), so no PHP request runs long enough to hit a time limit. Progress is saved after each
 * step: a reloaded page continues where it stopped, and a batch retried after an error is sent
 * again rather than skipped (the backend stores items by id, so a repeat is harmless).
 */
class Exporter
{
    /**
     * Source name sent to the backend.
     */
    const SOURCE = 'magento';

    /**
     * Import API schema version.
     */
    const SCHEMA = 1;

    /**
     * Categories sent per request.
     */
    const BLOGS_PER_REQUEST = 50;

    /**
     * Most posts sent per request.
     */
    const POSTS_PER_REQUEST = 10;

    /**
     * Most images collected into one request before it is sent.
     */
    const IMAGES_PER_REQUEST = 200;

    /**
     * Seconds of work after which a posts request stops taking more posts.
     */
    const TIME_BUDGET = 20;

    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var State
     */
    private $state;

    /**
     * @var SourcePool
     */
    private $sourcePool;

    /**
     * @var PostBuilder
     */
    private $postBuilder;

    /**
     * @var ImageUploader
     */
    private $imageUploader;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PackageInfo
     */
    private $packageInfo;

    /**
     * @param ApiClient $apiClient
     * @param State $state
     * @param SourcePool $sourcePool
     * @param PostBuilder $postBuilder
     * @param ImageUploader $imageUploader
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     * @param PackageInfo $packageInfo
     */
    public function __construct(
        ApiClient $apiClient,
        State $state,
        SourcePool $sourcePool,
        PostBuilder $postBuilder,
        ImageUploader $imageUploader,
        Emulation $emulation,
        StoreManagerInterface $storeManager,
        PackageInfo $packageInfo
    ) {
        $this->apiClient = $apiClient;
        $this->state = $state;
        $this->sourcePool = $sourcePool;
        $this->postBuilder = $postBuilder;
        $this->imageUploader = $imageUploader;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->packageInfo = $packageInfo;
    }

    /**
     * Save a connection key after the backend confirms it.
     *
     * @param string $key
     * @return string Connected shop domain
     * @throws LocalizedException
     */
    public function connect(string $key): string
    {
        $connection = $this->apiClient->parseKey($key);
        if (null === $connection) {
            throw new LocalizedException(
                __('This is not a valid connection key. Copy it from the Blog Import app in your Shopify admin.')
            );
        }

        $response = $this->apiClient->request($connection, 'importconnect');
        $shop = (string)($response['shop'] ?? '');
        $this->state->saveConnection($connection['url'], $connection['token'], $shop);
        $this->state->deleteJob();

        return $shop;
    }

    /**
     * Open an import job on the backend.
     *
     * @param string $sourceCode
     * @return array Export state
     * @throws LocalizedException
     */
    public function start(string $sourceCode): array
    {
        $connection = $this->getConnection();
        $source = $this->sourcePool->get($sourceCode);
        $totals = $source->getTotals();

        $response = $this->apiClient->request($connection, 'importstart', [
            'source' => self::SOURCE,
            'schema' => self::SCHEMA,
            'site_url' => $this->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB),
            'client_version' => (string)$this->packageInfo->getVersion('Magefan_ShopifyBlogExport'),
            'totals' => $totals,
        ]);

        $job = [
            'job_id' => (int)$response['job_id'],
            'source' => $sourceCode,
            'blogs_total' => $totals['blogs'],
            'posts_total' => $totals['posts'],
            'blogs_sent' => 0,
            'posts_sent' => 0,
            'finished' => false,
        ];
        $this->state->saveJob($job);

        return $job;
    }

    /**
     * Send the next batch: categories until all are sent, then posts.
     *
     * @return array Updated export state
     * @throws LocalizedException
     */
    public function send(): array
    {
        $connection = $this->getConnection();
        $job = $this->getJob();
        if ($job['finished']) {
            throw new LocalizedException(__('There is no export in progress.'));
        }

        $source = $this->sourcePool->get($job['source']);
        if ($job['blogs_sent'] < $job['blogs_total']) {
            $job = $this->sendBlogs($connection, $source, $job);
        } else {
            $this->emulation->startEnvironmentEmulation((int)$this->getStore()->getId(), Area::AREA_FRONTEND, true);
            try {
                $job = $this->sendPosts($connection, $source, $job);
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        }

        $this->state->saveJob($job);

        return $job;
    }

    /**
     * Tell the backend every item is sent.
     *
     * @return string Job status reported by the backend
     * @throws LocalizedException
     */
    public function finish(): string
    {
        $connection = $this->getConnection();
        $job = $this->getJob();

        $response = $this->apiClient->request($connection, 'importfinish', ['job_id' => $job['job_id']]);
        $job['finished'] = true;
        $this->state->saveJob($job);

        return (string)($response['status'] ?? '');
    }

    /**
     * Progress of the current import as reported by the backend.
     *
     * @return array
     * @throws LocalizedException
     */
    public function status(): array
    {
        return $this->apiClient->request($this->getConnection(), 'importstatus', ['job_id' => $this->getJob()['job_id']]);
    }

    /**
     * Cancel an import: the given one, or the current one when no id is given.
     *
     * The saved export is forgotten even when the backend cannot be reached (for example after the
     * key was reset in the app), so the export form is never locked; the import can then still be
     * cancelled in the Blog Import app.
     *
     * @param int $jobId
     * @return bool Whether the backend confirmed the cancellation
     * @throws LocalizedException When a conflicting import cannot be cancelled
     */
    public function cancel(int $jobId): bool
    {
        $job = $this->state->getJob();
        if (!$jobId && $job) {
            $jobId = (int)$job['job_id'];
        }

        $isOwnJob = $job && (int)$job['job_id'] === $jobId;
        $error = null;

        if ($jobId) {
            try {
                $this->apiClient->request($this->getConnection(), 'importcancel', ['job_id' => $jobId]);
            } catch (ApiException $e) {
                if (404 !== $e->getStatus()) {
                    $error = $e;
                }
            } catch (LocalizedException $e) {
                $error = $e;
            }
        }

        if ($error && !$isOwnJob) {
            throw $error;
        }

        if ($isOwnJob) {
            $this->state->deleteJob();
        }

        return null === $error;
    }

    /**
     * Forget the saved export once its import has ended, so the export form is shown again.
     *
     * The import itself is left untouched in Shopify.
     *
     * @return void
     */
    public function dismiss()
    {
        $job = $this->state->getJob();
        if ($job && !empty($job['finished'])) {
            $this->state->deleteJob();
        }
    }

    /**
     * Send one batch of categories.
     *
     * @param array $connection
     * @param SourceInterface $source
     * @param array $job
     * @return array Updated state
     * @throws ApiException
     */
    private function sendBlogs(array $connection, SourceInterface $source, array $job): array
    {
        $items = $source->getBlogItems($job['blogs_sent'], self::BLOGS_PER_REQUEST);
        foreach ($items as &$item) {
            $item['data']['title'] = $this->postBuilder->getTitle(
                $item['data']['title'],
                $item['data']['handle'],
                (string)__('Category #%1', $item['id'])
            );
        }
        unset($item);

        if ($items) {
            $this->apiClient->request($connection, 'importitems', ['job_id' => $job['job_id'], 'items' => $items]);
        }

        $job['blogs_sent'] = $items
            ? min($job['blogs_sent'] + self::BLOGS_PER_REQUEST, $job['blogs_total'])
            : $job['blogs_total'];

        return $job;
    }

    /**
     * Send one batch of posts with their images.
     *
     * Takes posts until the batch holds POSTS_PER_REQUEST posts, IMAGES_PER_REQUEST images, or
     * TIME_BUDGET seconds of work — always at least one post, so a slow post still moves forward.
     *
     * @param array $connection
     * @param SourceInterface $source
     * @param array $job
     * @return array Updated state
     * @throws ApiException
     */
    private function sendPosts(array $connection, SourceInterface $source, array $job): array
    {
        $started = microtime(true);
        $postIds = $source->getPostIds($job['posts_sent'], self::POSTS_PER_REQUEST);
        if (!$postIds) {
            $job['posts_total'] = $job['posts_sent'];
            return $job;
        }

        $posts = $source->getPosts($postIds);
        $items = [];
        $taken = 0;
        $imageCount = 0;

        foreach ($postIds as $postId) {
            if ($taken && (self::TIME_BUDGET < microtime(true) - $started || self::IMAGES_PER_REQUEST <= $imageCount)) {
                break;
            }

            ++$taken;
            if (!isset($posts[$postId])) {
                continue;
            }

            $built = $this->postBuilder->build($posts[$postId]);
            $resolved = $this->imageUploader->resolve($connection, $job['job_id'], $built['images']);
            $items[] = $this->postBuilder->attachImages($built, $resolved);
            $imageCount += count($built['images']);
        }

        if ($items) {
            $this->apiClient->request($connection, 'importitems', ['job_id' => $job['job_id'], 'items' => $items]);
        }

        $job['posts_sent'] += $taken;
        if (count($postIds) < self::POSTS_PER_REQUEST && count($postIds) === $taken) {
            $job['posts_total'] = $job['posts_sent'];
        }
        $job['posts_total'] = max($job['posts_total'], $job['posts_sent']);

        return $job;
    }

    /**
     * Saved connection.
     *
     * @return array
     * @throws LocalizedException When the extension is not connected
     */
    private function getConnection(): array
    {
        $connection = $this->state->getConnection();
        if (null === $connection) {
            throw new LocalizedException(__('Connect your Shopify store first.'));
        }

        return $connection;
    }

    /**
     * Saved export state.
     *
     * @return array
     * @throws LocalizedException When there is no export
     */
    private function getJob(): array
    {
        $job = $this->state->getJob();
        if (null === $job) {
            throw new LocalizedException(__('There is no export in progress.'));
        }

        return $job;
    }

    /**
     * Store view whose URLs and content filters are used: the default store view.
     *
     * @return Store
     */
    private function getStore()
    {
        return $this->storeManager->getDefaultStoreView();
    }
}
