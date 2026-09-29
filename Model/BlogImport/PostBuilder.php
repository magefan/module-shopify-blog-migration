<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport;

use Exception;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Turns a source post row into an import API post item and the images it references.
 *
 * Must run inside frontend store emulation: CMS directives such as {{media url="…"}} are
 * expanded for the current store, and image URLs are matched against its media URL.
 */
class PostBuilder
{
    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param FilterProvider $filterProvider
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     */
    public function __construct(
        FilterProvider $filterProvider,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem
    ) {
        $this->filterProvider = $filterProvider;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
    }

    /**
     * Build the post item and the images it references.
     *
     * Images are returned separately, keyed by reference, so the caller can upload a whole batch
     * at once; attachImages() then puts the results back into the item.
     *
     * @param array $post Row from SourceInterface::getPosts()
     * @return array ['item' => array, 'images' => array, 'inline' => int[]]
     */
    public function build(array $post): array
    {
        $id = $post['id'];
        $images = [];
        $bodyHtml = $this->renderContent($post['content']);
        $inline = $this->extractImages($bodyHtml);
        foreach ($inline as $index => $image) {
            $images[$id . ':images:' . $index] = $image;
        }

        if ('' !== $post['image']) {
            $url = $this->getMediaUrl() . ltrim($post['image'], '/');
            $images[$id . ':image'] = [
                'url' => $url,
                'path' => $this->toLocalPath($url),
                'alt' => $post['image_alt'],
            ];
        }

        $summaryHtml = '' === trim(strip_tags($post['short_content'], '<img>'))
            ? ''
            : $this->renderContent($post['short_content']);

        return [
            'item' => [
                'type' => 'post',
                'id' => (string)$id,
                'data' => [
                    'title' => $this->getTitle($post['title'], $post['handle'], (string)__('Post #%1', $id)),
                    'handle' => $post['handle'],
                    'body_html' => $bodyHtml,
                    'summary_html' => $summaryHtml,
                    'author' => $post['author'],
                    'tags' => array_values(array_unique(array_filter(array_map('trim', $post['tags'])))),
                    'blogs' => $post['blogs'],
                    'published' => $post['is_active'],
                    'published_at' => $this->formatDate($post['published_at']),
                    'seo' => [
                        'title' => $post['meta_title'],
                        'description' => $post['meta_description'],
                    ],
                    'image' => null,
                    'images' => [],
                ],
            ],
            'images' => $images,
            'inline' => array_keys($inline),
        ];
    }

    /**
     * Title for an item; the handle or a placeholder when the source has none, since the import rejects untitled items.
     *
     * @param string $title
     * @param string $handle
     * @param string $placeholder
     * @return string
     */
    public function getTitle(string $title, string $handle, string $placeholder): string
    {
        $title = trim(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));
        if ('' !== $title) {
            return $title;
        }

        return '' !== trim($handle) ? trim($handle) : $placeholder;
    }

    /**
     * Put resolved images back into a post item.
     *
     * @param array $built Result of build()
     * @param array $resolved Result of ImageUploader::resolve() for the post's images
     * @return array Post item
     */
    public function attachImages(array $built, array $resolved): array
    {
        $item = $built['item'];
        $id = $item['id'];

        if (isset($resolved[$id . ':image'])) {
            $item['data']['image'] = $resolved[$id . ':image'];
        }

        foreach ($built['inline'] as $index) {
            $reference = $id . ':images:' . $index;
            if (isset($resolved[$reference])) {
                $item['data']['images'][] = ['src' => $built['images'][$reference]['src']] + $resolved[$reference];
            }
        }

        return $item;
    }

    /**
     * Content with CMS directives expanded, as visitors see it.
     *
     * srcset and sizes are removed from images: they point at resized copies on the old site that
     * the import does not carry over, and the browser would prefer them over the replaced src.
     *
     * @param string $html
     * @return string
     */
    private function renderContent(string $html): string
    {
        try {
            $html = $this->filterProvider->getPageFilter()->filter($html);
        } catch (Exception $e) {
            // A directive that cannot be expanded leaves the raw content, which is still better than no post.
        }

        return (string)preg_replace('#\s(?:srcset|sizes)=("[^"]*"|\'[^\']*\')#i', '', $html);
    }

    /**
     * Images used in rendered content.
     *
     * @param string $html
     * @return array Keyed by index: src (exact attribute value), url (absolute), path (local file or ''), alt
     */
    private function extractImages(string $html): array
    {
        if (!preg_match_all('#<img\b[^>]*>#i', $html, $tags)) {
            return [];
        }

        $images = [];
        $seen = [];
        foreach ($tags[0] as $tag) {
            if (!preg_match('#\ssrc=("([^"]*)"|\'([^\']*)\')#i', $tag, $srcMatch)) {
                continue;
            }

            $src = '' !== ($srcMatch[2] ?? '') ? $srcMatch[2] : ($srcMatch[3] ?? '');
            if ('' === $src || 0 === strpos($src, 'data:') || isset($seen[$src])) {
                continue;
            }
            $seen[$src] = true;

            $alt = '';
            if (preg_match('#\salt=("([^"]*)"|\'([^\']*)\')#i', $tag, $altMatch)) {
                $alt = html_entity_decode(
                    '' !== ($altMatch[2] ?? '') ? $altMatch[2] : ($altMatch[3] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );
            }

            $url = $this->toAbsoluteUrl(html_entity_decode($src, ENT_QUOTES, 'UTF-8'));
            $images[] = [
                'src' => $src,
                'url' => $url,
                'path' => $this->toLocalPath($url),
                'alt' => $alt,
            ];
        }

        return $images;
    }

    /**
     * Absolute form of a URL found in content.
     *
     * @param string $url URL as written in the HTML
     * @return string
     */
    private function toAbsoluteUrl(string $url): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);

        if (0 === strpos($url, '//')) {
            return (string)parse_url($baseUrl, PHP_URL_SCHEME) . ':' . $url;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return $url;
        }
        if (0 === strpos($url, '/')) {
            $host = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);
            $port = parse_url($baseUrl, PHP_URL_PORT);

            return $host . ($port ? ':' . $port : '') . $url;
        }

        return $baseUrl . $url;
    }

    /**
     * Local file behind a media URL, empty string for anything outside the media directory.
     *
     * Compared without the scheme, so http/https mismatches between content and settings still match.
     * Both the configured media URL and the "/pub/media/" form are recognised, since content saved
     * before a docroot change often uses the other one.
     *
     * @param string $url Absolute URL
     * @return string
     */
    private function toLocalPath(string $url): string
    {
        $bare = preg_replace('#^https?:#i', '', strtok($url, '?#'));
        $mediaUrl = preg_replace('#^https?:#i', '', $this->getMediaUrl());
        $bases = [$mediaUrl, str_replace('/pub/media/', '/media/', $mediaUrl), str_replace('/media/', '/pub/media/', $mediaUrl)];

        foreach (array_unique($bases) as $base) {
            if (0 !== strpos($bare, $base)) {
                continue;
            }

            $relative = rawurldecode(substr($bare, strlen($base)));
            if ('' === $relative || false !== strpos($relative, '..')) {
                return '';
            }

            return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath($relative);
        }

        return '';
    }

    /**
     * Media URL of the current store, ending with a slash.
     *
     * @return string
     */
    private function getMediaUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * ISO 8601 UTC date from a database datetime, which Magento stores in UTC.
     *
     * @param string $date
     * @return string Empty when there is no date
     */
    private function formatDate(string $date): string
    {
        if ('' === $date || 0 === strpos($date, '0000-00-00')) {
            return '';
        }

        $timestamp = strtotime($date . ' UTC');

        return false === $timestamp ? '' : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
