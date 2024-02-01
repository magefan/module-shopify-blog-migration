<?php

namespace Magefan\ShopifyBlogExport\Model;

class ShopifyPusher
{
    protected $curl;

    public function execute(string $url, string $data, string $entity) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['data' => $data, 'entity' => $entity]);

        try {
            $result = curl_exec($ch);
            if ($result === false) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            if (200 != curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                return json_encode([
                    'errorMessage' => 'Wrong Import Key',
                ]);
            }

            return $result;

        }
        catch (\Exception $e) {
            var_dump($e->getMessage());exit;
        }
        finally {
            // Close curl handle unless it failed to initialize
            if (is_resource($ch)) {
                curl_close($ch);
            }
        }
    }
}