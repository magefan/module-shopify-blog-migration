<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\ShopifyBlogExport\Model\BlogImport;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\FlagManager;

/**
 * Saved connection and export progress.
 *
 * Kept in the flag table rather than in the browser, so a reloaded page continues where it
 * stopped. The connection token is stored encrypted.
 */
class State
{
    /**
     * Flag holding the connection: url, token, shop.
     */
    const FLAG_CONNECTION = 'mfshopifyblogexport_blogimport_connection';

    /**
     * Flag holding the current export: job_id, source, totals, sent counters, finished flag.
     */
    const FLAG_JOB = 'mfshopifyblogexport_blogimport_job';

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param FlagManager $flagManager
     * @param EncryptorInterface $encryptor
     */
    public function __construct(FlagManager $flagManager, EncryptorInterface $encryptor)
    {
        $this->flagManager = $flagManager;
        $this->encryptor = $encryptor;
    }

    /**
     * Saved connection, null when the extension is not connected.
     *
     * @return array|null ['url' => string, 'token' => string, 'shop' => string]
     */
    public function getConnection()
    {
        $connection = $this->flagManager->getFlagData(self::FLAG_CONNECTION);
        if (!is_array($connection) || empty($connection['token'])) {
            return null;
        }

        $connection['token'] = $this->encryptor->decrypt($connection['token']);

        return $connection;
    }

    /**
     * Save a verified connection.
     *
     * @param string $url
     * @param string $token
     * @param string $shop
     * @return void
     */
    public function saveConnection(string $url, string $token, string $shop)
    {
        $this->flagManager->saveFlag(self::FLAG_CONNECTION, [
            'url' => $url,
            'token' => $this->encryptor->encrypt($token),
            'shop' => $shop,
        ]);
    }

    /**
     * Forget the connection and the export that used it.
     *
     * @return void
     */
    public function deleteConnection()
    {
        $this->flagManager->deleteFlag(self::FLAG_CONNECTION);
        $this->deleteJob();
    }

    /**
     * Saved export state, null when there is none.
     *
     * @return array|null
     */
    public function getJob()
    {
        $job = $this->flagManager->getFlagData(self::FLAG_JOB);

        return is_array($job) && !empty($job['job_id']) ? $job : null;
    }

    /**
     * Save export state.
     *
     * @param array $job
     * @return void
     */
    public function saveJob(array $job)
    {
        $this->flagManager->saveFlag(self::FLAG_JOB, $job);
    }

    /**
     * Forget the export state.
     *
     * @return void
     */
    public function deleteJob()
    {
        $this->flagManager->deleteFlag(self::FLAG_JOB);
    }
}
