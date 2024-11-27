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
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConstants;
use PrestaShop\Module\Doofinder\Src\Entity\LanguageManager;

$rootPath = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$configFilePath = $rootPath . '/config/config.inc.php';
if (@file_exists($configFilePath)) {
    require_once $configFilePath;
} else {
    require_once dirname(__FILE__) . '/../../config/config.inc.php';
}
require_once 'autoloader.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!Module::isInstalled('doofinder') || !Module::isEnabled('doofinder')) {
    DoofinderConfig::debug('[Landing][Warning] Doofinder module is not installed or is not enabled');
    Tools::redirect('index.php?controller=404');
    exit;
}

$hashid = Tools::getValue('hashid');
$slug = Tools::getValue('slug');

if (!$hashid || !$slug) {
    DoofinderConfig::debug('[Landing][Warning] Hashid and/or slug could not be retrieved: ' . PHP_EOL . '- slug: ' . DoofinderConfig::dump($slug) . '- hashid: ' . DoofinderConfig::dump($hashid));
    Tools::redirect('index.php?controller=404');
    exit;
}

$idLang = LanguageManager::getLanguageByHashid($hashid);

if (!$idLang) {
    DoofinderConfig::debug('[Landing][Warning] Invalid Language ID: ' . DoofinderConfig::dump($idLang));
    Tools::redirect('index.php?controller=404');
    exit;
}

$link = Context::getContext()->link->getModuleLink(
    DoofinderConstants::NAME,
    'landing',
    ['landing_name' => $slug],
    null,
    $idLang
);

Tools::redirect($link);
