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

const API_URL = 'https://{region}-api.doofinder.com';
const API_VERSION = '2';

class DoofinderApiProducts
{
    public function __construct($hashid, $api_key, $region)
    {
        $this->hashid = $hashid;
        $this->api_key = $api_key;
        $this->api_url = str_replace("{region}", $region, API_URL);
    }

    /**
     * Make a request to the API to update the specified products
     * @param array Product data
     */
    public function updateBulk($payload)
    {
        $endpoint = '/api/v' . API_VERSION . '/search_engines/' . $this->hashid . '/indices/product/items/_bulk';

        $url = $this->api_url . $endpoint;

        return $this->post($url, $payload);
    }

    /**
     * Make a request to the API to delete the specified products
     * @param array Product ids
     */
    public function deleteBulk($payload)
    {
        $endpoint = '/api/v' . API_VERSION . '/search_engines/' . $this->hashid . '/indices/product/items/_bulk';

        $url = $this->api_url . $endpoint;

        return $this->delete($url, $payload);
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

    private function delete($url, $payload)
    {
        $client = new EasyREST();

        $response = $client->delete(
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
