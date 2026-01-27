<?php
/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Utils\DfTools;

/**
 * Front controller to provide Doofinder module configuration via JSON.
 *
 * This controller:
 * - Handles autoinstaller token validation and configuration updates.
 * - Provides a JSON representation of module configuration, including:
 *   - Available languages and currencies
 *   - Price display and tax settings
 *   - Feed URLs and module version
 * - Ensures proper response headers for AJAX requests.
 */
class DoofinderConfigModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template variables and output configuration as JSON.
     *
     * - If a valid autoinstaller token is provided, updates the API configuration.
     * - Otherwise, returns module configuration including languages, currencies, prices, and feed URLs.
     * - Handles compatibility across PrestaShop 1.5, 1.6, and 1.7 for AJAX responses.
     *
     * @see FrontController::initContent()
     *
     * @return void outputs JSON directly and exits
     */
    public function initContent()
    {
        parent::initContent();

        $this->ajax = true;

        header('Content-Type:application/json; charset=utf-8');

        $module = Module::getInstanceByName('doofinder');
        $autoinstallerToken = Tools::getValue('token');
        if ($autoinstallerToken) {
            $link = Context::getContext()->link;
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
