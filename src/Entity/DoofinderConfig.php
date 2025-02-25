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

use Context;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderConfig
{
    public static function debug($message, $logFile = 'doofinder.log')
    {
        if (!defined('_PS_MODULE_DIR_')) {
            return;
        }

        $context = Context::getContext();
        $idShop = $context->shop->id;
        $idShopGroup = $context->shop->id_shop_group;

        $debug = \Configuration::get('DF_DEBUG', null, $idShopGroup, $idShop);
        if (!empty($debug) && $debug) {
            $message = is_string($message) ? $message : print_r($message, true);
            error_log("$message\n", 3, _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . 'doofinder' . DIRECTORY_SEPARATOR . $logFile);
        }
    }

    /**
     * Dumps the information about a variable.
     *
     * This function captures the output of `var_dump` for the provided variable and returns it as a string.
     * It uses output buffering to store the dump result and then retrieves the buffered content before returning it.
     *
     * @param mixed $variable The variable to dump. It can be of any type.
     *
     * @return string returns the dumped content of the variable as a string
     */
    public static function dump($variable)
    {
        ob_start();
        var_dump($variable);

        return ob_get_clean();
    }

    /**
     * Set the default values in the configuration
     *
     * @param int $shopGroupId
     * @param int $shopId
     *
     * @return void
     */
    public static function setDefaultShopConfig($shopGroupId, $shopId)
    {
        $apiKey = DfTools::getFormattedApiKey();
        $apiEndpoint = \Configuration::getGlobalValue('DF_AI_API_ENDPOINT');
        $apiEndpointArray = explode('-', $apiEndpoint);
        $region = $apiEndpointArray[0];

        \Configuration::updateValue('DF_ENABLE_HASH', true, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_GS_DISPLAY_PRICES', true, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_GS_PRICES_USE_TAX', true, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_FEED_FULL_PATH', true, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_SHOW_PRODUCT_VARIATIONS', 0, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_REGION', $region, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_API_KEY', $region . '-' . $apiKey, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_GS_DESCRIPTION_TYPE', DoofinderConstants::GS_SHORT_DESCRIPTION, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_FEED_MAINCATEGORY_PATH', false, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_GS_IMAGE_SIZE', key(DfTools::getAvailableImageSizes()), false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_MULTIPRICE_ENABLED', true, false, $shopGroupId, $shopId);
    }

    /**
     * Save the information that Doofinder returns after login
     *
     * @param string $apiKey
     * @param string $apiEndpoint
     * @param string $adminEndpoint
     *
     * @return void
     */
    public static function saveApiConfig($apiKey, $apiEndpoint, $adminEndpoint)
    {
        \Configuration::updateGlobalValue('DF_AI_ADMIN_ENDPOINT', $apiEndpoint);
        \Configuration::updateGlobalValue('DF_AI_API_ENDPOINT', $adminEndpoint);

        $apiEndpointArray = explode('-', $apiEndpoint);
        $region = $apiEndpointArray[0];
        $shops = \Shop::getShops();

        foreach ($shops as $shop) {
            $sid = $shop['id_shop'];
            $sgid = $shop['id_shop_group'];

            \Configuration::updateValue('DF_API_KEY', $region . '-' . $apiKey, false, $sgid, $sid);
        }
    }

    /**
     * Get the values for the data feed configuration form
     *
     * @return array
     */
    public static function getConfigFormValuesDataFeed($idShop)
    {
        return [
            'DF_SHOW_LAYER' => DfTools::getConfigByShop('DF_SHOW_LAYER', $idShop, true),
            'DF_GS_DISPLAY_PRICES' => DfTools::getConfigByShop('DF_GS_DISPLAY_PRICES', $idShop),
            'DF_GS_PRICES_USE_TAX' => DfTools::getConfigByShop('DF_GS_PRICES_USE_TAX', $idShop),
            'DF_FEED_FULL_PATH' => DfTools::getConfigByShop('DF_FEED_FULL_PATH', $idShop),
            'DF_SHOW_PRODUCT_VARIATIONS' => DfTools::getConfigByShop('DF_SHOW_PRODUCT_VARIATIONS', $idShop),
            'DF_GROUP_ATTRIBUTES_SHOWN[]' => explode(',', DfTools::getConfigByShop('DF_GROUP_ATTRIBUTES_SHOWN', $idShop)),
            'DF_SHOW_PRODUCT_FEATURES' => DfTools::getConfigByShop('DF_SHOW_PRODUCT_FEATURES', $idShop),
            'DF_FEATURES_SHOWN[]' => explode(',', DfTools::getConfigByShop('DF_FEATURES_SHOWN', $idShop)),
            'DF_GS_IMAGE_SIZE' => DfTools::getConfigByShop('DF_GS_IMAGE_SIZE', $idShop),
            'DF_UPDATE_ON_SAVE_DELAY' => DfTools::getConfigByShop('DF_UPDATE_ON_SAVE_DELAY', $idShop),
        ];
    }

    /**
     * Get the values for the advanced configuration form
     *
     * @return array
     */
    public static function getConfigFormValuesAdvanced($idShop)
    {
        return [
            'DF_SHOW_LAYER_MOBILE' => DfTools::getConfigByShop('DF_SHOW_LAYER_MOBILE', $idShop, true),
            'DF_DEBUG' => DfTools::getConfigByShop('DF_DEBUG', $idShop),
            'DF_DEBUG_CURL' => DfTools::getConfigByShop('DF_DEBUG_CURL', $idShop),
            'DF_ENABLED_V9' => DfTools::getConfigByShop('DF_ENABLED_V9', $idShop),
        ];
    }

    /**
     * Get the values for the store information form
     *
     * @return array
     */
    public static function getConfigFormValuesStoreInfo($idShop)
    {
        return [
            'DF_INSTALLATION_ID' => DfTools::getConfigByShop('DF_INSTALLATION_ID', $idShop),
            'DF_API_KEY' => \Configuration::get('DF_API_KEY'),
            'DF_REGION' => \Configuration::get('DF_REGION'),
        ];
    }

    /**
     * Checks the connection with DooManager
     *
     * @return bool
     */
    public static function checkOutsideConnection()
    {
        $client = new EasyREST(true);
        $doomanangerRegionlessUrl = sprintf(DoofinderConstants::DOOMANAGER_REGION_URL, '');
        $result = $client->get(sprintf('%s/auth/login', $doomanangerRegionlessUrl));

        return $result && $result->originalResponse && isset($result->headers['code'])
            && (strpos($result->originalResponse, 'HTTP/2 200') || $result->headers['code'] == 200);
    }
}
