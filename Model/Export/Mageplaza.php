<?php

namespace Magefan\ShopifyBlogExport\Model\Export;

class Mageplaza extends \Magefan\ShopifyBlogExport\Model\Export\AbstractExport
{
    public function getCategories(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_category')],
            [
                'old_id' => 'category_id',
                'title' => 'name',
                'identifier' => 'url_key',
                'position' => 'position',
                'meta_title' => 'meta_title',
                'meta_keywords' => 'meta_keywords',
                'meta_description' => 'meta_description',
                'content' => 'description',
                'path' => 'parent_id',
                'is_active' => 'enabled',
            ])
            ->order('path ASC')
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Mageplaza Blog Extension not detected.'), 1);
        }

        return $this->mvColumns($result, ['category_id' => 'old_id', 'mf_exclude_xml_sitemap' => 'exclude_xml_sitemap']);
    }

    public function getCategoryIds(): array
    {
        return $this->getEntityIds('mageplaza_blog_category', 'category_id');
    }

    public function getTags(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_tag')],
                [
                   'old_id' => 'tag_id',
                   'title' => 'name',
                   'identifier' => 'url_key',
                   'content' => 'description',
                   'meta_title' => 'meta_title',
                   'meta_description' => 'meta_description',
                   'meta_keywords' => 'meta_keywords',
                   'is_active' => 'enabled'
                ])
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        return $result;
    }

    public function getTagIds(): array
    {
        return $this->getEntityIds('mageplaza_blog_tag', 'tag_id');
    }

    public function getPosts(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_post')])
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        foreach ($result as $key=>$data) {
            /* Find post categories*/
            $postCategories = [];
            $c_sql = $connection->select()
                ->from(
                    ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_post_category')],
                    ['category_id'])
                ->where('post_id = ?', $data['post_id']);
            $c_result = $connection->fetchAll($c_sql);
            foreach ($c_result as $c_data) {
                $postCategories[] = $c_data['category_id'];
            }
            $result[$key]['categories'] = $postCategories;

            /* Find post tags*/
            $postTags = [];
            $t_sql = $connection->select()
                ->from(
                    ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_post_tag')],
                    ['tag_id'])
                ->where('post_id = ?', $data['post_id']);

            $t_result = $connection->fetchAll($t_sql);

            foreach ($t_result as $t_data) {
                $postTags[] = $t_data['tag_id'];
            }

            $result[$key]['tags'] = $postTags;
        }

        return $this->mvColumns($result,
            [
                'post_id' => 'old_id',
                'enabled' => 'is_active',
                'name' => 'title',
                'url_key' => 'identifier',
                'post_content' => 'content',
                'short_description' => 'short_content',
                'created_at' => 'creation_time',
                'updated_at' => 'update_time',
                'publish_date' => 'publish_time',
                'image' => 'featured_img'
            ]);
    }

    public function getPostIds(): array
    {
        return $this->getEntityIds('mageplaza_blog_post', 'post_id');
    }

    public function getComments(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_comment')])
            ->order('reply_id ASC')
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        foreach ($result as $key => $commentData) {
            $result[$key]['status'] = ($commentData['status'] == 3) ? 0 : $commentData['status'];
            $result[$key]['parent_id'] = 0;
            $result[$key]['author_type'] = 0;
        }

        return $this->mvColumns($result,
            [
                'comment_id' => 'old_id',
                'user_name' => 'author_nickname',
                'user_email' => 'author_email',
                'url_key' => 'identifier',
                'content' => 'text'
            ]);
    }

    public function getCommentIds(): array
    {
        return $this->getEntityIds('mageplaza_blog_comment', 'comment_id');
    }

    public function getPostMediaPaths(int $offset): array
    {
        return $this->getPostMediaPathsWithOffset($offset);
    }

    public function getPostMediaPathsNumber(): array
    {
        return $this->getPostMediaPathsWithOffset();
    }

    public function getPostMediaPathsWithOffset(int $offset = null): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('mageplaza_blog_post')],
                ['old_id' => 'post_id','featured_img' => 'image'])
            ->where('image IS NOT NULL');

        if (null !== $offset) {
            $select->limitPage($offset, self::ENTITIES_PER_PAGE);
        }

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Mageplaza Blog Extension not detected.'), 1);
        }

        if (!empty($result)) {
            foreach ($result as $key => $item) {
                $featuredImg = explode('/', (string)$item['featured_img']);
                $featuredImg = array_pop($featuredImg);
                $result[$key]['featured_img'] = $this->findFullMediaPaths->execute(['featured_img' => $featuredImg])[0];
            }
        }

        return $result;
    }
}