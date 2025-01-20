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

$root_path = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$config_file_path = $root_path . '/config/config.inc.php';
if (@file_exists($config_file_path)) {
    require_once $config_file_path;
    require_once $root_path . '/init.php';
    require_once dirname($_SERVER['SCRIPT_FILENAME']) . '/doofinder.php';
} else {
    require_once dirname(__FILE__) . '/../../../config/config.inc.php';
    require_once dirname(__FILE__) . '/../../../init.php';
    require_once dirname(__FILE__) . '/../doofinder.php';
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
    'title', 'link', 'description', 'alternate_description',
    'meta_keywords', 'meta_title', 'meta_description', 'image_link', 'main_category',
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
        $header[] = $header_value;
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
    $header[] = 'attributes';
}

/**
 * @author camlafit <https://github.com/camlafit>
 * Extend doofinder feed
 *
 * To add an new header, module can do an array_merge on $extra_header
 * To add an new data to a product, module must create a multidemensionnal array as this :
 * array(
 *   index => array(
 *    'id_product' => value,
 *    'new_header_column_name' => 'value related to the new column'
 *   ),
 *   [...]
 * )
 * As each module can extend $extra_header and $extra_rows don't forget to merge them
 */
$extra_header = [];
$extra_rows = [];
Hook::exec('actionDoofinderExtendFeed', [
    'extra_header' => &$extra_header,
    'extra_rows' => &$extra_rows,
    'id_lang' => $lang->id,
    'id_shop' => $shop->id,
    'limit' => $limit,
    'offset' => $offset,
]);

$header = array_merge($header, $extra_header);

if (!$limit || ($offset !== false && (int) $offset === 0)) {
    echo implode(DfTools::TXT_SEPARATOR, $header) . PHP_EOL;
    DfTools::flush();
}

// PRODUCTS
$rows = DfTools::getAvailableProductsForLanguage($lang->id, $shop->id, $limit, $offset);

$rows = arrayMergeByIdProduct($rows, $extra_rows);

// In case there is no need to display prices, avoid calculating the mins by variant
$min_price_variant_by_product_id = $cfg_display_prices ? DfTools::getMinVariantPrices($rows, $cfg_prices_w_taxes, $currencies) : [];

foreach ($rows as $row) {
    $product_id = $row['id_product'];
    $variant_id = $row['id_product_attribute'];
    $product_price = DfTools::getPrice($product_id, $cfg_prices_w_taxes, $variant_id);
    $onsale_price = DfTools::getOnsalePrice($product_id, $cfg_prices_w_taxes, $variant_id);
    $multiprice = DfTools::getFormattedMultiprice($product_id, $cfg_prices_w_taxes, $currencies, $variant_id);

    if ((int) $row['id_product'] > 0) {
        // ID, TITLE, LINK

        if (
            $cfg_product_variations == 1
            && isset($row['id_product_attribute'])
            && (int) $row['id_product_attribute'] > 0
        ) {
            // ID
            echo 'VAR-' . $row['id_product_attribute'] . DfTools::TXT_SEPARATOR;

            // ITEM-GROUP-ID
            echo $row['id_product'] . DfTools::TXT_SEPARATOR;
            // TITLE
            $product_title = DfTools::cleanString($row['name']);
            echo $product_title . DfTools::TXT_SEPARATOR;
            echo DfTools::cleanURL(
                $context->link->getProductLink(
                    (int) $row['id_product'],
                    $row['link_rewrite'],
                    $row['cat_link_rew'],
                    $row['ean13'],
                    $lang->id,
                    $shop->id,
                    (int) $row['id_product_attribute'],
                    $cfg_mod_rewrite,
                    false,
                    true
                )
            ) . DfTools::TXT_SEPARATOR;
        } else {
            $eanLink = $row['ean13'];
            // ID
            echo $row['id_product'] . DfTools::TXT_SEPARATOR;

            if ($cfg_product_variations == 1) {
                // ITEM-GROUP-ID
                echo '' . DfTools::TXT_SEPARATOR;
            }

            // TITLE
            $product_title = DfTools::cleanString($row['name']);
            echo $product_title . DfTools::TXT_SEPARATOR;

            $parent_url = DfTools::cleanURL(
                $context->link->getProductLink(
                    (int) $row['id_product'],
                    $row['link_rewrite'],
                    $row['cat_link_rew'],
                    $eanLink,
                    $lang->id,
                    $shop->id,
                    0,
                    $cfg_mod_rewrite
                )
            );

            if (key_exists($product_id, $min_price_variant_by_product_id) && !empty($min_price_variant_by_product_id[$product_id])) {
                $min_variant = $min_price_variant_by_product_id[$product_id];
                echo $min_variant['onsale_price'] < $onsale_price ? $min_price_variant_by_product_id[$product_id]['link'] : $parent_url;
            } else {
                echo $parent_url;
            }
            echo DfTools::TXT_SEPARATOR;
        }

        // DESCRIPTION
        echo DfTools::cleanString(
            $row[$cfg_short_description ? 'description_short' : 'description']
        ) . DfTools::TXT_SEPARATOR;

        // ALTERNATE DESCRIPTION
        echo DfTools::cleanString(
            $row[$cfg_short_description ? 'description' : 'description_short']
        ) . DfTools::TXT_SEPARATOR;

        // META KEYWORDS
        echo DfTools::cleanString($row['meta_keywords']) . DfTools::TXT_SEPARATOR;

        // META TITLE
        echo DfTools::cleanString($row['meta_title']) . DfTools::TXT_SEPARATOR;

        // META DESCRIPTION
        echo DfTools::cleanString($row['meta_description']) . DfTools::TXT_SEPARATOR;

        // IMAGE LINK

        if (
            $cfg_product_variations == 1 && isset($row['id_product_attribute'])
            && (int) $row['id_product_attribute'] > 0
        ) {
            $cover = Product::getCover($row['id_product_attribute']);
            $id_image = DfTools::getVariationImg(
                $row['id_product'],
                $row['id_product_attribute']
            );

            if (!empty($id_image)) {
                $image_link = DfTools::cleanURL(
                    DfTools::getImageLink(
                        $row['id_product_attribute'],
                        $id_image,
                        $row['link_rewrite'],
                        $cfg_image_size
                    )
                );
            } else {
                $image_link = DfTools::cleanURL(
                    DfTools::getImageLink(
                        $row['id_product_attribute'],
                        $row['id_image'],
                        $row['link_rewrite'],
                        $cfg_image_size
                    )
                );
            }

            // For variations with no specific pictures
            if (strpos($image_link, '/-') > -1) {
                $image_link = DfTools::cleanURL(
                    DfTools::getImageLink(
                        $row['id_product'],
                        $row['id_image'],
                        $row['link_rewrite'],
                        $cfg_image_size
                    )
                );
            }

            echo $image_link . DfTools::TXT_SEPARATOR;
        } else {
            echo DfTools::cleanURL(
                DfTools::getImageLink(
                    $row['id_product'],
                    $row['id_image'],
                    $row['link_rewrite'],
                    $cfg_image_size
                )
            ) . DfTools::TXT_SEPARATOR;
        }

        // MAIN CATEGORY
        echo DfTools::cleanString($row['main_category']) . DfTools::TXT_SEPARATOR;

        // PRODUCT CATEGORIES
        echo DfTools::getCategoriesForProductIdAndLanguage(
            $row['id_product'],
            $lang->id,
            $shop->id
        ) . DfTools::TXT_SEPARATOR;

        // AVAILABILITY
        $available = (int) $row['available_for_order'] > 0;

        if ((int) DfTools::cfg($shop->id, 'PS_STOCK_MANAGEMENT')) {
            $stock = StockAvailable::getQuantityAvailableByProduct(
                $row['id_product'],
                isset($row['id_product_attribute']) ? $row['id_product_attribute'] : null,
                $shop->id
            );
            $allow_oosp = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
            echo ($available && ($stock > 0 || $allow_oosp) ? 'in stock' : 'out of stock') . DfTools::TXT_SEPARATOR;
        } else {
            echo ($available ? 'in stock' : 'out of stock') . DfTools::TXT_SEPARATOR;
        }

        // BRAND
        echo DfTools::cleanString($row['manufacturer']) . DfTools::TXT_SEPARATOR;

        // MPN
        echo DfTools::cleanString($row['mpn']) . DfTools::TXT_SEPARATOR;

        // EAN13
        echo DfTools::cleanString($row['ean13']) . DfTools::TXT_SEPARATOR;

        // UPC
        echo DfTools::cleanString($row['upc']) . DfTools::TXT_SEPARATOR;

        // REFERENCE
        echo DfTools::cleanString($row['reference']) . DfTools::TXT_SEPARATOR;

        // SUPPLIER_REFERENCE
        echo DfTools::cleanString($row['supplier_reference']) . DfTools::TXT_SEPARATOR;

        // EXTRA_TITLE_1
        echo $product_title . DfTools::TXT_SEPARATOR;

        // EXTRA_TITLE_2
        echo DfTools::splitReferences($product_title) . DfTools::TXT_SEPARATOR;

        // TAGS
        echo DfTools::escapeSlashes(DfTools::cleanString(DfTools::escapeSlashes($row['tags'])));

        // ISBN
        if (DfTools::versionGte('1.7.0.0')) {
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['isbn']);
        }

        // STOCK_QUANTITY
        echo DfTools::TXT_SEPARATOR;
        echo DfTools::cleanString($row['stock_quantity']);

        // PRODUCT PRICE & ON SALE PRICE

        if ($cfg_display_prices && $cfg_product_variations !== 1) {
            echo DfTools::TXT_SEPARATOR;

            $product_price = DfTools::getPrice($product_id, $cfg_prices_w_taxes);
            $onsale_price = DfTools::getOnsalePrice($product_id, $cfg_prices_w_taxes);
            $multiprice = DfTools::getFormattedMultiprice($product_id, $cfg_prices_w_taxes, $currencies);

            if ($row['show_price']) {
                echo Tools::convertPrice($product_price, $currency);
                echo DfTools::TXT_SEPARATOR;
                echo $product_price != $onsale_price ? Tools::convertPrice($onsale_price, $currency) : '';
                echo DfTools::TXT_SEPARATOR;
                echo $multiprice_enabled && $multiprice ? $multiprice : '';
            } else {
                echo DfTools::TXT_SEPARATOR . DfTools::TXT_SEPARATOR;
            }
        } elseif ($cfg_display_prices && $cfg_product_variations == 1) {
            echo DfTools::TXT_SEPARATOR;
            // The parent product should have as price the lowest ones of the
            // variants (combinations) if there are any
            if (DfTools::isParent($row) && array_key_exists($product_id, $min_price_variant_by_product_id)) {
                $min_variant = $min_price_variant_by_product_id[$product_id];

                if (!is_null($min_variant['onsale_price']) && !is_null($min_variant['price']) && $min_variant['onsale_price'] < $onsale_price) {
                    $product_price = $min_variant['price'];
                    $onsale_price = $min_variant['onsale_price'];
                    $multiprice = $min_variant['multiprice'];
                }
            }

            if ($row['show_price']) {
                echo Tools::convertPrice($product_price, $currency);
                echo DfTools::TXT_SEPARATOR;
                echo $product_price != $onsale_price ? Tools::convertPrice($onsale_price, $currency) : '';
                echo DfTools::TXT_SEPARATOR;
                echo $multiprice_enabled && $multiprice ? $multiprice : '';
            } else {
                echo DfTools::TXT_SEPARATOR . DfTools::TXT_SEPARATOR;
            }
        }

        if ($cfg_product_variations == 1) {
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['variation_reference']);
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['variation_supplier_reference']);
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['variation_mpn']);
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['variation_ean13']);
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['variation_upc']);
            echo DfTools::TXT_SEPARATOR;
            echo DfTools::cleanString($row['df_group_leader']);
            $variation_attributes = DfTools::getAttributesForProductVariation(
                $row['id_product_attribute'],
                $lang->id,
                $attribute_keys
            );
            echo DfTools::TXT_SEPARATOR;
            if (DfTools::hasAttributes($row['id_product']) && !$row['id_product_attribute']) {
                $product_attributes = DfTools::hasProductAttributes($row['id_product'], DfTools::cfg($shop->id, 'DF_GROUP_ATTRIBUTES_SHOWN'));
                if ($product_attributes) {
                    $attributes = DfTools::getAttributesName($product_attributes, $lang->id);

                    if (is_array($attributes)) {
                        $variants_keys = array_column($attributes, 'name');
                        echo implode('%%', array_map('slugify', $variants_keys));
                    } else {
                        echo '';
                    }
                } else {
                    echo '';
                }
            }
            foreach ($variation_attributes as $attribute) {
                echo DfTools::TXT_SEPARATOR . str_replace('/', '//', DfTools::cleanString($attribute));
            }
        }

        if ($cfg_product_features) {
            echo DfTools::TXT_SEPARATOR;
            foreach (DfTools::getFeaturesForProduct($row['id_product'], $lang->id, $feature_keys) as $key => $values) {
                foreach ($values as $index => $value) {
                    echo slugify($key) . '=';
                    echo str_replace('/', '\/', DfTools::cleanString($value)) . '/';
                }
            }
        }

        /*
         * @author camlafit <https://github.com/camlafit>
         */
        foreach ($extra_header as $extra) {
            echo DfTools::TXT_SEPARATOR;
            echo isset($row[$extra]) ? DfTools::cleanString($row[$extra]) : '';
        }

        echo PHP_EOL;
        DfTools::flush();
    }
}
