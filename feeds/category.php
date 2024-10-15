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

use PrestaShop\Module\Doofinder\Src\Entity\DfCategoryBuild;
use PrestaShop\Module\Doofinder\Src\Entity\DfTools;

if (function_exists('set_time_limit')) {
    @set_time_limit(3600 * 2);
}

$rootPath = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$configFilePath = $rootPath . '/config/config.inc.php';
if (file_exists($configFilePath)) {
    require_once $configFilePath;
    require_once dirname($_SERVER['SCRIPT_FILENAME']) . '/autoloader.php';
} else {
    require_once dirname(__FILE__) . '/../../../config/config.inc.php';
    require_once dirname(__FILE__) . '/../autoloader.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

DfTools::validateSecurityToken(Tools::getValue('dfsec_hash'));

// OUTPUT
if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=500');
}

header('Content-Type:text/plain; charset=utf-8');

// CONTEXT
$context = Context::getContext();
$shop = new Shop((int) $context->shop->id);
if (!$shop->id) {
    exit('NOT PROPERLY CONFIGURED');
}
$lang = DfTools::getLanguageFromRequest();
$context->language = $lang;

// CATEGORY DATA
$categories = DfTools::getCategories($lang->id);
$builder = new DfCategoryBuild($shop->id, $lang->id);
$builder->setCategories($categories);
$rows = $builder->build(false);

// HEADERS
$header = ['id', 'title', 'description', 'meta_title', 'meta_description', 'tags', 'link', 'image_link'];
echo implode(DfTools::TXT_SEPARATOR, $header) . PHP_EOL;
DfTools::flush();

// CATEGORIES
foreach ($rows as $row) {
    echo $row['id'] . DfTools::TXT_SEPARATOR;
    echo $row['title'] . DfTools::TXT_SEPARATOR;
    echo $row['description'] . DfTools::TXT_SEPARATOR;
    echo $row['meta_title'] . DfTools::TXT_SEPARATOR;
    echo $row['meta_description'] . DfTools::TXT_SEPARATOR;
    echo $row['tags'] . DfTools::TXT_SEPARATOR;
    echo $row['link'] . DfTools::TXT_SEPARATOR;
    echo $row['image_link'];
    echo PHP_EOL;
    DfTools::flush();
}
