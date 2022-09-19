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
$shop_id =  Tools::getValue('shop_id', NULL);
if ($autoinstaller) {
    header("Content-Type:application/json; charset=utf-8");
    if (Tools::getValue('token') == Tools::encrypt('doofinder-ajax')) {
        $doofinder->autoinstaller($shop_id);
        echo json_encode(['success' => TRUE]);
        exit();
    } else {
        $msgError = 'Forbidden access.'
            . ' Token for autoinstaller invalid.';
        die($msgError);
    }
}

if ($doofinder->canAjax()) {
    echo $doofinder->ajaxCall();
}
