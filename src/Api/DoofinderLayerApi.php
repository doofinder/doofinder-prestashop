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

namespace PrestaShop\Module\Doofinder\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Provides methods to retrieve installation data from the Doofinder Plugins API.
 */
class DoofinderLayerApi
{
    /**
     * Retrieve installation details from the Doofinder API.
     *
     * @param string $installationID Doofinder installation ID, which is the Doofinder store ID
     * @param string $apiKey API key for authentication
     * @param string $region Optional region code to determine the API endpoint (default: 'eu1')
     *
     * @return array|null Decoded JSON response containing installation data, or null on failure
     */
    public static function getInstallationData($installationID, $apiKey, $region = 'eu1')
    {
        $apiEndpoint = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region, '/installations/' . $installationID);
        $client = new EasyREST();
        $response = $client->get(
            $apiEndpoint,
            null,
            null,
            null,
            'application/json',
            ['Authorization: Token ' . $apiKey]
        );

        return json_decode($response->response, true);
    }
}
