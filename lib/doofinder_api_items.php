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
const API_VERSION = '2';

class DoofinderApiItems
{
    private $hashid;
    private $api_key;
    private $api_url;
    private $type;

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
        $endpoint = '/plugins/prestashop/' . $this->hashid . '/' . $this->type . '/product_update';

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
        $endpoint = '/plugins/prestashop/' . $this->hashid . '/' . $this->type . '/product_delete';

        $url = $this->api_url . $endpoint;

        return $this->post($url, $payload);
    }

    /**
     * Make a request to the API to reprocess all the feeds
     *
     * @param array items ids
     */
    public function invokeReindexing()
    {
        $installation_id = Configuration::get('DF_INSTALLATION_ID');
        $json_data = json_encode(['query' => 'mutation { process_store_feeds(id: "' . $installation_id . '", callback_url: "' . $this->getProcessCallbackUrl() . '") { id }}']);
        $response = $this->post($this->api_url . '/api/v1/graphql.json', $json_data);
        if (empty($response)) {
            return;
        }
        if (empty($response_array['errors'])) {
            Configuration::updateValue('DF_FEED_INDEXED', false);
        }
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

    /**
     * Get Process Callback URL
     *
     * @return string
     */
    private function getProcessCallbackUrl()
    {
        return Context::getContext()->link->getModuleLink('doofinder', 'callback', []);
    }
}
