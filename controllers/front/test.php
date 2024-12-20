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

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConfig;
use PrestaShop\Module\Doofinder\Src\Entity\UrlManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderTestModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $module = Module::getInstanceByName('doofinder');

        $process_callback_url = "";
        //$process_callback_url = UrlManager::getProcessCallbackUrl();
        DoofinderConfig::debug('Process callback url: ' . $process_callback_url);


        $feed_urls = [];
        /*
        $context = \Context::getContext();
        $languages = \Language::getLanguages(true, $context->shop->id);
        foreach ($languages as $lang) {
            foreach (\Currency::getCurrencies() as $cur) {
                $currencyIso = \Tools::strtoupper($cur['iso_code']);
                $langIso = \Tools::strtoupper($lang['iso_code']);
                $feed_urls[] = [
                    'url' => UrlManager::getFeedUrl($context->shop->id, $langIso, $currencyIso),
                    'lang' => $langIso,
                    'currency' => $currencyIso,
                ];
            }
        }
*/
        $resp = [
            "process_callback_url" => $process_callback_url,
            "feed_urls" => $feed_urls,
        ];

        $this->ajaxRender(json_encode($resp));
        exit;
    }
}
