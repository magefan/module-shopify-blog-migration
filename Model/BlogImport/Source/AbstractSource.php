<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Zend_Db_Expr;

/**
 * Database helpers shared by the blog extension sources.
 *
 * Sources read the extensions' tables directly instead of their models, so the export works with
 * any version of the extension and does not depend on its classes being enabled.
 */
abstract class AbstractSource implements SourceInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * Whether a table exists, per table name.
     *
     * @var bool[]
     */
    private $tables = [];

    /**
     * Column names per table name.
     *
     * @var array
     */
    private $columns = [];

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     */
    public function getTotals(): array
    {
        $posts = $this->getPostSelect()
            ->reset(Select::COLUMNS)
            ->columns(['count' => new Zend_Db_Expr('COUNT(*)')]);
        $blogs = $this->getCategorySelect()
            ->reset(Select::COLUMNS)
            ->columns(['count' => new Zend_Db_Expr('COUNT(*)')]);

        return [
            'blogs' => (int)$this->getConnection()->fetchOne($blogs),
            'posts' => (int)$this->getConnection()->fetchOne($posts),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getPostIds(int $offset, int $limit): array
    {
        $select = $this->getPostSelect()
            ->reset(Select::COLUMNS)
            ->columns(['id' => $this->getPostIdColumn()])
            ->order($this->getPostIdColumn() . ' ASC')
            ->limit($limit, $offset);

        return array_map('intval', $this->getConnection()->fetchCol($select));
    }

    /**
     * Select of the exported posts, aliased "p".
     *
     * @return Select
     */
    abstract protected function getPostSelect(): Select;

    /**
     * Qualified post id column of getPostSelect(), e.g. "p.post_id".
     *
     * @return string
     */
    abstract protected function getPostIdColumn(): string;

    /**
     * Select of the exported categories, aliased "c".
     *
     * @return Select
     */
    abstract protected function getCategorySelect(): Select;

    /**
     * Database connection.
     *
     * @return AdapterInterface
     */
    protected function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    /**
     * Table name with the installation's prefix.
     *
     * @param string $name
     * @return string
     */
    protected function getTable(string $name): string
    {
        return $this->resourceConnection->getTableName($name);
    }

    /**
     * Whether a table exists.
     *
     * @param string $name Table name without prefix
     * @return bool
     */
    protected function tableExists(string $name): bool
    {
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = $this->getConnection()->isTableExists($this->getTable($name));
        }

        return $this->tables[$name];
    }

    /**
     * Whether a column exists; false when the table does not.
     *
     * @param string $table Table name without prefix
     * @param string $column
     * @return bool
     */
    protected function columnExists(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = $this->tableExists($table)
                ? array_keys($this->getConnection()->describeTable($this->getTable($table)))
                : [];
        }

        return in_array($column, $this->columns[$table], true);
    }

    /**
     * Values of a relation table grouped by post id, in the table's own order.
     *
     * @param string $table Table name without prefix
     * @param string $postColumn
     * @param string $valueColumn
     * @param int[] $postIds
     * @return array [post id => string[]]
     */
    protected function fetchGrouped(string $table, string $postColumn, string $valueColumn, array $postIds): array
    {
        if (!$postIds || !$this->tableExists($table)) {
            return [];
        }

        $select = $this->getConnection()->select()
            ->from($this->getTable($table), ['post_id' => $postColumn, 'value' => $valueColumn])
            ->where($postColumn . ' IN (?)', $postIds);

        return $this->groupByPost($select);
    }

    /**
     * Rows of a select with "post_id" and "value" columns, grouped by post id in select order.
     *
     * @param Select $select
     * @return array [post id => string[]]
     */
    protected function groupByPost(Select $select): array
    {
        $result = [];
        foreach ($this->getConnection()->fetchAll($select) as $row) {
            $result[(int)$row['post_id']][] = (string)$row['value'];
        }

        return $result;
    }

    /**
     * Admin user names by id.
     *
     * @param int[] $userIds
     * @return array [user id => name]
     */
    protected function getAdminNames(array $userIds): array
    {
        $userIds = array_filter(array_unique($userIds));
        if (!$userIds) {
            return [];
        }

        $select = $this->getConnection()->select()
            ->from($this->getTable('admin_user'), [
                'user_id',
                'name' => new Zend_Db_Expr("TRIM(CONCAT(IFNULL(firstname, ''), ' ', IFNULL(lastname, '')))"),
            ])
            ->where('user_id IN (?)', $userIds);

        return $this->getConnection()->fetchPairs($select);
    }

    /**
     * Plain-text SEO value.
     *
     * @param mixed $value
     * @return string
     */
    protected function cleanText($value): string
    {
        return trim(strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
    }
}
