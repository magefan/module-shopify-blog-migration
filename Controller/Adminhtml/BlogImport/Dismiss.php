<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

/**
 * Forget the ended import, so the export form is shown again.
 */
class Dismiss extends AbstractAction
{
    /**
     * @inheritDoc
     */
    protected function handle(): array
    {
        $this->exporter->dismiss();

        return [];
    }
}
