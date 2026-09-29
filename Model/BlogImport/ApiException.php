<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Error returned by the Blog Import backend, or a failure to reach it.
 */
class ApiException extends LocalizedException
{
    /**
     * HTTP status of the backend response, 0 when no response was received.
     *
     * @var int
     */
    private $status;

    /**
     * Decoded response body.
     *
     * @var array
     */
    private $data;

    /**
     * @param Phrase $phrase Merchant-readable message
     * @param int $status HTTP status, 0 when the request did not complete
     * @param array $data Decoded response body
     */
    public function __construct(Phrase $phrase, int $status = 0, array $data = [])
    {
        parent::__construct($phrase);
        $this->status = $status;
        $this->data = $data;
    }

    /**
     * HTTP status of the backend response.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Decoded response body.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Whether sending the same request again may succeed: network failures, throttling and server errors.
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        return 0 === $this->status || 429 === $this->status || 500 <= $this->status;
    }
}
