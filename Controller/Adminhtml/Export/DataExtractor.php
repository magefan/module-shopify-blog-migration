<?php

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\Export;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Area;

class DataExtractor extends \Magento\Backend\App\Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Emulation $emulation,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $type = '';

        try {
            if (empty($data['type']) || empty($data['entity'])) {
                throw new \Exception(__('Blog export type is not specified.'), 1);
            }

            $_type = ucfirst($data['type']);
            $export = $this->_objectManager->create('\Magefan\ShopifyBlogExport\Model\Export\\'.$_type);
            $preparedData = [];

            $this->emulation->startEnvironmentEmulation(
                $this->storeManager->getStore()->getId(),
                Area::AREA_FRONTEND,
                true
            );

            switch ($data['entity']) {
                case 'category':
                    if (isset($data['allIds'])) {
                        $preparedData = $export->getCategoryIds();
                    }
                    else {
                        $preparedData = $export->getCategories((int)$data['offset']);
                    }
                    break;
                case 'tag':
                    if (isset($data['allIds'])) {
                        $preparedData = $export->getTagIds();
                    }
                    else {
                        $preparedData = $export->getTags((int)$data['offset']);
                    }
                    break;
                case 'post':
                    if (isset($data['allIds'])) {
                        $preparedData = $export->getPostIds();
                    }
                    else {
                        $preparedData = $export->getPosts((int)$data['offset']);
                    }
                    break;
                case 'comment':
                    if (isset($data['allIds'])) {
                        $preparedData = $export->getCommentIds();
                    }
                    else {
                        $preparedData = $export->getComments((int)$data['offset']);
                    }
                    break;
                case 'author':
                    if (method_exists($export,'getAuthorIds') && method_exists($export,'getAuthors')) {
                        if (isset($data['allIds'])) {
                            $preparedData = $export->getAuthorIds();
                        }
                        else {
                            $preparedData = $export->getAuthors((int)$data['offset']);
                        }
                    }
                    break;
                case 'media_post':
                    if (isset($data['allIds'])) {
                        $preparedData = $export->getPostMediaPathsNumber();
                    }
                    else {
                        $preparedData = $export->getPostMediaPaths((int)$data['offset']);
                    }
                    break;
            }

            $this->emulation->stopEnvironmentEmulation();

            $resultJson = $this->resultJsonFactory->create();
            return $resultJson->setData($preparedData);
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong: ') . ' ' . $e->getMessage());
            $this->_getSession()->setData('export_' . $type . '_form_data', $data);
            if ($formPath = (string)$this->getRequest()->getParam('form')) {
                $this->_redirect(str_replace('_', '/', $formPath));
            } else {
                $this->_redirect('*/*/form', ['type' => $type]);
            }

        }
    }
}