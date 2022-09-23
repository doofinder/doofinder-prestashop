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

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');

$context = Context::getContext();

header("Content-Type:application/json; charset=utf-8");

$module = Module::getInstanceByName('doofinder');
$autoinstallerToken = Tools::getValue('token');
if ($autoinstallerToken) {
    $redirect = Context::getContext()->shop->getBaseURL(true, false)
        . $module->getPathUri() . 'config.php';
    $tmpToken = Tools::encrypt($redirect);
    if ($tmpToken == $autoinstallerToken) {
        $apiToken = Tools::getValue('api_token');
        $api_endpoint = Tools::getValue('api_endpoint');
        $admin_endpoint = Tools::getValue('admin_endpoint');
        if ($apiToken) {
            $module->saveApiData($apiToken, $api_endpoint, $admin_endpoint);
        }
        exit('OK');
    } else {
        header('HTTP/1.1 403 Forbidden', true, 403);
        $msgError = 'Forbidden access.'
                . ' Token for autoinstaller invalid.';
        exit($msgError);
    }
}
$languages = array();
$configurations = array();
$currencies = array_keys(dfTools::getAvailableCurrencies());

$display_prices = (bool) Configuration::get('DF_GS_DISPLAY_PRICES');
$prices_with_taxes = (bool) Configuration::get('DF_GS_PRICES_USE_TAX');

foreach (Language::getLanguages(true, $context->shop->id) as $lang) {
    $lang = Tools::strtoupper($lang['iso_code']);
    $currency = dfTools::getCurrencyForLanguage($lang);

    $languages[] = $lang;
    $configurations[$lang] = array(
        "language" => $lang,
        "currency" => Tools::strtoupper($currency->iso_code),
        "prices" => $display_prices,
        "taxes" => $prices_with_taxes,
    );
}

$force_ssl = (Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
$shop = $context->shop;
$base = (($force_ssl) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain);

$cfg = array(
    "platform" => array(
        "name" => "Prestashop",
        "version" => _PS_VERSION_
    ),
    "module" => array(
        "version" => Doofinder::VERSION,
        "feed" => $base . $shop->getBaseURI() . 'modules/doofinder/feed.php',
        "options" => array(
            "language" => $languages,
            "currency" => $currencies,
        ),
        "configuration" => $configurations,
    ),
);

echo dfTools::jsonEncode($cfg);
