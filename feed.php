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

/**
 * Accepted parameters:
 *
 * - limit:      Max results in this request.
 * - offset:     Zero-based position to start getting results.
 * - language:   Language ISO code, like "es" or "en"
 * - currency:   Currency ISO code, like "EUR" or "GBP"
 * - taxes:      Boolean. Apply taxes to prices. Default true.
 * - prices:     Boolean. Display Prices. Default true.
 */
@set_time_limit(3600 * 2);

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');

$doofinder_hash = Configuration::get('DF_FEED_HASH');
$enable_hash = Configuration::get('DF_ENABLE_HASH', null);
$dfsec_hash = Tools::getValue('dfsec_hash');
if ($enable_hash) {
    if (!empty($doofinder_hash) && $dfsec_hash != $doofinder_hash) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        $msgError = 'Forbidden access.'
                . ' Maybe security token missed.'
                . ' Please check on your doofinder module'
                . ' configuration page the new URL'
                . ' for your feed';
        exit($msgError);
    }
}


require_once(dirname(__FILE__) . '/doofinder.php');

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

$context = Context::getContext();

$shop = new Shop((int) $context->shop->id);
if (!$shop->id) {
    die('NOT PROPERLY CONFIGURED');
}

// CONFIG
$lang = dfTools::getLanguageFromRequest();
$currency = dfTools::getCurrencyForLanguageFromRequest($lang);


$cfg_short_description = (dfTools::cfg(
    $shop->id,
    'DF_GS_DESCRIPTION_TYPE',
    Doofinder::GS_SHORT_DESCRIPTION
) == Doofinder::GS_SHORT_DESCRIPTION);

$cfg_display_prices = dfTools::getBooleanFromRequest(
    'prices',
    (bool) dfTools::cfg($shop->id, 'DF_GS_DISPLAY_PRICES', Doofinder::YES)
);
$cfg_prices_w_taxes = dfTools::getBooleanFromRequest(
    'taxes',
    (bool) dfTools::cfg($shop->id, 'DF_GS_PRICES_USE_TAX', Doofinder::YES)
);
$cfg_image_size = dfTools::cfg($shop->id, 'DF_GS_IMAGE_SIZE');
$cfg_mod_rewrite = dfTools::cfg($shop->id, 'PS_REWRITING_SETTINGS', Doofinder::YES);
$cfg_product_variations = (int) dfTools::cfg($shop->id, 'DF_SHOW_PRODUCT_VARIATIONS');
$cfg_product_features = dfTools::cfg($shop->id, 'DF_SHOW_PRODUCT_FEATURES');
$cfg_debug = dfTools::cfg($shop->id, 'DF_DEBUG');
$cfg_features_shown = explode(',', dfTools::cfg($shop->id, 'DF_FEATURES_SHOWN'));

$cfg_group_attributes_shown = explode(',', dfTools::cfg($shop->id, 'DF_GROUP_ATTRIBUTES_SHOWN'));

$limit_group_attributes = false;
if (isset($cfg_group_attributes_shown) &&
        count($cfg_group_attributes_shown) > 0 &&
        $cfg_group_attributes_shown[0] !== "") {
    $group_attributes = AttributeGroup::getAttributesGroups($lang->id);
    $group_attributes_slug = array();
    foreach ($group_attributes as $g) {
        if (in_array($g['id_attribute_group'], $cfg_group_attributes_shown)) {
            $group_attributes_slug[] = slugify($g['name']);
        }
    }
    $limit_group_attributes = true;
}



$debug = dfTools::getBooleanFromRequest('debug', false);
$limit = Tools::getValue('limit', false);
$offset = Tools::getValue('offset', false);

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

if ($cfg_debug) {
    error_log("Starting feed.\n", 3, dirname(__FILE__) . '/doofinder.log');
}


// OUTPUT
if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=500');
}

header("Content-Type:text/plain; charset=utf-8");

// HEADER
$header = array('id');
if ($cfg_product_variations == 1) {
    $header[] = 'item_group_id';
}
$header = array_merge($header, array('title', 'link', 'description', 'alternate_description',
    'meta_keywords', 'meta_title', 'meta_description', 'image_link',
    'categories', 'availability', 'brand', 'mpn',
    'extra_title_1', 'extra_title_2', 'tags'));




if ($cfg_display_prices) {
    $header[] = 'price';
    $header[] = 'sale_price';
}

if ($cfg_product_variations == 1) {
    $header[] = 'variation_reference';
    $attribute_keys = dfTools::getAttributeKeysForShopAndLang($shop->id, $lang->id);
    $alt_attribute_keys = array();
    foreach ($attribute_keys as $key) {
        $header_value = slugify($key);
        if ($limit_group_attributes && !in_array($header_value, $group_attributes_slug)) {
            continue;
        }
        $alt_attribute_keys[] = $key;
        $header[] = $header_value;
    }
    $attribute_keys = $alt_attribute_keys;
} elseif ($cfg_product_variations == 2) {
    $attr_groups = AttributeGroup::getAttributesGroups((int) Configuration::get('PS_LANG_DEFAULT'));
    foreach ($attr_groups as $a_group) {
        if ($limit_group_attributes &&
                !in_array($a_group['id_attribute_group'], $cfg_group_attributes_shown)) {
            continue;
        }
        $a_group_name = str_replace('-', '_', Tools::str2url($a_group['name']));
        $header[] = 'attributes_' . $a_group_name;
    }
}

if ($cfg_product_features) {
    $all_feature_keys = dfTools::getFeatureKeysForShopAndLang($shop->id, $lang->id);

    if (isset($cfg_features_shown) &&
        count($cfg_features_shown) > 0 &&
        $cfg_features_shown[0] !== "") {
        $feature_keys = dfTools::getSelectedFeatures($all_feature_keys, $cfg_features_shown);
    } else {
        $feature_keys = $all_feature_keys;
    }

    /* foreach($feature_keys as $key){
      $header[] = slugify($key);
      } */
    $header[] = "attributes";
}



if (!$limit || ($offset !== false && (int)$offset === 0)) {
    echo implode(TXT_SEPARATOR, $header) . PHP_EOL;
    dfTools::flush();
}

// PRODUCTS
foreach (dfTools::getAvailableProductsForLanguage($lang->id, $shop->id, $limit, $offset) as $row) {
    if ((int)$row['id_product'] > 0) {
        // ID, TITLE, LINK

        if ($cfg_product_variations == 1 &&
                isset($row['id_product_attribute']) &&
                (int)$row['id_product_attribute'] > 0) {
            // ID
            echo "VAR-" . $row['id_product_attribute'] . TXT_SEPARATOR;
            
            //ITEM-GROUP-ID
            echo $row['id_product'] . TXT_SEPARATOR;
            // TITLE
            $product_title = dfTools::cleanString($row['name']);
            echo $product_title . TXT_SEPARATOR;
            echo dfTools::cleanURL(
                $context->link->getProductLink(
                    (int)$row['id_product'],
                    $row['link_rewrite'],
                    $row['cat_link_rew'],
                    $row['ean13'],
                    $lang->id,
                    $shop->id,
                    (int)$row['id_product_attribute'],
                    $cfg_mod_rewrite
                )
            ) . TXT_SEPARATOR;
        } else {
            $eanLink = $row['ean13'];
            if ($cfg_product_variations == 2) {
                $eanLink = $row['simple_ean13'];
            }
            // ID
            echo $row['id_product'] . TXT_SEPARATOR;
            
            if ($cfg_product_variations == 1) {
                //ITEM-GROUP-ID
                echo "0" . TXT_SEPARATOR;
            }
            
            
            // TITLE
            $product_title = dfTools::cleanString($row['name']);
            echo $product_title . TXT_SEPARATOR;
            echo dfTools::cleanURL(
                $context->link->getProductLink(
                    (int)$row['id_product'],
                    $row['link_rewrite'],
                    $row['cat_link_rew'],
                    $eanLink,
                    $lang->id,
                    $shop->id,
                    0,
                    $cfg_mod_rewrite
                )
            ) . TXT_SEPARATOR;
        }

        // DESCRIPTION
        echo dfTools::cleanString(
            $row[($cfg_short_description ? 'description_short' : 'description')]
        ) . TXT_SEPARATOR;

        // ALTERNATE DESCRIPTION
        echo dfTools::cleanString(
            $row[($cfg_short_description ? 'description' : 'description_short')]
        ) . TXT_SEPARATOR;

        // META KEYWORDS
        echo dfTools::cleanString($row['meta_keywords']) . TXT_SEPARATOR;

        // META TITLE
        echo dfTools::cleanString($row['meta_title']) . TXT_SEPARATOR;

        // META DESCRIPTION
        echo dfTools::cleanString($row['meta_description']) . TXT_SEPARATOR;

        // IMAGE LINK

        if ($cfg_product_variations == 1 && isset($row['id_product_attribute']) &&
                (int)$row['id_product_attribute'] > 0) {
            $cover = Product::getCover($row['id_product_attribute']);
            $id_image = dfTools::getVariationImg(
                $row['id_product'],
                $row['id_product_attribute']
            );

            if (isset($id_image)) {
                $image_link = dfTools::cleanURL(
                    dfTools::getImageLink(
                        $row['id_product_attribute'],
                        $id_image,
                        $row['link_rewrite'],
                        $cfg_image_size
                    )
                );
            } else {
                $image_link = dfTools::cleanURL(
                    dfTools::getImageLink(
                        $row['id_product_attribute'],
                        $row['id_image'],
                        $row['link_rewrite'],
                        $cfg_image_size
                    )
                );
            }

            // For variations with no specific pictures
            if (strpos($image_link, "/-") > -1) {
                $image_link = dfTools::cleanURL(
                    dfTools::getImageLink(
                        $row['id_product'],
                        $row['id_image'],
                        $row['link_rewrite'],
                        $cfg_image_size
                    )
                );
            }

            echo $image_link . TXT_SEPARATOR;
        } else {
            echo dfTools::cleanURL(
                dfTools::getImageLink(
                    $row['id_product'],
                    $row['id_image'],
                    $row['link_rewrite'],
                    $cfg_image_size
                )
            ) . TXT_SEPARATOR;
        }

        // PRODUCT CATEGORIES
        echo dfTools::getCategoriesForProductIdAndLanguage(
            $row['id_product'],
            $lang->id,
            $shop->id
        ) . TXT_SEPARATOR;

        // AVAILABILITY
        $available = (int)$row['available_for_order'] > 0;

        if ((int)dfTools::cfg($shop->id, 'PS_STOCK_MANAGEMENT')) {
            $stock = StockAvailable::getQuantityAvailableByProduct(
                $row['id_product'],
                (isset($row['id_product_attribute'])?$row['id_product_attribute']:null),
                $shop->id
            );
            $allow_oosp = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
            echo ($available && ($stock > 0 || $allow_oosp) ? 'in stock' : 'out of stock') . TXT_SEPARATOR;
        } else {
            echo ($available ? 'in stock' : 'out of stock') . TXT_SEPARATOR;
        }


        // BRAND
        echo dfTools::cleanString($row['manufacturer']) . TXT_SEPARATOR;

        // MPN
        echo dfTools::cleanString($row['mpn']) . TXT_SEPARATOR;

        // EXTRA_TITLE_1
        echo dfTools::cleanReferences($product_title) . TXT_SEPARATOR;

        // EXTRA_TITLE_2
        echo dfTools::splitReferences($product_title) . TXT_SEPARATOR;

        // TAGS
        echo dfTools::cleanString($row['tags']);

        // PRODUCT PRICE & ON SALE PRICE
        if ($cfg_display_prices && $cfg_product_variations !== 1) {
            echo TXT_SEPARATOR;

            $product_price = Product::getPriceStatic(
                $row['id_product'],
                $cfg_prices_w_taxes,
                null,
                6,
                null,
                false,
                false
            );
            $onsale_price = Product::getPriceStatic(
                $row['id_product'],
                $cfg_prices_w_taxes,
                null,
                6
            );
            if (!$row['show_price']) {
                $product_price = false;
                $onsale_price = false;
            }
            echo ($product_price ? Tools::convertPrice(
                $product_price,
                $currency
            ) : "") . TXT_SEPARATOR;
            echo (($product_price && $onsale_price && $product_price != $onsale_price)
                    ? Tools::convertPrice($onsale_price, $currency) : "");
        } elseif ($cfg_display_prices && $cfg_product_variations == 1) {
            echo TXT_SEPARATOR;
            $product_price = Product::getPriceStatic(
                $row['id_product'],
                $cfg_prices_w_taxes,
                $row['id_product_attribute'],
                6,
                null,
                false,
                false
            );
            $onsale_price = Product::getPriceStatic(
                $row['id_product'],
                $cfg_prices_w_taxes,
                $row['id_product_attribute'],
                6
            );
            if (!$row['show_price']) {
                $product_price = false;
                $onsale_price = false;
            }
            echo ($product_price ? Tools::convertPrice($product_price, $currency) : "") . TXT_SEPARATOR;
            echo (($product_price && $onsale_price && $product_price != $onsale_price) ?
                Tools::convertPrice($onsale_price, $currency) : "");
        }

        if ($cfg_product_variations == 1) {
            echo TXT_SEPARATOR;
            echo $row['variation_reference'];
            $variation_attributes = dfTools::getAttributesForProductVariation(
                $row['id_product_attribute'],
                $lang->id,
                $attribute_keys
            );
            foreach ($variation_attributes as $attribute) {
                echo TXT_SEPARATOR . dfTools::cleanString($attribute);
            }
        } elseif ($cfg_product_variations == 2) {
            foreach ($attr_groups as $a_group) {
                if ($limit_group_attributes && !in_array($a_group['id_attribute_group'], $cfg_group_attributes_shown)) {
                    continue;
                }
                $a_group_name = str_replace('-', '_', Tools::str2url($a_group['name']));
                if (isset($row['attributes_' . $a_group_name])) {
                    echo TXT_SEPARATOR . dfTools::cleanString($row['attributes_' . $a_group_name]);
                } else {
                    echo TXT_SEPARATOR;
                }
            }
        }

        if ($cfg_product_features) {
            echo TXT_SEPARATOR;
            foreach (dfTools::getFeaturesForProduct($row['id_product'], $lang->id, $feature_keys) as $key => $value) {
                echo slugify($key) . "=" . dfTools::cleanString($value) . "/";
            }
        }

        echo PHP_EOL;
        dfTools::flush();
    }
}
