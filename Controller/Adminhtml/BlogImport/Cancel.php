<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

/**
 * Cancel the current import, or the conflicting one named in the request.
 */
class Cancel extends AbstractAction
{
    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        if ($this->exporter->cancel((int)$this->getRequest()->getPostValue('job_id'))) {
            return [];
        }

        return [
            'warning' => (string)__('The export was removed here, but the Blog Import app could not be reached to stop it. If the import is still running, cancel it in the Blog Import app in your Shopify admin.'),
        ];
    }
}
