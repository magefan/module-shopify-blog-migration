<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport\Source;

use Magento\Framework\DB\Select;

/**
 * Mirasvit Blog (mst_blog_* EAV tables).
 */
class Mirasvit extends AbstractSource
{
    /**
     * Media folder of post images, relative to the media directory.
     */
    const IMAGE_FOLDER = 'blog/';

    /**
     * Value of the "status" attribute of a published post.
     */
    const STATUS_PUBLISHED = 2;

    /**
     * Attributes per entity type code: [attribute code => [attribute_id, backend_type]].
     *
     * @var array
     */
    private $attributes = [];

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string)__('Mirasvit Blog');
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->tableExists('mst_blog_post_entity') && $this->tableExists('mst_blog_category_entity');
    }

    /**
     * @inheritDoc
     */
    public function getBlogItems(int $offset, int $limit): array
    {
        $select = $this->getCategorySelect()
            ->order('c.entity_id ASC')
            ->limit($limit, $offset);
        $ids = array_map('intval', $this->getConnection()->fetchCol($select));
        $values = $this->getAttributeValues('blog_category', $ids, ['name', 'url_key', 'content']);

        $items = [];
        foreach ($ids as $id) {
            $items[] = [
                'type' => 'blog',
                'id' => (string)$id,
                'data' => [
                    'title' => (string)($values[$id]['name'] ?? ''),
                    'handle' => (string)($values[$id]['url_key'] ?? ''),
                    'description_html' => (string)($values[$id]['content'] ?? ''),
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

        $columns = ['entity_id', 'created_at'];
        if ($this->columnExists('mst_blog_post_entity', 'author_id')) {
            $columns[] = 'author_id';
        }

        $select = $this->getConnection()->select()
            ->from($this->getTable('mst_blog_post_entity'), $columns)
            ->where('entity_id IN (?)', $postIds);
        $rows = $this->getConnection()->fetchAll($select);

        $values = $this->getAttributeValues('blog_post', $postIds, [
            'name', 'url_key', 'content', 'short_content', 'status', 'meta_title', 'meta_description', 'featured_image',
        ]);

        $categories = $this->getCategorySelect()
            ->reset(Select::COLUMNS)
            ->join(['pc' => $this->getTable('mst_blog_category_post')], 'pc.category_id = c.entity_id', [
                'post_id',
                'value' => 'category_id',
            ])
            ->where('pc.post_id IN (?)', $postIds);
        $categories = $this->groupByPost($categories);

        $tags = [];
        if ($this->tableExists('mst_blog_tag_post') && $this->tableExists('mst_blog_tag')) {
            $tags = $this->getConnection()->select()
                ->from(['pt' => $this->getTable('mst_blog_tag_post')], ['post_id'])
                ->join(['t' => $this->getTable('mst_blog_tag')], 't.tag_id = pt.tag_id', ['value' => 'name'])
                ->where('pt.post_id IN (?)', $postIds);
            $tags = $this->groupByPost($tags);
        }

        $authors = $this->getAdminNames(array_map('intval', array_column($rows, 'author_id')));

        $posts = [];
        foreach ($rows as $row) {
            $id = (int)$row['entity_id'];
            $value = $values[$id] ?? [];
            $image = trim((string)($value['featured_image'] ?? ''), '/');
            $posts[$id] = [
                'id' => $id,
                'title' => (string)($value['name'] ?? ''),
                'handle' => (string)($value['url_key'] ?? ''),
                'content' => (string)($value['content'] ?? ''),
                'short_content' => (string)($value['short_content'] ?? ''),
                'author' => (string)($authors[$row['author_id'] ?? 0] ?? ''),
                'tags' => $tags[$id] ?? [],
                'blogs' => $categories[$id] ?? [],
                'is_active' => self::STATUS_PUBLISHED === (int)($value['status'] ?? 0),
                'published_at' => (string)$row['created_at'],
                'meta_title' => $this->cleanText($value['meta_title'] ?? ''),
                'meta_description' => $this->cleanText($value['meta_description'] ?? ''),
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
        $select = $this->getConnection()->select()
            ->from(['p' => $this->getTable('mst_blog_post_entity')], ['entity_id']);

        if ($this->columnExists('mst_blog_post_entity', 'type')) {
            $select->where('p.type = ?', 'post');
        }

        return $select;
    }

    /**
     * @inheritDoc
     */
    protected function getPostIdColumn(): string
    {
        return 'p.entity_id';
    }

    /**
     * Categories without Mirasvit's hidden root category.
     *
     * @inheritDoc
     */
    protected function getCategorySelect(): Select
    {
        $select = $this->getConnection()->select()
            ->from(['c' => $this->getTable('mst_blog_category_entity')], ['entity_id']);

        if ($this->columnExists('mst_blog_category_entity', 'level')) {
            $select->where('c.level > 0');
        }

        return $select;
    }

    /**
     * Default-scope attribute values of several entities, loaded with one query per backend type.
     *
     * @param string $entityTypeCode "blog_post" or "blog_category"
     * @param int[] $entityIds
     * @param string[] $codes Attribute codes
     * @return array [entity id => [attribute code => value]]
     */
    private function getAttributeValues(string $entityTypeCode, array $entityIds, array $codes): array
    {
        $attributes = array_intersect_key($this->getAttributes($entityTypeCode), array_flip($codes));
        if (!$entityIds || !$attributes) {
            return [];
        }

        $byType = [];
        foreach ($attributes as $code => $attribute) {
            $byType[$attribute['backend_type']][$attribute['attribute_id']] = $code;
        }

        $result = [];
        foreach ($byType as $backendType => $attributeCodes) {
            $table = 'mst_' . $entityTypeCode . '_entity_' . $backendType;
            if (!$this->tableExists($table)) {
                continue;
            }

            $select = $this->getConnection()->select()
                ->from($this->getTable($table), ['entity_id', 'attribute_id', 'value'])
                ->where('store_id = ?', 0)
                ->where('attribute_id IN (?)', array_keys($attributeCodes))
                ->where('entity_id IN (?)', $entityIds);

            foreach ($this->getConnection()->fetchAll($select) as $row) {
                $result[(int)$row['entity_id']][$attributeCodes[$row['attribute_id']]] = $row['value'];
            }
        }

        return $result;
    }

    /**
     * EAV attributes of an entity type, loaded once.
     *
     * @param string $entityTypeCode
     * @return array [attribute code => ['attribute_id' => int, 'backend_type' => string]]
     */
    private function getAttributes(string $entityTypeCode): array
    {
        if (!isset($this->attributes[$entityTypeCode])) {
            $select = $this->getConnection()->select()
                ->from(['a' => $this->getTable('eav_attribute')], ['attribute_code', 'attribute_id', 'backend_type'])
                ->join(['t' => $this->getTable('eav_entity_type')], 't.entity_type_id = a.entity_type_id', [])
                ->where('t.entity_type_code = ?', $entityTypeCode);

            $this->attributes[$entityTypeCode] = [];
            foreach ($this->getConnection()->fetchAll($select) as $row) {
                $this->attributes[$entityTypeCode][$row['attribute_code']] = $row;
            }
        }

        return $this->attributes[$entityTypeCode];
    }
}
