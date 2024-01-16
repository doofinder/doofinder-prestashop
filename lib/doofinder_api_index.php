<?php

/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   GPLv3
 */
require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

const API_URL = 'https://{region}-plugins.doofinder.com';

class DoofinderApiIndex
{
    private $api_key;
    private $api_url;

    public function __construct($api_key, $region)
    {
        $this->api_key = $api_key;
        $this->api_url = str_replace('{region}', $region, API_URL);
    }

    /**
     * Make a request to the plugins API to reprocess all the feeds
     *
     * @param string $installation_id
     * @param string $callback_url
     */
    public function invokeReindexing($installation_id, $callback_url = '')
    {
        $api_endpoint = $this->api_url . '/process-feed';
        $json_data = json_encode(['store_id' => $installation_id, 'callback_url' => $callback_url]);

        return $this->post($api_endpoint, $json_data);
    }

    private function post($url, $payload)
    {
        $client = new EasyREST();

        $response = $client->post(
            $url,
            $payload,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $this->api_key]
        );

        return json_decode($response->response, true);
    }
}
