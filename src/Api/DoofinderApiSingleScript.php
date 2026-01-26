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
 * @license   MIT
 */

namespace PrestaShop\Module\Doofinder\Api;

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Manager\UrlManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles the migration to the Single Script from the previous Live Layer deprecated scripts.
 */
class DoofinderApiSingleScript
{
    private $installationId;
    private $apiKey;
    private $apiUrl;

    public function __construct($installationId, $region, $apiKey)
    {
        $this->installationId = $installationId;
        $this->apiKey = $apiKey;
        $this->apiUrl = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
    }

    /**
     * Make a request to the API to SET single script flag to notify the migration of this customer
     *
     * This function does not require any parameters.
     *
     * @return mixed The response from the API request
     */
    public function setSingleScriptFlag()
    {
        $endpoint = '/prestashop/migrate-unique-script';

        $url = $this->apiUrl . $endpoint;

        return $this->post($url);
    }

    /**
     * Execute a POST request to the Doofinder API with a JSON payload.
     *
     * The payload will be the installation ID.
     *
     * @param string $url Full API endpoint URL
     *
     * @return array|null Decoded JSON response from the API, or null if the request fails
     */
    private function post($url)
    {
        $client = new EasyREST();

        $body = [
            'installation_id' => $this->installationId,
        ];

        $jsonStoreData = json_encode($body);

        $response = $client->post(
            $url,
            $jsonStoreData,
            null,
            null,
            'application/json',
            ['Authorization: Token ' . $this->apiKey]
        );

        return json_decode($response->response, true);
    }
}
