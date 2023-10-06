<?php

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\Export;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magefan\ShopifyBlogExport\Model\ShopifyPusher;
use Magefan\ShopifyBlogExport\Model\ShopifyMediaPusher;

class Pusher extends \Magento\Backend\App\Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var JsonSerializer
     */
    private $jsonSerializer;

    /**
     * @var ShopifyPusher
     */
    private $shopifyPusher;

    /**
     * @var ShopifyMediaPusher
     */
    private $shopifyMediaPusher;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param JsonSerializer $jsonSerializer
     * @param ShopifyPusher $shopifyPusher
     * @param ShopifyMediaPusher $shopifyMediaPusher
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        JsonSerializer $jsonSerializer,
        ShopifyPusher $shopifyPusher,
        ShopifyMediaPusher $shopifyMediaPusher
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->shopifyPusher = $shopifyPusher;
        $this->shopifyMediaPusher = $shopifyMediaPusher;
    }

    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $resultJson = $this->resultJsonFactory->create();
        if (empty($data['data']) || empty($data['shopifyUrl']) || empty($data['entity'])) {
            throw new \Exception(__('Data is not specified.'), 1);
        }

        try {
            $this->jsonSerializer->unserialize($data['data']);
        } catch (\InvalidArgumentException $e) {
            return $resultJson->setData(['errorMessage' => 'Invalid json format']);
        }

        $responseCode = 200;
        if ('media_post' === $data['entity']) {
            $status = $this->shopifyMediaPusher->execute((string)$data['shopifyUrl'], $data['data'], $data['entity']);
        }
        else {
            $status = $this->shopifyPusher->execute((string)$data['shopifyUrl'], $data['data'], $data['entity']);
        }

        if (strpos($status, 'errorMessage')) {
            $responseCode = 401;
        }

        return $resultJson->setData([$status])->setHttpResponseCode($responseCode);
    }
}