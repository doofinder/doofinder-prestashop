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

class DoofinderApiUniqueScript
{
    public function __construct($installationId, $region, $apiKey)
    {
        $this->installationId = $installationId;
        $this->apiKey = $apiKey;
        $this->apiUrl = str_replace('{region}', $region, API_URL);
    }

    /**
     * Make a request to the API to SET unique script flag to notify the migration of this customer
     *
     * This function does not require any parameters.
     *
     * @return mixed The response from the API request
     */
    public function set_unique_script_flag()
    {
        $endpoint = '/prestashop/migrate-unique-script';

        $url = $this->apiUrl . $endpoint;

        return $this->post($url);
    }

    private function post($url)
    {
        $client = new EasyREST();

        $body = [
            'installation_id' => $this->installationId,
        ];

        $json_store_data = json_encode($body);

        $response = $client->post(
            $url,
            $json_store_data,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $this->apiKey]
        );

        return json_decode($response->response, true);
    }
}
