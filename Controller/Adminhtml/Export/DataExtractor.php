<?php

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\Export;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class DataExtractor extends \Magento\Backend\App\Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
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