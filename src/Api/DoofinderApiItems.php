<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Api;

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Manager\UrlManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles communication with the Doofinder Plugins API for managing individual items
 * (such as products) in bulk for a specific store installation.
 */
class DoofinderApiItems
{
    /**
     * @var string Search Engine hashid
     */
    private $hashid;

    /**
     * @var string API key used for authentication with Doofinder Plugins API
     */
    private $apiKey;

    /**
     * @var string Base URL of the Doofinder API for the specified region
     */
    private $apiUrl;

    /**
     * @var string Type of item to manage (e.g., 'product', 'category' or 'cms')
     */
    private $type;

    /**
     * DoofinderApiItems constructor.
     *
     * Initializes the API connection and sets the type of items to manage.
     *
     * @param string $hashid Search Engine hashid
     * @param string $apiKey API key for authentication
     * @param string $region Region code used to determine the API endpoint
     * @param string $type Optional type of item (default: 'product')
     */
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
     * @param array|string $payload Items data to update. This can be an associative array or a JSON string.
     *
     * @return array Response from the API
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
     * @param string|null $payload Items IDs to delete
     *
     * @return array Response from the API
     */
    public function deleteBulk($payload)
    {
        $endpoint = '/item/' . $this->hashid . '/' . $this->type . '?platform=prestashop&action=delete';

        $url = $this->apiUrl . $endpoint;

        return $this->post($url, $payload);
    }

    /**
     * Execute a POST request to the Doofinder API with a JSON payload.
     *
     * @param string $url Full API endpoint URL
     * @param mixed $payload Data to send in the request body
     *
     * @return array|null Decoded JSON response from the API, or null if the request fails
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
