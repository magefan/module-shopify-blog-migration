<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

/**
 * Tell the backend every item is sent.
 */
class Finish extends AbstractAction
{
    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        return ['status' => $this->exporter->finish()];
    }
}
