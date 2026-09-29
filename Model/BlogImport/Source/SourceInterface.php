<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport\Source;

/**
 * A Magento blog extension whose content can be exported.
 *
 * Sources only read their own tables and return plain rows; turning them into import API items
 * (content filters, images, dates) is shared and happens in PostBuilder.
 */
interface SourceInterface
{
    /**
     * Name shown to the merchant.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Whether the extension's tables exist in this installation.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Number of categories and posts that will be exported.
     *
     * @return array ['blogs' => int, 'posts' => int]
     */
    public function getTotals(): array;

    /**
     * Blog items (import API format) for a slice of the categories, in a stable order.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getBlogItems(int $offset, int $limit): array;

    /**
     * Ids of a slice of the exported posts, in a stable order.
     *
     * @param int $offset
     * @param int $limit
     * @return int[]
     */
    public function getPostIds(int $offset, int $limit): array;

    /**
     * Post rows keyed by post id.
     *
     * Each row: id, title, handle, content, short_content, author, tags (names), blogs (category ids),
     * is_active, published_at (UTC "Y-m-d H:i:s" or ''), meta_title, meta_description,
     * image (path relative to the media directory or ''), image_alt.
     *
     * @param int[] $postIds
     * @return array
     */
    public function getPosts(array $postIds): array;
}
