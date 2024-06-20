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
if (function_exists('set_time_limit')) {
    @set_time_limit(3600 * 2);
}

$root_path = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$config_file_path = $root_path . '/config/config.inc.php';
if (file_exists($config_file_path)) {
    require_once $config_file_path;
    require_once dirname($_SERVER['SCRIPT_FILENAME']) . '/lib/dfCategory_build.php';
} else {
    require_once dirname(__FILE__) . '/../../../config/config.inc.php';
    require_once dirname(__FILE__) . '/../lib/dfCategory_build.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

dfTools::validateSecurityToken(Tools::getValue('dfsec_hash'));

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
$lang = dfTools::getLanguageFromRequest();
$context->language = $lang;

// CATEGORY DATA
$categories = dfTools::getCategories($lang->id);
$builder = new DfCategoryBuild($shop->id, $lang->id);
$builder->setCategories($categories);
$rows = $builder->build(false);

// HEADERS
$header = ['id', 'title', 'description', 'meta_title', 'meta_description', 'tags', 'link', 'image_link'];
echo implode(TXT_SEPARATOR, $header) . PHP_EOL;
dfTools::flush();

// CATEGORIES
foreach ($rows as $row) {
    echo $row['id'] . TXT_SEPARATOR;
    echo $row['title'] . TXT_SEPARATOR;
    echo $row['description'] . TXT_SEPARATOR;
    echo $row['meta_title'] . TXT_SEPARATOR;
    echo $row['meta_description'] . TXT_SEPARATOR;
    echo $row['tags'] . TXT_SEPARATOR;
    echo $row['link'] . TXT_SEPARATOR;
    echo $row['image_link'];
    echo PHP_EOL;
    dfTools::flush();
}
