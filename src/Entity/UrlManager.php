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

class UrlManager
{
    /**
     * Get store URL
     *
     * @param int $shop_id
     *
     * @return string
     */
    public static function getShopURL($shop_id)
    {
        $shop = new \Shop($shop_id);
        $force_ssl = (\Configuration::get('PS_SSL_ENABLED')
            && \Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
        $url = ($force_ssl) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain;

        return $url . self::_getShopBaseURI($shop);
    }

    /**
     * Build feed urls
     *
     * @param int $shopId
     * @param int $language
     *
     * @return string
     */
    public static function getFeedUrl($shopId, $language, $currency = null)
    {
        $shopUrl = self::getShopURL($shopId);

        return $shopUrl . ltrim('modules/' . DoofinderConstants::NAME, DIRECTORY_SEPARATOR)
            . '/feed.php?'
            . ($currency ? 'currency=' . $currency : '')
            . 'language=' . \Tools::strtoupper($language)
            . '&dfsec_hash=' . \Configuration::get('DF_API_KEY');
    }

    /**
     * Get Process Callback URL
     *
     * @return string
     */
    public static function getProcessCallbackUrl()
    {
        return \Context::getContext()->link->getModuleLink('doofinder', 'callback', []);
    }

    public static function getInstallUrl($region)
    {
        return self::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region, '/install');
    }

    /**
     * Gets an URL with its region filled in. You can also append a path (optional).
     * If the region is provided as '' it will return a regionless URL.
     *
     * @return string
     */
    public static function getRegionalUrl($url, $region, $pathToAppend = '')
    {
        if (empty($region)) {
            return sprintf($url, '') . $pathToAppend;
        }

        return sprintf($url, $region . '-') . $pathToAppend;
    }

    private static function _getShopBaseURI($shop)
    {
        return $shop->physical_uri . $shop->virtual_uri;
    }
}
