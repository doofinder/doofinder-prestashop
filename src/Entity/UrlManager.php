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
        $shop = new \Shop($shopId);
        \Context::getContext()->shop = $shop;

        $params = [
            'language' => \Tools::strtoupper($language),
            'dfsec_hash' => \Configuration::get('DF_API_KEY'),
        ];

        if ($currency) {
            $params['currency'] = \Tools::strtoupper($currency);
        }

        $link = \Context::getContext()->link->getModuleLink(DoofinderConstants::NAME, 'feed', $params);

        /*
         * Cleans redundant parameters in URLs for PrestaShop 1.5.3
         *
         * In PrestaShop 1.5.3, 'module' and 'controller' parameters are maintained in URLs even when friendly URLs are
         * enabled, which causes navigation errors.
         * This function removes these parameters when they are not necessary, specifically when the 'fc=module'
         */
        if (\Configuration::get('PS_REWRITING_SETTINGS')) {
            $link = preg_replace('/[&?](module|controller)=[^&]+/', '', $link);
        }

        return $link;
    }

    /**
     * Get Process Callback URL
     *
     * @return string
     */
    public static function getProcessCallbackUrl($shopId)
    {
        $shop = new \Shop($shopId);
        \Context::getContext()->shop = $shop;

        return \Context::getContext()->link->getModuleLink(DoofinderConstants::NAME, 'callback', []);
    }

    public static function getInstallUrl($region)
    {
        return self::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region, '/install');
    }

    public static function getUpdateFeedUrl($region)
    {
        return self::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region, '/prestashop/feed-url-update');
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
