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

use PrestaShop\Module\Doofinder\Feed\DfProductBuild;
use PrestaShop\Module\Doofinder\Utils\DfTools;

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

$dfProductBuild = new DfProductBuild($shop->id, $lang->id, $currency->id);

/* ---------- START SHARED CONFIG ---------- */
$currencies = $dfProductBuild->getCurrencies();
$shouldDisplayPrices = $dfProductBuild->shouldDisplayPrices();
$shouldPricesUseTaxes = $dfProductBuild->shouldUseTaxes();
$isMultipriceEnabled = $dfProductBuild->isMultipriceEnabled();
$shouldShowProductVariations = $dfProductBuild->shouldShowProductVariations();
$shouldShowProductFeatures = $dfProductBuild->shouldShowProductFeatures();
$featuresShownArray = $dfProductBuild->getFeaturesShown();
$attributesShownArray = array_filter(explode(',', $dfProductBuild->getAttributesShown()), function ($a) { return strlen(trim($a)) > 0; });
/* ---------- END SHARED CONFIG ---------- */

/* ---------- START CSV-SPECIFIC CONFIG ---------- */
$shouldLimitGroupAttributes = false;
$isDebugEnabled = DfTools::cfg($shop->id, 'DF_DEBUG');
$debug = DfTools::getBooleanFromRequest('debug');
$limit = Tools::getValue('limit', false);
$offset = Tools::getValue('offset', false);
/* ---------- END CSV-SPECIFIC CONFIG ---------- */

// To prevent printing errors or warnings that may corrupt the feed.
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

$groupAttributesSlug = [];
if (count($attributesShownArray) > 0) {
    $groupAttributes = AttributeGroup::getAttributesGroups($lang->id);
    foreach ($groupAttributes as $g) {
        if (in_array($g['id_attribute_group'], $attributesShownArray)) {
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
if ($shouldShowProductVariations) {
    $header[] = 'item_group_id';
}
$header = array_merge($header, [
    'title', 'link', 'description', 'alternate_description', 'meta_title', 'meta_description', 'image_link', 'main_category',
    'categories', 'category_merchandising', 'availability', 'brand', 'mpn', 'ean13', 'upc', 'reference',
    'supplier_reference', 'supplier_name', 'extra_title_1', 'extra_title_2', 'tags',
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

if ($shouldShowProductVariations) {
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
        is_array($featuresShownArray)
        && count($featuresShownArray) > 0
        && $featuresShownArray[0] !== ''
    ) {
        $featurekeys = DfTools::getSelectedFeatures($allFeatureKeys, $featuresShownArray);
    } else {
        $featurekeys = $allFeatureKeys;
    }
    $additionalAttributesHeaders[] = 'attributes';
}

$header = array_merge($header, $additionalAttributesHeaders);

/**
 * @author camlafit <https://github.com/camlafit>
 * Allows users to extend Doofinder feed by adding extra headers and extra rows.
 * The hook can be defined in a different custom module or directly in the theme PHP files.
 * Don't forget to register the hook via registerHook method:
 *
 * `$this->registerHook('actionDoofinderExtendFeed');`
 *
 * Example of the function:
 *
 * public function hookActionDoofinderExtendFeed($params)
 * {
 *     $params['extra_header'] = ['header_custom1', 'header_custom2'];
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
 * To add an new header, module can do an array_merge on $extraHeader
 * To add an new data to a product, the module must create a multidimensional array as this :
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
// To avoid indexation failures
$header = array_unique($header);
$additionalHeaders = array_merge($additionalAttributesHeaders, $extraHeader);

$csv = fopen('php://output', 'w');
if (!$limit || (false !== $offset && 0 === (int) $offset)) {
    fputcsv($csv, $header, DfTools::TXT_SEPARATOR);
}

$products = DfTools::getAvailableProducts($lang->id, $shouldShowProductVariations, $limit, $offset);
$products = arrayMergeByIdProduct($products, $extraRows);

foreach ($products as $product) {
    $minPriceVariant = null;
    if ($shouldShowProductVariations && $product['variant_count'] > 0) {
        $variations = DfTools::getProductVariations($product['id_product']);
        foreach ($variations as $variation) {
            $minPriceVariant = $dfProductBuild->getMinPrice($minPriceVariant, $variation);
            $builtVariation = $dfProductBuild->buildVariation($product, $variation);
            $csvVariation = $dfProductBuild->applySpecificTransformationsForCsv($builtVariation, $extraHeader, $header);
            fputcsv($csv, $csvVariation, DfTools::TXT_SEPARATOR);
        }
        $product = $dfProductBuild->buildProduct($product, $minPriceVariant, $additionalAttributesHeaders, $additionalHeaders);
    } else {
        $product = $dfProductBuild->buildProduct($product, null, $additionalAttributesHeaders, $additionalHeaders);
    }

    $product = $dfProductBuild->applySpecificTransformationsForCsv($product, $extraHeader, $header);
    fputcsv($csv, $product, DfTools::TXT_SEPARATOR);
}
fclose($csv);
