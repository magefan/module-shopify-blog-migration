<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

/**
 * Send the next batch of categories or posts.
 */
class Send extends AbstractAction
{
    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        return $this->exporter->send();
    }
}
