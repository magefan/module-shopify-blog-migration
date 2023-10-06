<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 *
 * Glory to Ukraine! Glory to the heroes!
 */

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\Export;

/**
 * Run export controller
 */
class Run extends \Magento\Backend\App\Action
{
    /**
     * Run export
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        //set_time_limit(0);
        $data = $this->getRequest()->getPost();
        $type = '';
        try {
            if (empty($data['type'])) {
                throw new \Exception(__('Blog export type is not specified.'), 1);
            }
            $type = $data['type'];
            $this->_view->loadLayout();
            $this->_setActiveMenu('Magefan_ShopifyBlogExport::export');
            $this->_view->getPage()->getConfig()->getTitle()->prepend('Export from ' . ucfirst($type) . ' Blog to Shopify');
            $this->_addBreadcrumb($type, $type);
            $this->_view->renderLayout();
        }
        catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong: ').' '.$e->getMessage());
            $this->_getSession()->setData('export_' . $type . '_form_data', $data);
            if ($formPath = (string)$this->getRequest()->getParam('form')) {
                $this->_redirect(str_replace('_', '/', $formPath));
            } else {
                $this->_redirect('*/*/form', ['type' => $type]);
            }

        }
    }

    /**
     * Check is allowed access
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magefan_Blog::export');
    }
}
