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

namespace PrestaShop\Module\Doofinder\Lib;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderConfig
{
    public static function debug($message, $logFile = 'doofinder.log')
    {
        $currentPath = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
        if (!is_dir($currentPath)) {
            $currentPath = dirname(__FILE__);
        }
        $debug = Configuration::get('DF_DEBUG');
        if (isset($debug) && $debug) {
            error_log("$message\n", 3, $currentPath . '/' . $logFile);
        }
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
        $apikey = Configuration::getGlobalValue('DF_AI_APIKEY');
        $apiEndpoint = Configuration::getGlobalValue('DF_AI_API_ENDPOINT');
        $apiEndpointArray = explode('-', $apiEndpoint);
        $region = $apiEndpointArray[0];

        Configuration::updateValue('DF_ENABLE_HASH', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_DISPLAY_PRICES', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_PRICES_USE_TAX', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_FEED_FULL_PATH', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_SHOW_PRODUCT_VARIATIONS', 0, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_REGION', $region, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_API_KEY', $region . '-' . $apikey, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_DESCRIPTION_TYPE', DoofinderConstants::GS_SHORT_DESCRIPTION, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_FEED_MAINCATEGORY_PATH', false, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_IMAGE_SIZE', key(dfTools::getAvailableImageSizes()), false, $shopGroupId, $shopId);
    }
}
