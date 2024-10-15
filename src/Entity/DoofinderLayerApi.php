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

class DoofinderLayerApi
{
    public static function getInstallationData($installationID, $apiKey, $region = 'eu1')
    {
        $apiEndpoint = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region, '/installations/' . $installationID);
        $client = new EasyREST();
        $response = $client->get(
            $apiEndpoint,
            null,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $apiKey]
        );

        return json_decode($response->response, true);
    }
}
