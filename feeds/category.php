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

use PrestaShop\Module\Doofinder\Feed\DfCategoryBuild;
use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (function_exists('set_time_limit')) {
    @set_time_limit(3600 * 2);
}

// To prevent printing errors or warnings that may corrupt the feed.
$debug = DfTools::getBooleanFromRequest('debug', false);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// OUTPUT
if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=500');
}

header('Content-Type:text/plain; charset=utf-8');

DfTools::validateSecurityToken(Tools::getValue('dfsec_hash'));

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
$header = ['id', 'title', 'description', 'meta_title', 'meta_description', 'link', 'image_link'];

$csv = fopen('php://output', 'w');
fputcsv($csv, $header, DfTools::TXT_SEPARATOR);

// CATEGORIES
foreach ($rows as $row) {
    $csvRow = [];
    foreach ($header as $field) {
        $csvRow[$field] = array_key_exists($field, $row) ? $row[$field] : '';
    }
    fputcsv($csv, $csvRow, DfTools::TXT_SEPARATOR);
}
fclose($csv);
