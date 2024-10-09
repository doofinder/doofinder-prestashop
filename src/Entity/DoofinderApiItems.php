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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderApiItems
{
    private $hashid;
    private $apiKey;
    private $apiUrl;
    private $type;

    public function __construct($hashid, $apiKey, $region, $type = 'product')
    {
        $this->hashid = $hashid;
        $this->apiKey = $apiKey;
        $this->apiUrl = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
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

        $url = $this->apiUrl . $endpoint;

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

        $url = $this->apiUrl . $endpoint;

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
            ['Authorization: Token ' . $this->apiKey]
        );

        return json_decode($response->response, true);
    }
}
