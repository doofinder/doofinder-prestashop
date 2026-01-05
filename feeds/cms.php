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

use PrestaShop\Module\Doofinder\Feed\DfCmsBuild;
use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (function_exists('set_time_limit')) {
    @set_time_limit(3600 * 2);
}

/* ---------- START CSV-SPECIFIC CONFIG ---------- */
$debug = DfTools::getBooleanFromRequest('debug');
$limit = Tools::getValue('limit', false);
$offset = Tools::getValue('offset', false);
/* ---------- END CSV-SPECIFIC CONFIG ---------- */

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

// CMS DATA
$cms_pages = DfTools::getCmsPages($lang->id, $shop->id, $limit, $offset);
$builder = new DfCmsBuild($shop->id, $lang->id);
$builder->setCmsPages($cms_pages);
$rows = $builder->build(false);

// HEADERS
$header = ['id', 'title', 'description', 'meta_title', 'meta_description', 'tags', 'content', 'link'];
$csv = fopen('php://output', 'w');
if (!$limit || (false !== $offset && 0 === (int) $offset)) {
    fputcsv($csv, $header, DfTools::TXT_SEPARATOR);
}

// CMS PAGES
foreach ($rows as $row) {
    $csvRow = [];
    foreach ($header as $field) {
        $csvRow[$field] = array_key_exists($field, $row) ? $row[$field] : '';
    }
    fputcsv($csv, $csvRow, DfTools::TXT_SEPARATOR);
}
fclose($csv);
