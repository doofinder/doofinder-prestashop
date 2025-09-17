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

namespace PrestaShop\Module\Doofinder\Api;

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Manager\UrlManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles communication with the Doofinder Plugins API for reindexing feeds.
 */
class DoofinderApiIndex
{
    /**
     * @var string API key used for authentication with Doofinder Plugins API
     */
    private $apiKey;

    /**
     * @var string Base URL of the Doofinder API for the specified region
     */
    private $apiUrl;

    /**
     * DoofinderApiIndex constructor.
     *
     * Initializes the API key and determines the regional API URL.
     *
     * @param string $apiKey API key for authentication
     * @param string $region Region code used to determine the API endpoint
     */
    public function __construct($apiKey, $region)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
    }

    /**
     * Invoke reindexing for all feeds in the store.
     *
     * Sends a request to the Doofinder Plugins API to reprocess all feeds.
     *
     * @param string $installationId Unique identifier of the store installation
     * @param string $callbackUrl Optional callback URL to receive notifications once the process completes
     *
     * @return array|null Returns the decoded JSON response from the API, or null on failure
     */
    public function invokeReindexing($installationId, $callbackUrl = '')
    {
        $apiEndpoint = $this->apiUrl . '/process-feed';
        $jsonData = json_encode(['store_id' => $installationId, 'callback_url' => $callbackUrl]);

        return $this->post($apiEndpoint, $jsonData);
    }

    /**
     * Execute a POST request to a given URL with a JSON payload.
     *
     * @param string $url The API endpoint URL
     * @param string $payload JSON-encoded string to send in the request body
     *
     * @return array|null Returns the decoded JSON response, or null if the request fails
     */
    private function post($url, $payload)
    {
        $client = new EasyREST();

        $response = $client->post(
            $url,
            $payload,
            null,
            null,
            'application/json',
            ['Authorization: Token ' . $this->apiKey]
        );

        return json_decode($response->response, true);
    }
}
