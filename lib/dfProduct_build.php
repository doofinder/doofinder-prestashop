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
if (!class_exists('dfTools')) {
    require_once dirname(__FILE__) . '/lib/dfTools.class.php';
}

class DfProductBuild
{
    public function __construct($id_shop, $id_lang, $id_currency)
    {
        $this->id_shop = $id_shop;
        $this->id_lang = $id_lang;
        $this->id_currency = $id_currency;
    }

    /**
     * Set the products to be included in the payload
     *
     * @param array Product ids
     */
    public function setProducts($array_products)
    {
        $this->products = $array_products;
    }

    public function build()
    {
        $this->assign();

        $products = $this->getProductData();

        foreach ($products as $product) {
            $payload[] = $this->buildProduct($product);
        }

        return json_encode($payload);
    }

    private function assign()
    {
        $this->attributes_shown = Configuration::get('DF_GROUP_ATTRIBUTES_SHOWN');
        $this->display_prices = Configuration::get('DF_GS_DISPLAY_PRICES');
        $this->image_size = Configuration::get('DF_GS_IMAGE_SIZE');
        $this->link = Context::getContext()->link;
        $this->link_rewrite_conf = Configuration::get('PS_REWRITING_SETTINGS');
        $this->product_variations = Configuration::get('DF_SHOW_PRODUCT_VARIATIONS');
        $this->show_product_features = Configuration::get('DF_SHOW_PRODUCT_FEATURES');
        $this->stock_management = Configuration::get('PS_STOCK_MANAGEMENT');
        $this->use_tax = Configuration::get('DF_GS_PRICES_USE_TAX');
        $this->featuresKeys = $this->getFeaturesKeys();
    }

    private function getProductData()
    {
        $products = DfTools::getAvailableProductsForLanguage(
            $this->id_lang,
            $this->id_shop,
            false,
            false,
            $this->products
        );

        return $products;
    }

    private function buildProduct($product)
    {
        $p = [];

        $p['id'] = $this->getId($product);
        $p['title'] = dfTools::cleanString($product['name']);
        $p['link'] = $this->getLink($product);
        $p['description'] = dfTools::cleanString($product['description_short']);
        $p['alternate_description'] = dfTools::cleanString($product['description']);
        $p['meta_keywords'] = dfTools::cleanString($product['meta_keywords']);
        $p['meta_title'] = dfTools::cleanString($product['meta_title']);
        $p['meta_description'] = dfTools::cleanString($product['meta_description']);
        $p['image_link'] = $this->getImageLink($product);
        $p['categories'] = dfTools::getCategoriesForProductIdAndLanguage(
            $product['id_product'],
            $this->id_lang,
            $this->id_shop,
            false
        );
        $p['availability'] = $this->getAvailability($product);
        $p['brand'] = dfTools::cleanString($product['manufacturer']);
        $p['mpn'] = dfTools::cleanString($product['mpn']);
        $p['ean13'] = dfTools::cleanString($product['ean13']);
        $p['upc'] = dfTools::cleanString($product['upc']);
        $p['reference'] = dfTools::cleanString($product['reference']);
        $p['supplier_reference'] = dfTools::cleanString($product['supplier_reference']);
        $p['extra_title_1'] = dfTools::cleanReferences($p['title']);
        $p['extra_title_2'] = dfTools::splitReferences($p['title']);
        $p['tags'] = dfTools::cleanString($product['tags']);

        if (dfTools::versionGte('1.7.0.0')) {
            $p['isbn'] = dfTools::cleanString($product['isbn']);
        }

        if ($this->display_prices) {
            $p['price'] = $this->getPrice($product);
            $p['sale_price'] = $this->getPrice($product, true);
        }

        if ($this->show_product_features) {
            $p['attributes'] = $this->getFeatures($product);
        }

        if ($this->product_variations) {
            $p['item_group_id'] = $this->getItemGroupId($product);

            $p['variation_reference'] = $product['variation_reference'];
            $p['variation_supplier_reference'] = $product['variation_supplier_reference'];
            $p['variation_mpn'] = $product['variation_mpn'];
            $p['variation_ean13'] = $product['variation_ean13'];
            $p['variation_upc'] = $product['variation_upc'];
            $p['df_group_leader'] = (!is_null($product['df_group_leader']) ? true : false);

            $attributes = $this->getAttributes($product);

            $p = array_merge($p, $attributes);
        }

        return $p;
    }

    private function getId($product)
    {
        if ($this->haveVariations($product)) {
            return 'VAR-' . $product['id_product_attribute'];
        }

        return $product['id_product'];
    }

    private function getItemGroupId($product)
    {
        if ($this->haveVariations($product)) {
            return $product['id_product'];
        }

        return '';
    }

    private function getLink($product)
    {
        if ($this->haveVariations($product)) {
            return dfTools::cleanURL(
                $this->link->getProductLink(
                    (int) $product['id_product'],
                    $product['link_rewrite'],
                    $product['cat_link_rew'],
                    $$product['ean13'],
                    $this->id_lang,
                    $this->id_shop,
                    $product['id_product_attribute'],
                    $this->link_rewrite_conf,
                    false,
                    true
                )
            );
        }

        return dfTools::cleanURL(
            $this->link->getProductLink(
                (int) $product['id_product'],
                $product['link_rewrite'],
                $product['cat_link_rew'],
                $$product['ean13'],
                $this->id_lang,
                $this->id_shop,
                0,
                $this->link_rewrite_conf
            )
        );
    }

    private function getImageLink($product)
    {
        if ($this->haveVariations($product)) {
            $id_image = dfTools::getVariationImg($product['id_product'], $product['id_product_attribute']);

            if (isset($id_image)) {
                $image_link = dfTools::cleanURL(
                    dfTools::getImageLink(
                        $product['id_product_attribute'],
                        $id_image,
                        $product['link_rewrite'],
                        $this->image_size
                    )
                );
            } else {
                $image_link = dfTools::cleanURL(
                    dfTools::getImageLink(
                        $product['id_product_attribute'],
                        $product['id_image'],
                        $product['link_rewrite'],
                        $this->image_size
                    )
                );
            }

            // For variations with no specific pictures
            if (strpos($image_link, '/-') > -1) {
                $image_link = dfTools::cleanURL(
                    dfTools::getImageLink(
                        $product['id_product'],
                        $product['id_image'],
                        $product['link_rewrite'],
                        $this->image_size
                    )
                );
            }

            return $image_link;
        }

        return dfTools::cleanURL(
            dfTools::getImageLink(
                $product['id_product'],
                $product['id_image'],
                $product['link_rewrite'],
                $this->image_size
            )
        );
    }

    private function getAvailability($product)
    {
        $available = (int) $product['available_for_order'] > 0;

        if ((int) $this->stock_management) {
            $stock = StockAvailable::getQuantityAvailableByProduct(
                $product['id_product'],
                isset($product['id_product_attribute']) ? $product['id_product_attribute'] : null,
                $this->id_shop
            );
            $allow_oosp = Product::isAvailableWhenOutOfStock($product['out_of_stock']);

            return $available && ($stock > 0 || $allow_oosp) ? 'in stock' : 'out of stock';
        } else {
            return $available ? 'in stock' : 'out of stock';
        }
    }

    private function getPrice($product, $salePrice = false)
    {
        if (!$product['show_price']) {
            return false;
        }

        if ($this->product_variations) {
            $id_product_attribute = $product['id_product_attribute'];
        } else {
            $id_product_attribute = null;
        }

        $product_price = Product::getPriceStatic(
            $product['id_product'],
            $this->use_tax,
            $id_product_attribute,
            6,
            null,
            false,
            false
        );

        if (!$salePrice) {
            return $product_price ? Tools::convertPrice($product_price, $this->id_currency) : null;
        } else {
            $onsale_price = Product::getPriceStatic(
                $product['id_product'],
                $this->use_tax,
                $id_product_attribute,
                6
            );

            return ($product_price && $onsale_price && $product_price != $onsale_price)
                ? Tools::convertPrice($onsale_price, $this->id_currency) : null;
        }
    }

    private function getFeatures($product)
    {
        $features = [];

        $keys = $this->featuresKeys;

        foreach (dfTools::getFeaturesForProduct($product['id_product'], $this->id_lang, $keys) as $key => $values) {
            foreach ($values as $value) {
                $features[$key][] = dfTools::cleanString($value);
            }
        }

        return $features;
    }

    private function getFeaturesKeys()
    {
        $cfg_features_shown = explode(',', Configuration::get('DF_FEATURES_SHOWN'));
        $all_feature_keys = dfTools::getFeatureKeysForShopAndLang($this->id_shop, $this->id_lang);

        if (isset($cfg_features_shown) && count($cfg_features_shown) > 0 && $cfg_features_shown[0] !== '') {
            return dfTools::getSelectedFeatures($all_feature_keys, $cfg_features_shown);
        } else {
            return $all_feature_keys;
        }
    }

    private function getAttributes($product)
    {
        $attributes = DfTools::getAttributesByCombination(
            $product['id_product_attribute'],
            $this->id_lang,
            $this->attributes_shown
        );

        $alt_attributes = [];

        foreach ($attributes as $attribute) {
            $alt_attributes[$this->slugify($attribute['group_name'])] = $attribute['name'];
        }

        return $alt_attributes;
    }

    private function haveVariations($product)
    {
        if ($this->product_variations) {
            if (isset($product['id_product_attribute']) && (int) $product['id_product_attribute'] > 0) {
                return true;
            }
        }

        return false;
    }

    private function slugify($text)
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
}
