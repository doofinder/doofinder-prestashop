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

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderApi;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation;

$rootPath = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$configFilePath = $rootPath . '/config/config.inc.php';
if (@file_exists($configFilePath)) {
    require_once $configFilePath;
    require_once $rootPath . '/init.php';
} else {
    require_once dirname(__FILE__) . '/../../config/config.inc.php';
    require_once dirname(__FILE__) . '/../../init.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'autoloader.php';

$checkApiKey = Tools::getValue('check_api_key');
if ($checkApiKey) {
    exit(DoofinderApi::checkApiKey(true));
}

$autoinstaller = Tools::getValue('autoinstaller');
$shopId = Tools::getValue('shop_id', null);
if ($autoinstaller) {
    header('Content-Type:application/json; charset=utf-8');
    if (Tools::getValue('token') == Tools::encrypt('doofinder-ajax')) {
        DoofinderInstallation::autoinstaller($shopId);
        echo json_encode(['success' => true]);
        exit;
    } else {
        $msgError = 'Forbidden access.'
            . ' Token for autoinstaller invalid.';
        exit($msgError);
    }
}
