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

class DoofinderInstallation
{
    private $api_key;
    private $api_url;

    public function __construct($api_key, $region)
    {
        $this->api_key = $api_key;
        $this->api_url = str_replace('{region}', $region, API_URL);
    }

    /**
     * Make a request to the plugins API to to check that the update on save is valid
     *
     * @param string $installation_id
     * @param string $callback_url
     */
    public function is_valid_update_on_save($installation_id)
    {
        $api_endpoint = $this->api_url . '/' . $installation_id . '/validate-update-on-save';

        return $this->get($api_endpoint);
    }

    private function get($url)
    {
        $client = new EasyREST();

        $response = $client->get(
            $url,
            null,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $this->api_key]
        );

        $decode_response = json_decode($response->response, true);

        if (empty($decode_response) || $decode_response['status'] !== 200) {
            $this->debug('Error checking search engines: ' . json_encode($decode_response));

            return false;
        }

        return @$decode_response['apt?'];
    }
}
