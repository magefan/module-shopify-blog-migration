<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport\Source;

use Magento\Framework\DB\Select;

/**
 * Mageplaza Blog (mageplaza_blog_* tables).
 */
class Mageplaza extends AbstractSource
{
    /**
     * Media folder of post images, relative to the media directory.
     */
    const IMAGE_FOLDER = 'mageplaza/blog/post/';

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string)__('Mageplaza Blog');
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->tableExists('mageplaza_blog_post') && $this->tableExists('mageplaza_blog_category');
    }

    /**
     * @inheritDoc
     */
    public function getBlogItems(int $offset, int $limit): array
    {
        $select = $this->getCategorySelect()
            ->reset(Select::COLUMNS)
            ->columns(['category_id', 'name', 'url_key', 'description'])
            ->order('c.category_id ASC')
            ->limit($limit, $offset);

        $items = [];
        foreach ($this->getConnection()->fetchAll($select) as $row) {
            $items[] = [
                'type' => 'blog',
                'id' => (string)$row['category_id'],
                'data' => [
                    'title' => (string)$row['name'],
                    'handle' => (string)$row['url_key'],
                    'description_html' => (string)$row['description'],
                ],
            ];
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function getPosts(array $postIds): array
    {
        if (!$postIds) {
            return [];
        }

        $select = $this->getConnection()->select()
            ->from($this->getTable('mageplaza_blog_post'), [
                'post_id', 'name', 'url_key', 'post_content', 'short_description', 'enabled', 'publish_date',
                'created_at', 'meta_title', 'meta_description', 'image', 'author_id',
            ])
            ->where('post_id IN (?)', $postIds);
        $rows = $this->getConnection()->fetchAll($select);

        $categories = $this->getCategorySelect()
            ->reset(Select::COLUMNS)
            ->join(['pc' => $this->getTable('mageplaza_blog_post_category')], 'pc.category_id = c.category_id', [
                'post_id',
                'value' => 'category_id',
            ])
            ->where('pc.post_id IN (?)', $postIds);
        if ($this->columnExists('mageplaza_blog_post_category', 'position')) {
            $categories->order('pc.position ASC');
        }
        $categories = $this->groupByPost($categories);

        $tags = [];
        if ($this->tableExists('mageplaza_blog_post_tag') && $this->tableExists('mageplaza_blog_tag')) {
            $tags = $this->getConnection()->select()
                ->from(['pt' => $this->getTable('mageplaza_blog_post_tag')], ['post_id'])
                ->join(['t' => $this->getTable('mageplaza_blog_tag')], 't.tag_id = pt.tag_id', ['value' => 'name'])
                ->where('pt.post_id IN (?)', $postIds);
            $tags = $this->groupByPost($tags);
        }

        $authors = $this->getAuthorNames(array_column($rows, 'author_id'));

        $posts = [];
        foreach ($rows as $row) {
            $id = (int)$row['post_id'];
            $image = trim((string)$row['image'], '/');
            $posts[$id] = [
                'id' => $id,
                'title' => (string)$row['name'],
                'handle' => (string)$row['url_key'],
                'content' => (string)$row['post_content'],
                'short_content' => (string)$row['short_description'],
                'author' => (string)($authors[$row['author_id']] ?? ''),
                'tags' => $tags[$id] ?? [],
                'blogs' => $categories[$id] ?? [],
                'is_active' => 1 === (int)$row['enabled'],
                'published_at' => (string)($row['publish_date'] ?: $row['created_at']),
                'meta_title' => $this->cleanText($row['meta_title']),
                'meta_description' => $this->cleanText($row['meta_description']),
                'image' => '' === $image ? '' : self::IMAGE_FOLDER . $image,
                'image_alt' => '',
            ];
        }

        return $posts;
    }

    /**
     * @inheritDoc
     */
    protected function getPostSelect(): Select
    {
        return $this->getConnection()->select()
            ->from(['p' => $this->getTable('mageplaza_blog_post')], ['post_id']);
    }

    /**
     * @inheritDoc
     */
    protected function getPostIdColumn(): string
    {
        return 'p.post_id';
    }

    /**
     * Categories without Mageplaza's hidden root category.
     *
     * @inheritDoc
     */
    protected function getCategorySelect(): Select
    {
        $select = $this->getConnection()->select()
            ->from(['c' => $this->getTable('mageplaza_blog_category')], ['category_id']);

        if ($this->columnExists('mageplaza_blog_category', 'level')) {
            $select->where('c.level > 0');
        }

        return $select;
    }

    /**
     * Author names by id from Mageplaza's author table, admin users when it is missing.
     *
     * @param array $authorIds
     * @return array [author id => name]
     */
    private function getAuthorNames(array $authorIds): array
    {
        $authorIds = array_filter(array_unique(array_map('intval', $authorIds)));
        if (!$authorIds || !$this->columnExists('mageplaza_blog_author', 'name')) {
            return $this->getAdminNames($authorIds);
        }

        $select = $this->getConnection()->select()
            ->from($this->getTable('mageplaza_blog_author'), ['user_id', 'name'])
            ->where('user_id IN (?)', $authorIds);

        return $this->getConnection()->fetchPairs($select);
    }
}
