<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Controller\Adminhtml\BlogImport;

use Exception;
use Magefan\ShopifyBlogExport\Model\BlogImport\ApiException;
use Magefan\ShopifyBlogExport\Model\BlogImport\Exporter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * One AJAX step of the export page.
 *
 * Success returns the step's data with HTTP 200. Errors return {message, retryable, job_id}:
 * 502 when sending the same request again may succeed, 400 otherwise.
 */
abstract class AbstractAction extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session.
     */
    const ADMIN_RESOURCE = 'Magefan_ShopifyBlogExport::export';

    /**
     * @var Exporter
     */
    protected $exporter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param Exporter $exporter
     * @param LoggerInterface $logger
     */
    public function __construct(Context $context, Exporter $exporter, LoggerInterface $logger)
    {
        parent::__construct($context);
        $this->exporter = $exporter;
        $this->logger = $logger;
    }

    /**
     * Run the step and return its JSON response.
     *
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            return $result->setData($this->handle());
        } catch (ApiException $e) {
            return $result->setHttpResponseCode($e->isRetryable() ? 502 : 400)->setData([
                'message' => $e->getMessage(),
                'retryable' => $e->isRetryable(),
                'job_id' => (int)($e->getData()['job_id'] ?? 0),
            ]);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData(['message' => $e->getMessage(), 'retryable' => false]);
        } catch (Exception $e) {
            $this->logger->critical($e);
            return $result->setHttpResponseCode(500)->setData(['message' => $e->getMessage(), 'retryable' => false]);
        }
    }

    /**
     * Do the step.
     *
     * @return array Response data
     * @throws Exception
     */
    abstract protected function handle(): array;
}
