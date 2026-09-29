<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport;

use Exception;
use InvalidArgumentException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Math\Random;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Talks to the Blog Import backend with the merchant's connection key.
 *
 * The key is "mfbi_" + base64url of {"v":1,"url":"…/sfapp/api/","token":"…"}: the endpoint comes
 * from the key, so the extension never hardcodes where the backend lives.
 */
class ApiClient
{
    /**
     * Connection key prefix.
     */
    const KEY_PREFIX = 'mfbi_';

    /**
     * Seconds to wait for the backend, and for one file upload.
     */
    const TIMEOUT = 60;

    /**
     * @var CurlFactory
     */
    private $curlFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Random
     */
    private $random;

    /**
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param Random $random
     */
    public function __construct(CurlFactory $curlFactory, Json $json, Random $random)
    {
        $this->curlFactory = $curlFactory;
        $this->json = $json;
        $this->random = $random;
    }

    /**
     * Decode a connection key pasted by the merchant.
     *
     * @param string $key
     * @return array|null ['url' => string, 'token' => string], null when the key is malformed
     */
    public function parseKey(string $key)
    {
        $key = trim($key);
        if (0 !== strpos($key, self::KEY_PREFIX)) {
            return null;
        }

        $decoded = base64_decode(strtr(substr($key, strlen(self::KEY_PREFIX)), '-_', '+/'), true);
        try {
            $data = false === $decoded ? null : $this->json->unserialize($decoded);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        if (!is_array($data) || 1 !== (int)($data['v'] ?? 0) || empty($data['token'])) {
            return null;
        }

        $url = (string)($data['url'] ?? '');
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        return [
            'url' => rtrim($url, '/') . '/',
            'token' => (string)$data['token'],
        ];
    }

    /**
     * Call a backend action.
     *
     * @param array $connection ['url' => string, 'token' => string]
     * @param string $action Action name, e.g. "importstart"
     * @param array $body Request body
     * @return array Decoded response
     * @throws ApiException
     */
    public function request(array $connection, string $action, array $body = []): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Blog-Import-Token' => $connection['token'],
        ]);

        try {
            $curl->post($connection['url'] . $action, $this->json->serialize((object)$body));
        } catch (Exception $e) {
            throw new ApiException(__('Could not reach the Blog Import app: %1', $e->getMessage()));
        }

        $status = (int)$curl->getStatus();
        try {
            $data = $this->json->unserialize((string)$curl->getBody());
        } catch (InvalidArgumentException $e) {
            $data = null;
        }

        if (!is_array($data)) {
            throw new ApiException(
                __('Unexpected response from the Blog Import app (HTTP %1). Please try again later.', $status),
                $status ?: 502
            );
        }

        if (200 > $status || 300 <= $status) {
            $message = isset($data['error'])
                ? new Phrase((string)$data['error'])
                : __('The Blog Import app returned an error.');
            throw new ApiException($message, $status, $data);
        }

        return $data;
    }

    /**
     * Upload a file to a staged upload target as multipart/form-data: the target's parameters first, the file last.
     *
     * The body is built by hand because Shopify's storage requires the file to be the last field.
     *
     * @param array $target url, parameters
     * @param string $path Local file path
     * @param string $mimeType
     * @return bool Whether the upload succeeded
     */
    public function upload(array $target, string $path, string $mimeType): bool
    {
        $contents = file_get_contents($path);
        if (false === $contents) {
            return false;
        }

        $boundary = $this->random->getRandomString(24);
        $body = '';
        foreach ((array)($target['parameters'] ?? []) as $parameter) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $parameter['name'] . "\"\r\n\r\n";
            $body .= $parameter['value'] . "\r\n";
        }
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . str_replace('"', '', basename($path)) . "\"\r\n";
        $body .= 'Content-Type: ' . $mimeType . "\r\n\r\n";
        $body .= $contents . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->addHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary);

        try {
            $curl->post((string)$target['url'], $body);
        } catch (Exception $e) {
            return false;
        }

        $status = (int)$curl->getStatus();

        return 200 <= $status && 300 > $status;
    }
}
