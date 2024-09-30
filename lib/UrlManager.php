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

class UrlManager
{
    const API_URL = 'https://{region}-plugins.doofinder.com';

    public static function debug($message, $logFile = 'doofinder.log')
    {
        $currentPath = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
        if (!is_dir($currentPath)) {
            $currentPath = dirname(__FILE__);
        }
        $debug = Configuration::get('DF_DEBUG');
        if (isset($debug) && $debug) {
            error_log("$message\n", 3, $currentPath . DIRECTORY_SEPARATOR . $logFile);
        }
    }

    /**
     * Get store URL
     *
     * @param int $shop_id
     *
     * @return string
     */
    public static function getShopURL($shop_id)
    {
        $shop = new Shop($shop_id);
        $force_ssl = (Configuration::get('PS_SSL_ENABLED')
            && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
        $url = ($force_ssl) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain;

        return $url . self::_getShopBaseURI($shop);
    }

    /**
     * Build feed urls
     *
     * @param int $shopId
     * @param int $language
     * @param int $currency
     *
     * @return string
     */
    public static function buildFeedUrl($shopId, $language, $currency, $moduleName)
    {
        $shopUrl = self::getShopURL($shopId);

        return $shopUrl . ltrim('modules/' . $moduleName, DIRECTORY_SEPARATOR)
            . '/feed.php?'
            . 'currency=' . Tools::strtoupper($currency)
            . '&language=' . Tools::strtoupper($language)
            . '&dfsec_hash=' . Configuration::get('DF_API_KEY');
    }

    /**
     * Get Process Callback URL
     *
     * @return string
     */
    public static function getProcessCallbackUrl()
    {
        return Context::getContext()->link->getModuleLink('doofinder', 'callback', []);
    }

    public static function getInstallUrl($region)
    {
        return str_replace('{region}', $region, 'https://{region}-plugins.doofinder.com/install');
    }

    private static function _getShopBaseURI($shop)
    {
        return $shop->physical_uri . $shop->virtual_uri;
    }
}