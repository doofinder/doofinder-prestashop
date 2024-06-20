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
$root_path = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$config_file_path = $root_path . '/config/config.inc.php';
if (@file_exists($config_file_path)) {
    require_once $config_file_path;
    require_once $root_path . '/init.php';
} else {
    require_once dirname(__FILE__) . '/../../config/config.inc.php';
    require_once dirname(__FILE__) . '/../../init.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

$doofinder = Module::getInstanceByName('doofinder');

$check_api_key = Tools::getValue('check_api_key');
if ($check_api_key) {
    exit($doofinder->checkApiKey(true));
}

$autoinstaller = Tools::getValue('autoinstaller');
$shop_id = Tools::getValue('shop_id', null);
if ($autoinstaller) {
    header('Content-Type:application/json; charset=utf-8');
    if (Tools::getValue('token') == Tools::encrypt('doofinder-ajax')) {
        $doofinder->autoinstaller($shop_id);
        echo json_encode(['success' => true]);
        exit;
    } else {
        $msgError = 'Forbidden access.'
            . ' Token for autoinstaller invalid.';
        exit($msgError);
    }
}
