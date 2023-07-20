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

const API_URL = 'https://{region}-admin.doofinder.com';
const API_VERSION = '1';

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
     * Make a request to the API to reprocess all the feeds
     *
     * @param string $installation_id
     * @param string $callback_url
     */
    public function invokeReindexing($installation_id, $callback_url = '')
    {
        $json_data = json_encode(['query' => 'mutation { process_store_feeds(id: "' . $installation_id . '", callback_url: "' . $callback_url . '") { id }}']);

        return $this->post(sprintf('%1$s/api/v%2$s/graphql.json', $this->api_url, API_VERSION), $json_data);
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
