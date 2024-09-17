<?php

namespace Magefan\ShopifyBlogExport\Model\Export;

class Magefan extends \Magefan\ShopifyBlogExport\Model\Export\AbstractExport
{
    public function getCategories(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('magefan_blog_category')])
            ->order('path ASC')
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        return $this->mvColumns($result, ['category_id' => 'old_id', 'mf_exclude_xml_sitemap' => 'exclude_xml_sitemap']);
    }

    public function getCategoryIds(): array
    {
        return $this->getEntityIds('magefan_blog_category', 'category_id');
    }

    public function getTags(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('magefan_blog_tag')])
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        return $this->mvColumns($result, ['tag_id' => 'old_id', 'mf_exclude_xml_sitemap' => 'exclude_xml_sitemap']);
    }

    public function getTagIds(): array
    {
        return $this->getEntityIds('magefan_blog_tag', 'tag_id');
    }

    public function getPosts(int $offset): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName('magefan_blog_post')])
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
                    ['ce' => $this->resourceConnection->getTableName('magefan_blog_post_category')],
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
                    ['ce' => $this->resourceConnection->getTableName('magefan_blog_post_tag')],
                    ['tag_id'])
                ->where('post_id = ?', $data['post_id']);

            $t_result = $connection->fetchAll($t_sql);

            foreach ($t_result as $t_data) {
                $postTags[] = $t_data['tag_id'];
            }

            $result[$key]['tags'] = $postTags;
            $result[$key]['content'] = $this->filterProvider->getPageFilter()->filter((string)$result[$key]['content']);
            $result[$key]['short_content'] = $this->filterProvider->getPageFilter()->filter((string)$result[$key]['short_content']);
        }


        return $this->mvColumns($result, ['post_id' => 'old_id', 'mf_exclude_xml_sitemap' => 'exclude_xml_sitemap']);
    }

    public function getPostIds(): array
    {
        return $this->getEntityIds('magefan_blog_post', 'post_id');
    }

    public function getAuthorIds(): array
    {
        if ($this->getConnection()->isTableExists($this->resourceConnection->getTableName('magefan_blog_author'))) {
            return $this->getEntityIds('magefan_blog_author', 'author_id');
        } else {
            return $this->getEntityIds('admin_user', 'user_id');
        }
    }

    public function getAuthors(int $offset): array
    {
        if ($this->getConnection()->isTableExists($this->resourceConnection->getTableName('magefan_blog_author'))) {
            $tableName = $this->resourceConnection->getTableName('magefan_blog_author');
        } else {
            $tableName = $this->resourceConnection->getTableName('admin_user');
        }

        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName($tableName)])
            ->limitPage($offset,self::ENTITIES_PER_PAGE);

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        return $this->mvColumns($result, ['author_id' => 'old_id', 'user_id' => 'old_id']);
    }

    public function getComments(int $offset): array
    {
        return [];
    }

    public function getCommentIds(): array
    {
        return [];
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
                ['ce' => $this->resourceConnection->getTableName('magefan_blog_post')],
                ['old_id' => 'post_id','featured_img'])
            ->where('featured_img IS NOT NULL');

        if (null !== $offset) {
            $select->limitPage($offset, self::ENTITIES_PER_PAGE);
        }

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            throw new \Exception(__('Magefan Blog Extension not detected.'), 1);
        }

        if (!empty($result)) {
            foreach ($result as $key => $item) {
                $elems = explode('/', $item['featured_img']);
                $featuredImg = end($elems);

                $mediaPath = $this->findFullMediaPaths->execute(['featured_img' => $featuredImg])[0] ?? 0;

                if ($mediaPath) {
                    $result[$key]['featured_img'] = $mediaPath;
                }
            }
        }

        return $result;
    }
}