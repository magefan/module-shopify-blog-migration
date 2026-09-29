<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

/**
 * Open an import job for the selected blog extension.
 */
class Start extends AbstractAction
{
    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        return $this->exporter->start((string)$this->getRequest()->getPostValue('source'));
    }
}
