<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 *
 * Glory to Ukraine! Glory to the heroes!
 */

namespace Magefan\ShopifyBlogExport\Model\Export;

/**
 * Mirasvit export model
 */
class Mirasvit extends \Magefan\ShopifyBlogExport\Model\Export\AbstractExport
{
    public function getCategories(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
             ->from(
                 ['ce' => $this->resourceConnection->getTableName('mst_blog_category_entity')],
                 ['old_id' => 'entity_id', 'position', 'path' => 'parent_id'])->limitPage($offset,self::ENTITIES_PER_PAGE);
        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Mirasvit Blog Extension not detected.'), 1);
        }

        $answer = [];
        foreach ($result as $data) {
            /* Prepare category data */

            /* Get Stores */
            $data['store_ids'] = [0];

            $map = [
                //mirasvit_blog ->  magefan_blog
                'name' => 'title',
                'meta_title' => 'meta_title',
                'meta_keywords' => 'meta_keywords',
                'meta_description' => 'meta_description',
                'url_key' => 'identifier',
                'content' => 'content',
                'status' => 'is_active',
            ];

            foreach ($map as $msField => $mfField) {
                $data[$mfField] = $this->getAttributValue('blog_category', $data['old_id'], $msField);
            }

            $answer[] = $data;
        }

        return $answer;
    }

    public function getCategoryIds(): array {
        return $this->getEntityIds('mst_blog_category_entity', 'entity_id');
    }

    public function getTags(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mst_blog_tag')],
                ['old_id' => 'tag_id', 'title' => 'name', 'identifier' => 'url_key'])->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Mirasvit Blog Extension not detected.'), 1);
        }

        $answer = [];
        foreach ($result as $data) {
            if (!$data['title']) {
                continue;
            }

            $data['title'] = trim($data['title']);
            $data['is_active'] = '1';
            $answer[] = $data;
        }

        return $answer;
    }

    public function getTagIds(): array
    {
        return $this->getEntityIds('mst_blog_tag', 'tag_id');
    }

    public function getPosts(int $offset): array {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mst_blog_post_entity')],
                ['old_id' => 'entity_id', 'creation_time' => 'created_at', 'publish_time' => 'created_at', 'update_time' => 'updated_at'])
            ->where('ce.type = ?', 'post')
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Mirasvit Blog Extension not detected.'), 1);
        }

        $answer = [];
        foreach ($result as $data) {
            $map = [
                // mirasvit ->  blog magefan_blog
                //'featured_show_on_home' => '',
                //'featured_show_on_home' => '',
                'meta_description' => 'meta_description',
                'meta_keywords' => 'meta_keywords',
                'meta_title' => 'meta_title',
                'featured_image' => 'featured_img',
                'short_content' => 'short_content',
                'content' => 'content',
                'status' => 'is_active',
                'url_key' => 'identifier',
                'name' => 'title',
                //'is_pinned' => '',
            ];

            foreach ($map as $msField => $mfField) {
                $data[$mfField] = $this->getAttributValue('blog_post', $data['old_id'], $msField);
            }

            if ($data['is_active'] == 2) {
                $data['is_active'] = 1;
            } else {
                $data['is_active'] = 0;
            }

//            if ($data['featured_img']) {
//                $data['featured_img'] = 'magefan_blog/' . $data['featured_img'];
//            }

            /* Find post categories*/
            $postCategories = [];
            $c_sql = $connection->select()
                ->from(
                    ['ce' => $this->resourceConnection->getTableName('mst_blog_category_post')],
                    ['category_id'])
                ->where('post_id = ?', $data['old_id']);
            $c_result = $connection->fetchAll($c_sql);
            foreach ($c_result as $c_data) {
                $postCategories[] = $c_data['category_id'];
            }
            $data['categories'] = $postCategories;

            /* Find post tags*/
            $postTags = [];
            $t_sql = $connection->select()
                ->from(
                    ['ce' => $this->resourceConnection->getTableName('mst_blog_tag_post')],
                    ['tag_id'])
                ->where('post_id = ?', $data['old_id']);
            $t_result = $connection->fetchAll($t_sql);

            foreach ($t_result as $t_data) {
                $postTags[] = $t_data['tag_id'];
            }

            $data['tags'] = $postTags;

            $answer[] = $data;
        }

        return $answer;
    }

    public function getPostIds(): array {
        return $this->getEntityIds('mst_blog_post_entity', 'entity_id', 'ce.type = \'post\'');
    }

    public function getPostMediaPaths(int $offset): array {
        return $this->getPostMediaPathsWithOffset($offset);
    }

    public function getComments(int $offset): array
    {
        return [];
    }

    public function getCommentIds(): array
    {
        return [];
    }

    public function getPostMediaPathsNumber(): array {
        return $this->getPostMediaPathsWithOffset();
    }

    public function getPostMediaPathsWithOffset(int $offset = null): array {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mst_blog_post_entity')],
                ['old_id' => 'entity_id'])
            ->where('ce.type = ?', 'post');

        if (null !== $offset) {
            $select->
            limitPage($offset, self::ENTITIES_PER_PAGE);
        }

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Mirasvit Blog Extension not detected.'), 1);
        }

        $answer = [];
        foreach ($result as $data) {
            $map = [
                'featured_image' => 'featured_img',
            ];

            foreach ($map as $msField => $mfField) {
                $data[$mfField] = $this->getAttributValue('blog_post', $data['old_id'], $msField);
            }

            $data['old_id'];
            $answer[] = $data;
        }

        if (!empty($answer)) {
            foreach ($answer as $key => $item) {
                $answer[$key]['featured_img'] = $this->findFullMediaPaths->execute(['featured_img' => $item['featured_img']])[0];
            }
        }

        return $answer;
    }

    protected function getAttributValue($entitytTypeCode, $entitytId, $attributeCode)
    {
        $connection = $this->getConnection();

        if (!isset($this->entityTypeId[$entitytTypeCode])) {
            $select = $connection->select()
                ->from(
                    ['ce' => $this->resourceConnection->getTableName('eav_entity_type')],
                    ['entity_type_id'])->where('entity_type_code =?',$entitytTypeCode);

            $result = $connection->fetchAll($select);

            if (count($result)) {
                foreach ($result as $data) {
                    $this->entityTypeId[$entitytTypeCode] = $data['entity_type_id'];
                    break;
                }
            }
            else {
                $this->entityTypeId[$entitytTypeCode] = false;
            }
        }

        $entityTypeId = $this->entityTypeId[$entitytTypeCode];

        if (!$entityTypeId) {
            return null;
        }

        if (!isset($this->entityTypeAttributes[$entitytTypeCode])) {
            $this->entityTypeAttributes[$entitytTypeCode] = [];

            $select = $connection->select()
                ->from(
                    ['ce' => $this->resourceConnection->getTableName('eav_attribute')])
                    ->where('entity_type_id =?',$entityTypeId);
            $result = $connection->fetchAll($select);

            foreach ($result as $data) {
                $this->entityTypeAttributes[$entitytTypeCode][$data['attribute_code']] = $data;
            }
        }

        if (empty($this->entityTypeAttributes[$entitytTypeCode][$attributeCode])) {
            return null;
        }

        $attribute = $this->entityTypeAttributes[$entitytTypeCode][$attributeCode];

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mst_' . $entitytTypeCode . '_entity_' . $attribute['backend_type'])],
                ['value'])
                ->where('store_id =?',0)
                ->where('attribute_id =?', $attribute['attribute_id'])
                ->where('entity_id =?', $entitytId);

        $result = $connection->fetchAll($select);

        if (count($result)) {
            foreach ($result as $data) {
                return $data['value'];
            }
        }
        return null;
    }
}
