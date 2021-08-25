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

Context::getContext()->controller->php_self = 'search';

$doofinder = Module::getInstanceByName('doofinder');

$check_api_key = Tools::getValue('check_api_key');
if ($check_api_key) {
    die($doofinder->checkApiKey(true));
}

$autoinstaller = Tools::getValue('autoinstaller');
if ($autoinstaller) {
    if (Tools::getValue('token') == Tools::encrypt('doofinder-ajax')) {
        $apiToken = Configuration::get('DF_AI_APIKEY');
        $admin_endpoint = Configuration::get('DF_AI_ADMIN_ENDPOINT');
        $api_endpoint = Configuration::get('DF_AI_API_ENDPOINT');
        $doofinder->autoinstaller($apiToken, $api_endpoint, $admin_endpoint);
        die('OK');
    } else {
        $msgError = 'Forbidden access.'
                . ' Token for autoinstaller invalid.';
        die($msgError);
    }
}

if ($doofinder->canAjax()) {
    echo $doofinder->ajaxCall();
}
