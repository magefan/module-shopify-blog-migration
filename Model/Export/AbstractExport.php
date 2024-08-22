<?php

namespace Magefan\ShopifyBlogExport\Model\Export;

use Magento\Framework\App\ResourceConnection;
use Magefan\ShopifyBlogExport\Model\FindFullMediaPaths;
use Magento\Cms\Model\Template\FilterProvider;

abstract class AbstractExport implements \Magefan\ShopifyBlogExport\Api\DataExtractor
{
    const ENTITIES_PER_PAGE = 1;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connection;

    protected $entityTypeId = [];

    protected $entityTypeAttributes = [];

    protected $findFullMediaPaths;

    protected $filterProvider;

    /**
     * @param ResourceConnection $resourceConnection
     * @param FindFullMediaPaths $findFullMediaPaths
     * @param FilterProvider $filterProvider
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        FindFullMediaPaths $findFullMediaPaths,
        FilterProvider $filterProvider
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->findFullMediaPaths = $findFullMediaPaths;
        $this->filterProvider = $filterProvider;
    }

    abstract function getCategories(int $offset): array;
    abstract function getCategoryIds(): array;
    abstract function getTags(int $offset): array;
    abstract function getTagIds(): array;
    abstract function getPosts(int $offset): array;
    abstract function getPostIds(): array;
    abstract function getComments(int $offset): array;
    abstract function getCommentIds(): array;
    abstract function getPostMediaPaths(int $offset): array;
    abstract function getPostMediaPathsNumber(): array;
    abstract function getPostMediaPathsWithOffset(int $offset = null): array;

    public function getEntityIds(string $tableName, string $columnName, string $cond = ''): array
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                ['ce' => $this->resourceConnection->getTableName($tableName)],
                ['old_id' => $columnName]);

        if ('' !== $cond) {
            $select->where($cond);
        }

        try {
            $result = $connection->fetchAll($select);
        } catch (\Exception $e) {
            var_dump($e->getMessage());exit;
            throw new \Exception(__('Mirasvit Blog Extension not detected.'), 1);
        }

        $answer = [];
        foreach ($result as $data) {
            $answer[] = $data['old_id'];
        }

        return $answer;
    }

    protected function getConnection() {
        if (!$this->connection) {
            $this->connection = $this->resourceConnection->getConnection();
        }

        return $this->connection;
    }

    protected function mvColumns(array $data, array $schema): array {
        foreach ($data as $key=>$item) {
            foreach ($schema as $from => $to) {
                if (isset($data[$key][$from])) {
                    $data[$key][$to] = $data[$key][$from];
                    unset($data[$key][$from]);
                }
            }
        }

        return $data;
    }
}