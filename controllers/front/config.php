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
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Doofinder\Src\Entity\DfTools;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConfig;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConstants;

class DoofinderConfigModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->ajax = true;

        header('Content-Type:application/json; charset=utf-8');

        $module = Module::getInstanceByName('doofinder');
        $autoinstallerToken = Tools::getValue('token');
        if ($autoinstallerToken) {
            $link = \Context::getContext()->link;
            $redirect = $link->getPageLink('module-doofinder-config');
            $tmpToken = DfTools::encrypt($redirect);
            if ($tmpToken == $autoinstallerToken) {
                $apiToken = Tools::getValue('api_token');
                $apiEndpoint = Tools::getValue('api_endpoint');
                $adminEndpoint = Tools::getValue('admin_endpoint');
                if ($apiToken) {
                    DoofinderConfig::saveApiConfig($apiToken, $apiEndpoint, $adminEndpoint);
                }
                echo json_encode(['success' => true]);
                exit;
            } else {
                header('HTTP/1.1 403 Forbidden', true, 403);
                $msgError = 'Forbidden access.'
                    . ' Token for autoinstaller invalid.';
                exit($msgError);
            }
        }

        $languages = [];
        $configurations = [];
        $currencies = array_keys(DfTools::getAvailableCurrencies());

        $display_prices = (bool) Configuration::get('DF_GS_DISPLAY_PRICES');
        $prices_with_taxes = (bool) Configuration::get('DF_GS_PRICES_USE_TAX');

        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            $lang = Tools::strtoupper($lang['iso_code']);
            $currency = DfTools::getCurrencyForLanguage($lang);

            $languages[] = $lang;
            $configurations[$lang] = [
                'language' => $lang,
                'currency' => Tools::strtoupper($currency->iso_code),
                'prices' => $display_prices,
                'taxes' => $prices_with_taxes,
            ];
        }

        $force_ssl = (Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
        $shop = $this->context->shop;
        $base = (($force_ssl) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain);

        $cfg = [
            'platform' => [
                'name' => 'Prestashop',
                'version' => _PS_VERSION_,
            ],
            'module' => [
                'version' => DoofinderConstants::VERSION,
                'feed' => $base . $shop->getBaseURI() . 'module/doofinder/feed',
                'options' => [
                    'language' => $languages,
                    'currency' => $currencies,
                ],
                'configuration' => $configurations,
            ],
        ];

        $jsonCfg = DfTools::jsonEncode($cfg);
        if (method_exists($this, 'ajaxRender')) {
            $this->ajaxRender($jsonCfg);
            exit;
        } elseif (method_exists($this, 'ajaxDie')) {
            // Workaround for PS 1.6 as ajaxRender is not available
            $this->ajaxDie($jsonCfg);
        } else {
            // Workaround for PS 1.5
            echo $jsonCfg;
            exit;
        }
    }
}
