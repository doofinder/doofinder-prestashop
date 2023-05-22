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

class DoofinderApiLanding
{
    public function __construct($hashid, $api_key, $region)
    {
        $this->hashid = $hashid;
        $this->api_key = $api_key;
        $this->api_url = str_replace('{region}', $region, API_URL);
    }

    /**
     * Make a request to the API to get landing data
     *
     * @param array Product data
     */
    public function getLanding($slug)
    {
        $endpoint = '/plugins/landing/' . $this->hashid . '/' . $slug;

        $url = $this->api_url . $endpoint;

        return $this->get($url);
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

        return json_decode($response->response, true);
    }
}
