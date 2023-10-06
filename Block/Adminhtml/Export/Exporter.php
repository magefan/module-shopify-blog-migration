<?php

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Block\Adminhtml\Export;

class Exporter extends \Magento\Backend\Block\Template
{
    /**
     * @return string
     */
    public function getType(): string {
        return (string)$this->getRequest()->getParam('type');
    }

    /**
     * @return string
     */
    public function getShopifyDomain(): string {
        return (string)$this->getRequest()->getParam('shopify_domain');
    }

    /**
     * @return string
     */
    public function getImportKey(): string {
        return (string)$this->getRequest()->getParam('shopify_import_key');
    }

    /**
     * @return string
     */
    public function getExporterKey(): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < 20; $i++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $string;
    }
}