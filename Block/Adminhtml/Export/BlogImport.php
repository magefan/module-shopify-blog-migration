<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Block\Adminhtml\Export;

use Magefan\ShopifyBlogExport\Model\BlogImport\Source\SourcePool;
use Magefan\ShopifyBlogExport\Model\BlogImport\State;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * "Shopify default blog" destination of the export form: everything the page script needs.
 */
class BlogImport extends Template
{
    /**
     * Destination value in the export form.
     */
    const DESTINATION = 'shopify_blog';

    /**
     * @var State
     */
    private $state;

    /**
     * @var SourcePool
     */
    private $sourcePool;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param Context $context
     * @param State $state
     * @param SourcePool $sourcePool
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        State $state,
        SourcePool $sourcePool,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->state = $state;
        $this->sourcePool = $sourcePool;
        $this->json = $json;
    }

    /**
     * Page script configuration.
     *
     * @return string JSON
     */
    public function getConfigJson(): string
    {
        $connection = $this->state->getConnection();

        $urls = [];
        foreach (['connect', 'start', 'send', 'finish', 'status', 'cancel'] as $action) {
            $urls[$action] = $this->getUrl('shopifyblogexport/blogimport/' . $action);
        }

        return $this->json->serialize([
            'urls' => $urls,
            'formKey' => $this->getFormKey(),
            'destination' => self::DESTINATION,
            'source' => $this->getSourceCode(),
            'totals' => $this->getTotals(),
            'shop' => $connection ? $connection['shop'] : '',
            'job' => $this->state->getJob(),
            'i18n' => $this->getTranslations(),
        ]);
    }

    /**
     * Blog extension selected on the export index page.
     *
     * @return string
     */
    private function getSourceCode(): string
    {
        return (string)$this->getRequest()->getParam('type');
    }

    /**
     * Categories and posts of the selected blog extension, null when it is not installed.
     *
     * @return array|null ['blogs' => int, 'posts' => int]
     */
    private function getTotals()
    {
        try {
            return $this->sourcePool->get($this->getSourceCode())->getTotals();
        } catch (LocalizedException $e) {
            return null;
        }
    }

    /**
     * Texts used by the page script.
     *
     * @return array
     */
    private function getTranslations(): array
    {
        return array_map('strval', [
            'keyHint' => __('Paste the connection key from the Blog Import app in your Shopify admin. Disabled posts are exported as hidden posts. Categories become separate blogs in Shopify.'),
            'summary' => __('%1 posts and %2 categories will be exported.', '%1', '%2'),
            'nothing' => __('There are no posts to export.'),
            'notInstalled' => __('The selected blog extension was not found in this Magento installation.'),
            'connecting' => __('Connecting…'),
            'starting' => __('Starting…'),
            'exportingTo' => __('Exporting to %1', '%1'),
            'sendingBlogs' => __('Sending categories'),
            'sendingPosts' => __('Sending posts'),
            'waiting' => __('Queued — the import usually starts within a minute or two…'),
            'importing' => __('Importing into Shopify'),
            'done' => __('Import finished'),
            'doneSummary' => __('%1 posts imported, %2 failed.', '%1', '%2'),
            'cancelled' => __('Import cancelled.'),
            'failedJob' => __('The import could not be completed.'),
            'canClose' => __('All posts are sent. You can close this page — the import continues in Shopify. Come back here or open the Blog Import app in Shopify to check progress.'),
            'retrying' => __('Connection problem, retrying…'),
            'interrupted' => __('The export was interrupted before all posts were sent.'),
            'conflict' => __('Another import to this store is still running.'),
            'confirmCancel' => __('Cancel this import? Posts already imported stay in Shopify.'),
            'moreFailed' => __('See the full list in the Blog Import app in Shopify.'),
            'genericError' => __('Something went wrong. Please try again.'),
        ]);
    }
}
