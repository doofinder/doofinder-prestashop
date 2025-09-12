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
 * Handles the migration to the Single Script from the previous Live Layer deprecated scripts.
 */
class DoofinderConfig
{
    /**
     * Write debug messages to a log file if debug mode is enabled.
     *
     * The log file is stored in the module's directory under the PrestaShop installation.
     * Only logs messages if the `DF_DEBUG` configuration is enabled for the current shop.
     *
     * @param mixed $message The message to log. Can be a string or any variable (arrays/objects will be converted to string).
     * @param string $logFile Optional. The log file name (default: 'doofinder.log').
     *
     * @return void
     */
    public static function debug($message, $logFile = 'doofinder.log')
    {
        if (!defined('_PS_MODULE_DIR_')) {
            return;
        }

        $context = \Context::getContext();
        $idShop = $context->shop->id;
        $idShopGroup = $context->shop->id_shop_group;

        $debug = \Configuration::get('DF_DEBUG', null, $idShopGroup, $idShop);
        if (!empty($debug)) {
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
        if (preg_match('/([a-z]{2}[0-9])-.*/', $apiEndpoint, $matches)) {
            // If the API endpoint is in the format 'eu1-xxxx', we extract the region
            $region = $matches[1];
        } else {
            // Otherwise, we assume the default region is 'eu1'
            $region = 'eu1';
        }
        $fullApiKey = $region . '-' . $apiKey;

        \Configuration::updateValue('DF_REGION', $region, false, $shopGroupId, $shopId);
        \Configuration::updateValue('DF_API_KEY', $fullApiKey, false, $shopGroupId, $shopId);
        \Configuration::updateGlobalValue('DF_REGION', $region);
        \Configuration::updateGlobalValue('DF_API_KEY', $fullApiKey);
        self::setSharedDefaultConfig($shopGroupId, $shopId);
    }

    /**
     * Set the default values that don't need the API Key to be calculated in the configuration.
     * This function is useful for manual installations where the API key is not present until
     * the user enters it manually.
     *
     * @param int $shopGroupId
     * @param int $shopId
     *
     * @return void
     */
    public static function setSharedDefaultConfig($shopGroupId, $shopId)
    {
        $defaultConfigs = self::getDefaultConfigData();
        foreach ($defaultConfigs as $key => $value) {
            \Configuration::updateValue($key, $value, false, $shopGroupId, $shopId);
        }
    }

    /**
     * Set the default values that don't need the API Key to be calculated in the configuration, but
     * unlike `setSharedDefaultConfig` function, it sets fallback data globally, not at shop level nor
     * shop group level.
     *
     * @return void
     */
    public static function setSharedGlobalDefaultConfig()
    {
        $defaultConfigs = self::getDefaultConfigData();
        foreach ($defaultConfigs as $key => $value) {
            \Configuration::updateGlobalValue($key, $value);
        }
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

        if (preg_match('/([a-z]{2}[0-9])-.*/', $apiEndpoint, $matches)) {
            // If the API endpoint is in the format 'eu1-xxxx', we extract the region
            $region = $matches[1];
        } else {
            // Otherwise, we assume the default region is 'eu1'
            $region = 'eu1';
        }

        $shops = \Shop::getShops();

        foreach ($shops as $shop) {
            $sid = $shop['id_shop'];
            $sgid = $shop['id_shop_group'];

            \Configuration::updateValue('DF_API_KEY', $region . '-' . $apiKey, false, $sgid, $sid);
            \Configuration::updateGlobalValue('DF_API_KEY', $region . '-' . $apiKey);
        }
    }

    /**
     * Get the values for the data feed configuration form
     *
     * @return array
     */
    public static function getConfigFormValuesDataFeed($idShop)
    {
        /*
        Some parameters are still using the `Configuration::get()` instead of the
        new `DfTools::getConfigByShop()` one. The reason behind this is that the ones
        using the `Configuration::get()` have default global pre-configured values that
        must be taken into account too.
        */
        return [
            'DF_SHOW_LAYER' => \Configuration::get('DF_SHOW_LAYER', null, null, null, true),
            'DF_GS_DISPLAY_PRICES' => \Configuration::get('DF_GS_DISPLAY_PRICES', null, null, null, true),
            'DF_GS_PRICES_USE_TAX' => \Configuration::get('DF_GS_PRICES_USE_TAX'),
            'DF_FEED_FULL_PATH' => \Configuration::get('DF_FEED_FULL_PATH'),
            'DF_SHOW_PRODUCT_VARIATIONS' => DfTools::getConfigByShop('DF_SHOW_PRODUCT_VARIATIONS', $idShop),
            'DF_GROUP_ATTRIBUTES_SHOWN[]' => explode(',', DfTools::getConfigByShop('DF_GROUP_ATTRIBUTES_SHOWN', $idShop)),
            'DF_SHOW_PRODUCT_FEATURES' => DfTools::getConfigByShop('DF_SHOW_PRODUCT_FEATURES', $idShop),
            'DF_FEATURES_SHOWN[]' => explode(',', DfTools::getConfigByShop('DF_FEATURES_SHOWN', $idShop)),
            'DF_GS_IMAGE_SIZE' => \Configuration::get('DF_GS_IMAGE_SIZE'),
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
        /*
        `DF_MULTIPRICE_ENABLED` is still using the `Configuration::get()` instead of the
        new `DfTools::getConfigByShop()` one. The reason behind this is that the ones
        using the `Configuration::get()` have default global pre-configured values that
        must be taken into account too.
        */
        return [
            'DF_SHOW_LAYER_MOBILE' => DfTools::getConfigByShop('DF_SHOW_LAYER_MOBILE', $idShop, true),
            'DF_DEBUG' => DfTools::getConfigByShop('DF_DEBUG', $idShop),
            'DF_DEBUG_CURL' => DfTools::getConfigByShop('DF_DEBUG_CURL', $idShop),
            'DF_ENABLED_V9' => DfTools::getConfigByShop('DF_ENABLED_V9', $idShop, true),
            'DF_MULTIPRICE_ENABLED' => \Configuration::get('DF_MULTIPRICE_ENABLED', null, null, null, true),
        ];
    }

    /**
     * Get the values for the store information form
     *
     * @return array
     */
    public static function getConfigFormValuesStoreInfo($idShop)
    {
        /*
        `DF_API_KEY` and `DF_REGION` are still using the `Configuration::get()` instead of the
        new `DfTools::getConfigByShop()` one because they should use the global value if it exists.
        */
        $config = [
            'DF_INSTALLATION_ID' => DfTools::getConfigByShop('DF_INSTALLATION_ID', $idShop),
            'DF_API_KEY' => \Configuration::get('DF_API_KEY'),
            'DF_REGION' => \Configuration::get('DF_REGION'),
        ];

        $hashidKeys = DfTools::getHashidKeys();
        $isAdvParamPresent = (bool) \Tools::getValue('adv', 0);
        $multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');
        $keyToUse = 'key';
        if ($multipriceEnabled) {
            $keyToUse = 'keyMultiprice';
        }
        if ($isAdvParamPresent) {
            foreach ($hashidKeys as $hashidKey) {
                // To avoid overriding already defined values in multiprice cases
                if (!empty($config[$hashidKey[$keyToUse]])) {
                    continue;
                }
                $config[$hashidKey[$keyToUse]] = \Configuration::get($hashidKey['key']);
            }
        }

        return $config;
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

        return $result->originalResponse && isset($result->headers['code'])
            && (strpos($result->originalResponse, 'HTTP/2 200') !== false || $result->headers['code'] == 200);
    }

    /**
     * Gets the default config data as key-value pairs to
     * keep the single source of truth.
     *
     * @return array
     */
    private static function getDefaultConfigData()
    {
        return [
            'DF_ENABLE_HASH' => true,
            'DF_GS_DISPLAY_PRICES' => true,
            'DF_GS_PRICES_USE_TAX' => true,
            'DF_FEED_FULL_PATH' => true,
            'DF_SHOW_PRODUCT_VARIATIONS' => 0,
            'DF_GS_DESCRIPTION_TYPE' => DoofinderConstants::GS_SHORT_DESCRIPTION,
            'DF_FEED_MAINCATEGORY_PATH' => false,
            'DF_GS_IMAGE_SIZE' => key(DfTools::getAvailableImageSizes()),
            'DF_MULTIPRICE_ENABLED' => true,
        ];
    }
}
