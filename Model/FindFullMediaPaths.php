<?php

namespace Magefan\ShopifyBlogExport\Model;

use Magento\Framework\App\Filesystem\DirectoryList;

class FindFullMediaPaths
{
    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @param DirectoryList $directoryList
     */
    public function __construct(
        DirectoryList $directoryList
    )
    {
        $this->directoryList = $directoryList;
    }

    public function execute(array $files, string $subDirectory = null): array {
        $it = new \RecursiveDirectoryIterator($this->directoryList->getPath('media') . ($subDirectory ?? ''));
        $pathes  = [];

        foreach (new \RecursiveIteratorIterator($it) as $file) {
            if (in_array($file->getFilename(), $files)) {
                $pathes[] = $file->getRealPath();
            }
        }

        return $pathes;
    }
}