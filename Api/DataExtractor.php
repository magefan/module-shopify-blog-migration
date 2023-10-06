<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 *
 * Glory to Ukraine! Glory to the heroes!
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Api;

interface DataExtractor
{
    /**
     * @param int $offset
     * @return array
     */
    public function getCategories(int $offset): array;

    /**
     * @return array
     */
    public function getCategoryIds(): array;

    /**
     * @param int $offset
     * @return array
     */
    public function getTags(int $offset): array;

    /**
     * @return array
     */
    public function getTagIds(): array;

    /**
     * @param int $offset
     * @return array
     */
    public function getPosts(int $offset): array;

    /**
     * @return array
     */
    public function getPostIds(): array;

    /**
     * @param string $tableName
     * @param string $columnName
     * @return array
     */
    public function getEntityIds(string $tableName, string $columnName): array;

    /**
     * @param int $offset
     * @return array
     */
    public function getPostMediaPaths(int $offset): array;

    /**
     * @return array
     */
    public function getPostMediaPathsNumber(): array;

    /**
     * @param int|null $offset
     * @return array
     */
    public function getPostMediaPathsWithOffset(int $offset = null): array;

    /**
     * @param int $offset
     * @return array
     */
    public function getComments(int $offset): array;

    /**
     * @return array
     */
    public function getCommentIds(): array;
}