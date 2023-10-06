<?php

namespace Magefan\ShopifyBlogExport\Model;

class ShopifyMediaPusher
{
    public function execute(string $url, string $data, string $entity) {
        $decodedData = json_decode($data,true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("cookie: secure_customer_sig=; localization=UA; _y=90b96ff8-abb8-4cbb-ad89-132fe5128247; _shopify_y=90b96ff8-abb8-4cbb-ad89-132fe5128247; __ssid=73e67d4f-edcb-462c-86fa-7aa282453de3; __atuvc=79%7C42%2C32%7C43%2C65%7C44; storefront_digest=df42e7ccc309c466b25e121100190eb17bcc059e8de7871f5549981d8432c47a; rmc_logged_in_at=null; _orig_referrer=; _landing_page=%2F; _s=7f8a6470-0a6e-4d46-8a7a-813cea320ece; _shopify_s=7f8a6470-0a6e-4d46-8a7a-813cea320ece; _shopify_sa_p=; keep_alive=b6b75269-0da8-4881-abb8-c4118e5ff243; _shopify_sa_t=2022-11-21T08%3A57%3A11.482Z"));
        curl_setopt($ch, CURLOPT_POST,1);
        $result = [];

        foreach ($decodedData as $item) {
            if (file_exists($item['featured_img'])) {
                $cf = new \CURLFile($item['featured_img']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ["data" => $data, "file" => $cf, 'old_id' => $item['old_id'], 'entity' => str_replace('media_','',$entity)]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result[] = curl_exec ($ch);
            }
        }

        curl_close ($ch);

        return (string)json_encode($result);
    }
}