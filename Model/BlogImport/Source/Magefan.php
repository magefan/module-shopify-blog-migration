<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport\Source;

use DOMDocument;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Module\Manager as ModuleManager;
use Zend_Db_Expr;

/**
 * Magefan Blog (magefan_blog_* tables), including Blog Author authors.
 *
 * Exports all store views with default (not localized) values, like the legacy exporter. Mirrors
 * the storefront otherwise: a post goes into its active categories, the category with the highest
 * position first; only active tags and authors are used.
 */
class Magefan extends AbstractSource
{
    /**
     * Marker that separates the excerpt from the rest of the content.
     */
    const PAGE_BREAK = '<!-- pagebreak -->';

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ModuleManager $moduleManager
     */
    public function __construct(ResourceConnection $resourceConnection, ModuleManager $moduleManager)
    {
        parent::__construct($resourceConnection);
        $this->moduleManager = $moduleManager;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string)__('Magefan Blog');
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->tableExists('magefan_blog_post') && $this->tableExists('magefan_blog_category');
    }

    /**
     * @inheritDoc
     */
    public function getBlogItems(int $offset, int $limit): array
    {
        $select = $this->getCategorySelect()
            ->columns(['title', 'identifier', 'content'])
            ->order('c.category_id ASC')
            ->limit($limit, $offset);

        $items = [];
        foreach ($this->getConnection()->fetchAll($select) as $row) {
            $items[] = [
                'type' => 'blog',
                'id' => (string)$row['category_id'],
                'data' => [
                    'title' => (string)$row['title'],
                    'handle' => (string)$row['identifier'],
                    'description_html' => (string)$row['content'],
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

        $columns = [
            'post_id', 'author_id', 'title', 'identifier', 'content', 'short_content', 'is_active', 'publish_time',
            'meta_title', 'meta_description', 'featured_img',
        ];
        if ($this->columnExists('magefan_blog_post', 'featured_img_alt')) {
            $columns[] = 'featured_img_alt';
        }

        $select = $this->getConnection()->select()
            ->from($this->getTable('magefan_blog_post'), $columns)
            ->where('post_id IN (?)', $postIds);
        $rows = $this->getConnection()->fetchAll($select);

        $authors = $this->getAuthorNames(array_column($rows, 'author_id'));
        $categories = $this->getPostCategoryIds($postIds);
        $tags = $this->getPostTags($postIds);

        $posts = [];
        foreach ($rows as $row) {
            $id = (int)$row['post_id'];
            $content = str_replace('&lt;!-- pagebreak --&gt;', self::PAGE_BREAK, (string)$row['content']);
            $shortContent = (string)$row['short_content'];
            if ('' === trim($shortContent)) {
                $shortContent = $this->getExcerpt($content);
            }

            $posts[$id] = [
                'id' => $id,
                'title' => (string)$row['title'],
                'handle' => (string)$row['identifier'],
                'content' => str_replace(self::PAGE_BREAK, '', $content),
                'short_content' => $shortContent,
                'author' => (string)($authors[$row['author_id']] ?? ''),
                'tags' => $tags[$id] ?? [],
                'blogs' => $categories[$id] ?? [],
                'is_active' => 1 === (int)$row['is_active'],
                'published_at' => (string)$row['publish_time'],
                'meta_title' => $this->cleanText($row['meta_title']),
                'meta_description' => $this->cleanText($row['meta_description']),
                'image' => (string)$row['featured_img'],
                'image_alt' => (string)($row['featured_img_alt'] ?? ''),
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
            ->from(['p' => $this->getTable('magefan_blog_post')], ['post_id']);
    }

    /**
     * @inheritDoc
     */
    protected function getPostIdColumn(): string
    {
        return 'p.post_id';
    }

    /**
     * Active categories only.
     *
     * @inheritDoc
     */
    protected function getCategorySelect(): Select
    {
        return $this->getConnection()->select()
            ->from(['c' => $this->getTable('magefan_blog_category')], ['category_id'])
            ->where('c.is_active = ?', 1);
    }

    /**
     * Category ids per post, the category Magefan Blog treats as the parent (highest position) first.
     *
     * @param int[] $postIds
     * @return array [post id => string[]]
     */
    private function getPostCategoryIds(array $postIds): array
    {
        if (!$this->tableExists('magefan_blog_post_category')) {
            return [];
        }

        $select = $this->getCategorySelect()
            ->reset(Select::COLUMNS)
            ->join(['pc' => $this->getTable('magefan_blog_post_category')], 'pc.category_id = c.category_id', [
                'post_id',
                'value' => 'category_id',
            ])
            ->where('pc.post_id IN (?)', $postIds)
            ->order(['c.position DESC', 'c.category_id ASC']);

        return $this->groupByPost($select);
    }

    /**
     * Active tag titles per post.
     *
     * @param int[] $postIds
     * @return array [post id => string[]]
     */
    private function getPostTags(array $postIds): array
    {
        if (!$this->tableExists('magefan_blog_post_tag') || !$this->tableExists('magefan_blog_tag')) {
            return [];
        }

        $select = $this->getConnection()->select()
            ->from(['pt' => $this->getTable('magefan_blog_post_tag')], ['post_id'])
            ->join(['t' => $this->getTable('magefan_blog_tag')], 't.tag_id = pt.tag_id', ['value' => 'title'])
            ->where('pt.post_id IN (?)', $postIds)
            ->where('t.is_active = ?', 1);

        return $this->groupByPost($select);
    }

    /**
     * Names of active authors: Blog Author's own authors when that module is enabled, admin users otherwise.
     *
     * @param array $authorIds
     * @return array [author id => name]
     */
    private function getAuthorNames(array $authorIds): array
    {
        $authorIds = array_filter(array_unique(array_map('intval', $authorIds)));
        if (!$authorIds) {
            return [];
        }

        $useAuthors = $this->moduleManager->isEnabled('Magefan_BlogAuthor') && $this->tableExists('magefan_blog_author');
        $select = $this->getConnection()->select()
            ->from(
                $this->getTable($useAuthors ? 'magefan_blog_author' : 'admin_user'),
                [
                    'id' => $useAuthors ? 'author_id' : 'user_id',
                    'name' => new Zend_Db_Expr("TRIM(CONCAT(IFNULL(firstname, ''), ' ', IFNULL(lastname, '')))"),
                ]
            )
            ->where(($useAuthors ? 'author_id' : 'user_id') . ' IN (?)', $authorIds)
            ->where('is_active = ?', 1);

        return $this->getConnection()->fetchPairs($select);
    }

    /**
     * Content before the page break, with its HTML tags closed, as Magefan Blog shows it in post lists.
     *
     * @param string $content
     * @return string Empty when the content has no page break
     */
    private function getExcerpt(string $content): string
    {
        $position = mb_strpos($content, self::PAGE_BREAK);
        if (!$position) {
            return '';
        }

        $excerpt = mb_substr($content, 0, $position);
        $previousErrorState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'utf-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $excerpt . '</body>');
        libxml_use_internal_errors($previousErrorState);

        $body = $loaded ? $dom->getElementsByTagName('body')->item(0) : null;
        if (null === $body) {
            return $excerpt;
        }

        return (string)preg_replace('#^<body>|</body>$#', '', (string)$dom->saveHTML($body));
    }
}
