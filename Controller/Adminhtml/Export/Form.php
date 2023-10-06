<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 *
 * Glory to Ukraine! Glory to the heroes!
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\Export;

use Magento\Framework\Exception\LocalizedException;

/**
 * Shopify Blog prepare export controller
 */
class Form extends \Magento\Backend\App\Action
{
    /**
     * Prepare export
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            //ShopifyBlogExport

            $type = (string)$this->getRequest()->getParam('type');
            if (!$type) {
                throw new LocalizedException(__('Shopify Blog Export type is not specified.'));
            }

            $_type = ucfirst($type);

            $this->_view->loadLayout();
            $this->_setActiveMenu('Magefan_ShopifyBlogExport::export');
            $title = __('Shopify Blog Export from %1 Blog', $_type);
            $this->_view->getPage()->getConfig()->getTitle()->prepend($title);
            $this->_addBreadcrumb($title, $title);

            $config = new \Magento\Framework\DataObject(
                (array)$this->_getSession()->getData('export_' . $type . '_form_data', true) ?: []
            );

            $this->_objectManager->get(\Magento\Framework\Registry::class)->register('export_config', $config);

            $this->_view->renderLayout();

        } catch (LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e);
            $this->_redirect('*/*/index');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong: ').' '.$e->getMessage());
            $this->_redirect('*/*/index');
        }
    }

    /**
     * Check is allowed access
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magefan_ShopifyBlogExport::export');
    }
}
