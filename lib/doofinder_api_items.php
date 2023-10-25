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

class DoofinderApiItems
{
    public function __construct($hashid, $api_key, $region, $type = 'product')
    {
        $this->hashid = $hashid;
        $this->api_key = $api_key;
        $this->api_url = str_replace('{region}', $region, API_URL);
        $this->type = $type;
    }

    /**
     * Make a request to the API to update the specified items
     *
     * @param array items data
     */
    public function updateBulk($payload)
    {
        $endpoint = '/item/' . $this->hashid . '/' . $this->type . '?platform=prestashop&action=update';

        $url = $this->api_url . $endpoint;

        return $this->post($url, $payload);
    }

    /**
     * Make a request to the API to delete the specified items
     *
     * @param array items ids
     */
    public function deleteBulk($payload)
    {
        $endpoint = '/item/' . $this->hashid . '/' . $this->type . '?platform=prestashop&action=delete';

        $url = $this->api_url . $endpoint;

        return $this->post($url, $payload);
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
