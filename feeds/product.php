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

use PrestaShop\Module\Doofinder\Src\Entity\DfProductBuild;
use PrestaShop\Module\Doofinder\Src\Entity\DfTools;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConstants;

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * Accepted parameters:
 *
 * - limit:      Max results in this request.
 * - offset:     Zero-based position to start getting results.
 * - language:   Language ISO code, like "es" or "en"
 * - taxes:      Boolean. Apply taxes to prices. Default true.
 * - prices:     Boolean. Display Prices. Default true.
 * - currency:   Currency ISO code, like "EUR" or "GBP". It needs to be specified
 *               non multiprice SE stores. For multiprice SE, the currency is not
 *               included in the feed URL
 */
if (function_exists('set_time_limit')) {
    @set_time_limit(3600 * 2);
}

DfTools::validateSecurityToken(Tools::getValue('dfsec_hash'));

function slugify($text)
{
    // replace non letter or digits by -
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

    // trim
    $text = trim($text, '-');

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // lowercase
    $text = Tools::strtolower($text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

/**
 *  @author camlafit <https://github.com/camlafit>
 *  Merge multidemensionnal array by value on each row
 *  https://stackoverflow.com/questions/7973915/php-merge-arrays-by-value
 */
function arrayMergeByIdProduct($array1 = [], $array2 = [])
{
    $sub_key = 'id_product';
    $result = [];
    $result_row = [];
    if (empty($array1)) {
        return $array2;
    }
    if (empty($array2)) {
        return $array1;
    }
    foreach ($array1 as $item1) {
        $result_row = [];
        // Merge data
        foreach ($array2 as $item2) {
            if ($item1[$sub_key] == $item2[$sub_key]) {
                $result_row = array_merge($item1, $item2);
                break;
            }
        }
        // If no array merged
        if (empty($result_row)) {
            $result_row = $item1;
        }
        $result[] = $result_row;
    }

    return $result;
}

$context = Context::getContext();

$shop = new Shop((int) $context->shop->id);
if (!$shop->id) {
    exit('NOT PROPERLY CONFIGURED');
}

// CONFIG
$lang = DfTools::getLanguageFromRequest();
$context->language = $lang;
$country = Configuration::get('PS_COUNTRY_DEFAULT');
$context->country = new Country($country);
$currency = DfTools::getCurrencyForLanguageFromRequest($lang);
$multiprice_enabled = Configuration::get('DF_MULTIPRICE_ENABLED');
$currencies = Currency::getCurrenciesByIdShop($context->shop->id);

$cfg_short_description = (DfTools::cfg(
    $shop->id,
    'DF_GS_DESCRIPTION_TYPE',
    DoofinderConstants::GS_SHORT_DESCRIPTION
) == DoofinderConstants::GS_SHORT_DESCRIPTION);

$cfg_display_prices = DfTools::getBooleanFromRequest(
    'prices',
    (bool) DfTools::cfg($shop->id, 'DF_GS_DISPLAY_PRICES', DoofinderConstants::YES)
);
$cfg_prices_w_taxes = DfTools::getBooleanFromRequest(
    'taxes',
    (bool) DfTools::cfg($shop->id, 'DF_GS_PRICES_USE_TAX', DoofinderConstants::YES)
);
$cfg_image_size = DfTools::cfg($shop->id, 'DF_GS_IMAGE_SIZE');
$cfg_mod_rewrite = DfTools::cfg($shop->id, 'PS_REWRITING_SETTINGS', DoofinderConstants::YES);
$cfg_product_variations = (int) DfTools::cfg($shop->id, 'DF_SHOW_PRODUCT_VARIATIONS');
$cfg_product_features = DfTools::cfg($shop->id, 'DF_SHOW_PRODUCT_FEATURES');
$cfg_debug = DfTools::cfg($shop->id, 'DF_DEBUG');
$cfg_features_shown = explode(',', DfTools::cfg($shop->id, 'DF_FEATURES_SHOWN'));

$cfg_group_attributes_shown = explode(',', DfTools::cfg($shop->id, 'DF_GROUP_ATTRIBUTES_SHOWN'));

$limit_group_attributes = false;
if (
    is_array($cfg_group_attributes_shown)
    && count($cfg_group_attributes_shown) > 0
    && $cfg_group_attributes_shown[0] !== ''
) {
    $group_attributes = AttributeGroup::getAttributesGroups($lang->id);
    $group_attributes_slug = [];
    foreach ($group_attributes as $g) {
        if (in_array($g['id_attribute_group'], $cfg_group_attributes_shown)) {
            $group_attributes_slug[] = slugify($g['name']);
        }
    }
    $limit_group_attributes = true;
}

$debug = DfTools::getBooleanFromRequest('debug', false);
$limit = Tools::getValue('limit', false);
$offset = Tools::getValue('offset', false);

// To prevent printing errors or warnings that may corrupt the feed.
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if ($cfg_debug) {
    error_log("Starting feed.\n", 3, 'doofinder.log');
}

// OUTPUT
if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=500');
}

header('Content-Type:text/plain; charset=utf-8');

// HEADER
$header = ['id'];
if ($cfg_product_variations == 1) {
    $header[] = 'item_group_id';
}
$header = array_merge($header, [
    'title', 'link', 'description', 'alternate_description', 'meta_title', 'meta_description', 'image_link', 'main_category',
    'categories', 'availability', 'brand', 'mpn', 'ean13', 'upc', 'reference',
    'supplier_reference', 'extra_title_1', 'extra_title_2', 'tags',
]);

if (DfTools::versionGte('1.7.0.0')) {
    $header = array_merge($header, ['isbn']);
}

$header[] = 'stock_quantity';

if ($cfg_display_prices) {
    $header[] = 'price';
    $header[] = 'sale_price';
    $header[] = 'df_multiprice';
}

$additionalAttributesHeaders = [];

if ($cfg_product_variations == 1) {
    $header[] = 'variation_reference';
    $header[] = 'variation_supplier_reference';
    $header[] = 'variation_mpn';
    $header[] = 'variation_ean13';
    $header[] = 'variation_upc';
    $header[] = 'df_group_leader';
    $header[] = 'df_variants_information';
    $attribute_keys = DfTools::getAttributeKeysForShopAndLang($shop->id, $lang->id);
    $alt_attribute_keys = [];

    foreach ($attribute_keys as $key) {
        $header_value = slugify($key);
        if ($limit_group_attributes && !in_array($header_value, $group_attributes_slug)) {
            continue;
        }
        $alt_attribute_keys[] = $key;
        $additionalAttributesHeaders[] = $header_value;
    }
    $attribute_keys = $alt_attribute_keys;
}

if ($cfg_product_features) {
    $all_feature_keys = DfTools::getFeatureKeysForShopAndLang($shop->id, $lang->id);

    if (
        is_array($cfg_features_shown)
        && count($cfg_features_shown) > 0
        && $cfg_features_shown[0] !== ''
    ) {
        $feature_keys = DfTools::getSelectedFeatures($all_feature_keys, $cfg_features_shown);
    } else {
        $feature_keys = $all_feature_keys;
    }
    $additionalAttributesHeaders[] = 'attributes';
}

$header = array_merge($header, $additionalAttributesHeaders);

/**
 * @author camlafit <https://github.com/camlafit>
 * Extend Doofinder feed
 *
 * To add an new header, module can do an array_merge on $extraHeader
 * To add an new data to a product, module must create a multidimensionnal array as this :
 * array(
 *   index => array(
 *    'id_product' => value,
 *    'new_header_column_name' => 'value related to the new column'
 *   ),
 *   [...]
 * )
 * As each module can extend $extraHeader and $extraRows don't forget to merge them
 */
$extraHeader = [];
$extraRows = [];
Hook::exec('actionDoofinderExtendFeed', [
    'extra_header' => &$extraHeader,
    'extra_rows' => &$extraRows,
    'id_lang' => $lang->id,
    'id_shop' => $shop->id,
    'limit' => $limit,
    'offset' => $offset,
]);

$header = array_merge($header, $extraHeader);

$csv = fopen('php://output', 'w');
if (!$limit || ($offset !== false && (int) $offset === 0)) {
    fputcsv($csv, $header, DfTools::TXT_SEPARATOR);
}
fclose($csv);

// PRODUCTS
$rows = DfTools::getAvailableProductsForLanguage($lang->id, $shop->id, $limit, $offset);

$rows = arrayMergeByIdProduct($rows, $extraRows);

// In case there is no need to display prices, avoid calculating the mins by variant
$minPriceVariantByProductId = $cfg_display_prices ? DfTools::getMinVariantPrices($rows, $cfg_prices_w_taxes, $currencies, $lang->id, $shop->id) : [];
$additionalHeaders = array_merge($additionalAttributesHeaders, $extraHeader);

$dfProductBuild = new DfProductBuild($shop->id, $lang->id, $currency->id);

$csv = fopen('php://output', 'w');
foreach ($rows as $row) {
    $product = $dfProductBuild->buildProduct($row, $minPriceVariantByProductId, $additionalAttributesHeaders, $additionalHeaders);
    $product = $dfProductBuild->applySpecificTransformationsForCsv($product, $extraHeader, $header);
    fputcsv($csv, $product, DfTools::TXT_SEPARATOR);
}
fclose($csv);
