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

// GENERAL PURPOSE VARIABLES + CONTEXT
$lang = DfTools::getLanguageFromRequest();
$context->language = $lang;
$country = (int) DfTools::cfg($shop->id, 'PS_COUNTRY_DEFAULT');
$context->country = new Country($country);
$currency = DfTools::getCurrencyForLanguageFromRequest($lang);
$currencies = Currency::getCurrenciesByIdShop($context->shop->id);

/* ---------- START CONFIG ---------- */
$shouldDisplayPrices = DfTools::getBooleanFromRequest(
    'prices',
    (bool) DfTools::cfg($shop->id, 'DF_GS_DISPLAY_PRICES', DoofinderConstants::YES)
);
$shouldPricesUseTaxes = DfTools::getBooleanFromRequest(
    'taxes',
    (bool) DfTools::cfg($shop->id, 'DF_GS_PRICES_USE_TAX', DoofinderConstants::YES)
);
$isMultipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');
$shouldShowProductVariations = (int) DfTools::cfg($shop->id, 'DF_SHOW_PRODUCT_VARIATIONS');
$shouldShowProductFeatures = DfTools::cfg($shop->id, 'DF_SHOW_PRODUCT_FEATURES');
$isDebugEnabled = DfTools::cfg($shop->id, 'DF_DEBUG');
$shouldFeaturesBeShown = explode(',', DfTools::cfg($shop->id, 'DF_FEATURES_SHOWN'));
$shouldGroupAttributesBeShown = explode(',', DfTools::cfg($shop->id, 'DF_GROUP_ATTRIBUTES_SHOWN'));
$shouldLimitGroupAttributes = false;
$debug = DfTools::getBooleanFromRequest('debug', false);
$limit = Tools::getValue('limit', false);
$offset = Tools::getValue('offset', false);
/* ---------- END CONFIG ---------- */

// To prevent printing errors or warnings that may corrupt the feed.
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (
    is_array($shouldGroupAttributesBeShown)
    && count($shouldGroupAttributesBeShown) > 0
    && $shouldGroupAttributesBeShown[0] !== ''
) {
    $groupAttributes = AttributeGroup::getAttributesGroups($lang->id);
    $groupAttributesSlug = [];
    foreach ($groupAttributes as $g) {
        if (in_array($g['id_attribute_group'], $shouldGroupAttributesBeShown)) {
            $groupAttributesSlug[] = DfTools::slugify($g['name']);
        }
    }
    $shouldLimitGroupAttributes = true;
}

if ($isDebugEnabled) {
    error_log("Starting feed.\n", 3, 'doofinder.log');
}

// OUTPUT
if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=500');
}

header('Content-Type:text/plain; charset=utf-8');

// HEADER
$header = ['id'];
if (1 === $shouldShowProductVariations) {
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

if ($shouldDisplayPrices) {
    $header[] = 'price';
    $header[] = 'sale_price';

    if ($isMultipriceEnabled) {
        $header[] = 'df_multiprice';
    }
}

$additionalAttributesHeaders = [];

if (1 === $shouldShowProductVariations) {
    $header[] = 'variation_reference';
    $header[] = 'variation_supplier_reference';
    $header[] = 'variation_mpn';
    $header[] = 'variation_ean13';
    $header[] = 'variation_upc';
    $header[] = 'df_group_leader';
    $header[] = 'df_variants_information';
    $attributeKeys = DfTools::getAttributeKeysForShopAndLang($shop->id, $lang->id);
    $altAttributeKeys = [];

    foreach ($attributeKeys as $key) {
        $headerValue = DfTools::slugify($key);
        if ($shouldLimitGroupAttributes && !in_array($headerValue, $groupAttributesSlug)) {
            continue;
        }
        $altAttributeKeys[] = $key;
        $additionalAttributesHeaders[] = $headerValue;
    }
    $attributeKeys = $altAttributeKeys;
}

if ($shouldShowProductFeatures) {
    $allFeatureKeys = DfTools::getFeatureKeysForShopAndLang($shop->id, $lang->id);

    if (
        is_array($shouldFeaturesBeShown)
        && count($shouldFeaturesBeShown) > 0
        && $shouldFeaturesBeShown[0] !== ''
    ) {
        $featurekeys = DfTools::getSelectedFeatures($allFeatureKeys, $shouldFeaturesBeShown);
    } else {
        $featurekeys = $allFeatureKeys;
    }
    $additionalAttributesHeaders[] = 'attributes';
}

$header = array_merge($header, $additionalAttributesHeaders);

/**
 * @author camlafit <https://github.com/camlafit>
 * Allows users to extend Doofinder feed by adding extra headers and extra rows. Example:
 *
 * public function hookActionDoofinderExtendFeed($params)
 * {
 *     $params['extra_headers'] = ['header_custom1', 'header_custom2'];
 *     $params['extra_rows'] = [
 *         [
 *             'id_product' => 1,
 *             'header_custom1' => 'value1'
 *         ],
 *         [
 *             'id_product' => 2,
 *             'header_custom2' => 'value2'
 *         ]
 *     ];
 * }
 *
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
$minPriceVariantByProductId = $shouldDisplayPrices ? DfTools::getMinVariantPrices($rows, $shouldPricesUseTaxes, $currencies, $lang->id, $shop->id) : [];
$additionalHeaders = array_merge($additionalAttributesHeaders, $extraHeader);

$dfProductBuild = new DfProductBuild($shop->id, $lang->id, $currency->id);

$csv = fopen('php://output', 'w');
foreach ($rows as $row) {
    $product = $dfProductBuild->buildProduct($row, $minPriceVariantByProductId, $additionalAttributesHeaders, $additionalHeaders);
    $product = $dfProductBuild->applySpecificTransformationsForCsv($product, $extraHeader, $header);
    fputcsv($csv, $product, DfTools::TXT_SEPARATOR);
}
fclose($csv);
