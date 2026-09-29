<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport;

use Magento\Framework\File\Mime;

/**
 * Gets post images to Shopify.
 *
 * Files that exist on this server are uploaded straight to Shopify's staged upload storage, so an
 * export works even when the store is not publicly reachable (localhost, staging, a domain already
 * pointed at Shopify). Image bytes never pass through the Blog Import backend. When a file is not
 * on disk, is too large, or its upload fails, the public URL is sent instead and Shopify tries to
 * download it.
 */
class ImageUploader
{
    /**
     * Largest image Shopify accepts, in bytes.
     */
    const MAX_FILE_SIZE = 20971520;

    /**
     * Most upload targets one backend request may ask for.
     */
    const TARGETS_PER_REQUEST = 50;

    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var Mime
     */
    private $mime;

    /**
     * @param ApiClient $apiClient
     * @param Mime $mime
     */
    public function __construct(ApiClient $apiClient, Mime $mime)
    {
        $this->apiClient = $apiClient;
        $this->mime = $mime;
    }

    /**
     * Turn image references into what the import API expects.
     *
     * A file used by several references is uploaded once.
     *
     * @param array $connection
     * @param int $jobId
     * @param array $images Keyed by caller reference: ['url' => absolute URL, 'path' => local file or '', 'alt' => string]
     * @return array Same keys: ['resource_url' => …] or ['url' => …], plus 'alt'. References that cannot be imported at all are omitted.
     * @throws ApiException When the backend cannot provide upload targets
     */
    public function resolve(array $connection, int $jobId, array $images): array
    {
        $paths = [];
        foreach ($images as $reference => $image) {
            $paths[$reference] = $this->getUploadablePath($image['path']);
        }

        $uploaded = [];
        foreach (array_chunk(array_unique(array_filter($paths)), self::TARGETS_PER_REQUEST) as $chunk) {
            $uploaded += $this->uploadFiles($connection, $jobId, $chunk);
        }

        $result = [];
        foreach ($images as $reference => $image) {
            $path = $paths[$reference];
            if (isset($uploaded[$path])) {
                $result[$reference] = ['resource_url' => $uploaded[$path], 'alt' => $image['alt']];
            } elseif (preg_match('#^https?://#i', $image['url'])) {
                $result[$reference] = ['url' => $image['url'], 'alt' => $image['alt']];
            }
        }

        return $result;
    }

    /**
     * Ask the backend for upload targets and upload each file to its target.
     *
     * @param array $connection
     * @param int $jobId
     * @param string[] $paths Local file paths
     * @return array [path => resource URL] for the files that were uploaded
     * @throws ApiException
     */
    private function uploadFiles(array $connection, int $jobId, array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $files[] = [
                'filename' => basename($path),
                'mime_type' => $this->mime->getMimeType($path),
                'file_size' => filesize($path),
            ];
        }

        $response = $this->apiClient->request($connection, 'importuploads', ['job_id' => $jobId, 'files' => $files]);

        $uploaded = [];
        foreach ($paths as $index => $path) {
            $target = $response['targets'][$index] ?? null;
            if (is_array($target) && $this->apiClient->upload($target, $path, $files[$index]['mime_type'])) {
                $uploaded[$path] = (string)$target['resource_url'];
            }
        }

        return $uploaded;
    }

    /**
     * Local path of an image file that can be uploaded, empty string when it cannot.
     *
     * @param string $path Candidate path
     * @return string
     */
    private function getUploadablePath(string $path): string
    {
        if ('' === $path || !is_file($path) || !is_readable($path)) {
            return '';
        }

        $size = filesize($path);
        if (!$size || self::MAX_FILE_SIZE < $size || 0 !== strpos($this->mime->getMimeType($path), 'image/')) {
            return '';
        }

        return $path;
    }
}
