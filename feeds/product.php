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
 * Global storage for all active measurements.
 *   - $__metrics_data[$label] holds ['time' => float, 'memory' => int, 'peak' => int]
 *   - $__metrics_stack is an indexed array of labels, used to pop the most recent.
 */
$__metrics_data  = [];
$__metrics_stack = [];

/**
 * Begin measuring time & memory.
 *
 * @param string|null $label  Optional: a custom label for this measurement.
 *                            If you pass null (or omit), a unique label is generated.
 * @return string             The label that was used.  (Use the same label in stop_metrics() if you want.)
 */
function start_metrics(string $label = null): string
{
    global $__metrics_data, $__metrics_stack;

    // 1) If no label provided, generate one
    if ($label === null) {
        // uniqid('m_', true) gives something like "m_5cd1e4d3a1f71.12345600"
        $label = uniqid('m_', true);
    }

    // 2) Warn if the same label is already running
    if (isset($__metrics_data[$label])) {
        trigger_error("start_metrics(): metrics with label '$label' already started.", E_USER_WARNING);
    }

    // 3) Capture "before" values
    $t0 = microtime(true);
    $m0 = memory_get_usage();
    $p0 = memory_get_peak_usage();

    // 4) Store them under $__metrics_data
    $__metrics_data[$label] = [
        'time'   => $t0,
        'memory' => $m0,
        'peak'   => $p0,
    ];

    // 5) Push this label onto the stack (so stop_metrics() with no args can pop it)
    $__metrics_stack[] = $label;

    return $label;
}

/**
 * Stop measuring time & memory and compute deltas.
 *
 * @param string|null $label  Optional: the label you used in start_metrics().
 *                            If you pass null, this function will pop the most recently started label.
 * @return array|null         On success, returns an array:
 *                                [
 *                                  'label'       => (string) the label used,
 *                                  'time'        => (float) elapsed seconds,
 *                                  'memory'      => (int)   bytes of memory allocated,
 *                                  'peak_memory' => (int)   extra peak memory (bytes)
 *                                ]
 *                            On failure (no matching start), returns null and emits a warning.
 */
function stop_metrics(string $label = null): ?array
{
    global $__metrics_data, $__metrics_stack;

    // 1) If no label provided, pop the most recent one
    if ($label === null) {
        if (empty($__metrics_stack)) {
            trigger_error("stop_metrics(): no active measurements to stop.", E_USER_WARNING);
            return null;
        }
        $label = array_pop($__metrics_stack);
    } else {
        // 2) If a label was provided, remove it from the stack if present
        $idx = array_search($label, $__metrics_stack, true);
        if ($idx !== false) {
            array_splice($__metrics_stack, $idx, 1);
        }
    }

    // 3) Check that we had a start for this label
    if (!isset($__metrics_data[$label])) {
        trigger_error("stop_metrics(): no metrics found for label '$label'.", E_USER_WARNING);
        return null;
    }

    // 4) Grab the "before" values
    $start = $__metrics_data[$label];
    unset($__metrics_data[$label]);

    // 5) Capture "after" values
    $t1 = microtime(true);
    $m1 = memory_get_usage();
    $p1 = memory_get_peak_usage();

    // 6) Compute deltas
    $elapsed   = $t1 - $start['time'];
    $memDelta  = $m1 - $start['memory'];
    $peakDelta = $p1 - $start['peak'];

    return [
        'label'       => $label,
        'time'        => $elapsed,
        'memory'      => $memDelta,
        'peak_memory' => $peakDelta,
    ];
}

/**
 * Print a human‐readable summary of the metrics array returned by stop_metrics().
 *
 * @param array|null $metrics
 *     Example of $metrics:
 *       [
 *         'label'       => 'my_func',
 *         'time'        => 0.123456,
 *         'memory'      => 1048576,     // bytes
 *         'peak_memory' => 2097152,     // bytes
 *       ]
 *
 * If $metrics is null or not an array, prints a warning.
 */
function print_metrics(?array $metrics): void
{
    if (!is_array($metrics)) {
        echo "print_metrics(): no metrics to display.\n";
        return;
    }

    $label  = $metrics['label']       ?? '';
    $time   = $metrics['time']        ?? 0.0;
    $memory = $metrics['memory']      ?? 0;
    $peak   = $metrics['peak_memory'] ?? 0;

    if ($label !== '') {
        echo "Metrics for '{$label}':\n";
    } else {
        echo "Metrics:\n";
    }
    echo "- Elapsed time:         " . round($time, 6)   . " seconds\n";
    echo "- Memory used:          " . number_format($memory)  . " bytes\n";
    echo "- Peak memory increase: " . number_format($peak)    . " bytes\n";
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
$attributesShownArray = explode(',', $dfProductBuild->getAttributesShown());
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

if (
    is_array($attributesShownArray)
    && count($attributesShownArray) > 0
    && $attributesShownArray[0] !== ''
) {
    $groupAttributes = AttributeGroup::getAttributesGroups($lang->id);
    $groupAttributesSlug = [];
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
// To avoid indexation failures
$header = array_unique($header);
$additionalHeaders = array_merge($additionalAttributesHeaders, $extraHeader);

$csv = fopen('php://output', 'w');
if (!$limit || (false !== $offset && 0 === (int) $offset)) {
    fputcsv($csv, $header, DfTools::TXT_SEPARATOR);
}
$startLabel = start_metrics("full");
if (isset($_GET["old"]) && $_GET["old"] == 1) {

    // PRODUCTS
    $rows = DfTools::getAvailableProductsForLanguage($lang->id, $shop->id, $limit, $offset);

    $rows = arrayMergeByIdProduct($rows, $extraRows);

    // In case there is no need to display prices, avoid calculating the mins by variant
    $minPriceVariantByProductId = ($shouldShowProductVariations && $shouldDisplayPrices) ? DfTools::getMinVariantPrices($rows, $shouldPricesUseTaxes, $currencies, $lang->id, $shop->id) : [];


    foreach ($rows as $row) {
        $product = $dfProductBuild->buildProduct($row, $minPriceVariantByProductId, $additionalAttributesHeaders, $additionalHeaders);
        $product = $dfProductBuild->applySpecificTransformationsForCsv($product, $extraHeader, $header);
        fputcsv($csv, $product, DfTools::TXT_SEPARATOR);
    }
} else {



    $products = DfTools::getAvailableProductsForLanguageV2($lang->id, $shouldShowProductVariations, $limit, $offset);
    $empty_line = array_fill_keys($header, null);
    foreach ($products as $product) {
        $minProductPrices = [];
        if ($shouldShowProductVariations && $product['variant_count'] > 0) {
            $variations = DfTools::getProductVariationsV2($product['id_product']);
            foreach ($variations as $variation) {
                if ($shouldDisplayPrices) {
                    $variantPrices = DfTools::getVariantPrices($variation['id_product'], $variation['id_product_attribute'], $shouldPricesUseTaxes, $currencies);
                    if (!isset($minProductPrices['onsale_price']) || $variantPrices['onsale_price'] < $minProductPrices['onsale_price']) {
                        $minProductPrices = $variantPrices;
                    }
                }
                $expanded_variation = array_merge($product, $variation);
                $built_variation = $dfProductBuild->buildProduct($expanded_variation, [], $additionalAttributesHeaders, $additionalHeaders);
                $csv_product = $dfProductBuild->applySpecificTransformationsForCsv($built_variation, $extraHeader, $header);
                fputcsv($csv, $csv_product, DfTools::TXT_SEPARATOR);
            }
            $product = $dfProductBuild->buildProduct($product, [$product['id_product'] => $minProductPrices], $additionalAttributesHeaders, $additionalHeaders);
        } else {
            $product = $dfProductBuild->buildProduct($product, [], $additionalAttributesHeaders, $additionalHeaders);
        }

        $product = $dfProductBuild->applySpecificTransformationsForCsv($product, $extraHeader, $header);
        fputcsv($csv, $product, DfTools::TXT_SEPARATOR);
    }
}
$metrics = stop_metrics("full");
if ($_GET["debug"] == 1) {
    print_metrics($metrics);
}
fclose($csv);
