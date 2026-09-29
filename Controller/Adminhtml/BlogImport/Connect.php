<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

/**
 * Save a connection key after the backend confirms it.
 */
class Connect extends AbstractAction
{
    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        return ['shop' => $this->exporter->connect((string)$this->getRequest()->getPostValue('connection_key'))];
    }
}
