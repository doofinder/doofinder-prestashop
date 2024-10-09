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

class DoofinderApiIndex
{
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey, $region)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
    }

    /**
     * Make a request to the plugins API to reprocess all the feeds
     *
     * @param string $installationId
     * @param string $callbackUrl
     */
    public function invokeReindexing($installationId, $callbackUrl = '')
    {
        $apiEndpoint = $this->apiUrl . '/process-feed';
        $jsonData = json_encode(['store_id' => $installationId, 'callback_url' => $callbackUrl]);

        return $this->post($apiEndpoint, $jsonData);
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
