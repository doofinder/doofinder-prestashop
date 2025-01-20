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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DfProductBuild
{
    private $idShop;
    private $idLang;
    private $idCurrency;
    private $currencies;
    private $products;
    private $attributesShown;
    private $displayPrices;
    private $imageSize;
    private $link;
    private $linkRewriteConf;
    private $productVariations;
    private $showProductFeatures;
    private $stockManagement;
    private $useTax;
    private $multipriceEnabled;
    private $featuresKeys;

    public function __construct($idShop, $idLang, $idCurrency)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
        $this->idCurrency = $idCurrency;
        $this->currencies = \Currency::getCurrenciesByIdShop($idShop);
    }

    /**
     * Set the products to be included in the payload
     *
     * @param array $arrayProducts Product ids
     */
    public function setProducts($arrayProducts)
    {
        $this->products = $arrayProducts;
    }

    public function build()
    {
        $this->assign();

        $payload = [];

        $products = $this->getProductData();

        foreach ($products as $product) {
            $payload[] = $this->buildProduct($product);
        }

        return json_encode($payload);
    }

    private function assign()
    {
        $this->attributesShown = \Configuration::get('DF_GROUP_ATTRIBUTES_SHOWN');
        $this->displayPrices = \Configuration::get('DF_GS_DISPLAY_PRICES');
        $this->imageSize = \Configuration::get('DF_GS_IMAGE_SIZE');
        $this->link = \Context::getContext()->link;
        $this->linkRewriteConf = \Configuration::get('PS_REWRITING_SETTINGS');
        $this->productVariations = \Configuration::get('DF_SHOW_PRODUCT_VARIATIONS');
        $this->showProductFeatures = \Configuration::get('DF_SHOW_PRODUCT_FEATURES');
        $this->stockManagement = \Configuration::get('PS_STOCK_MANAGEMENT');
        $this->useTax = \Configuration::get('DF_GS_PRICES_USE_TAX') || DoofinderConstants::NO;
        $this->multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');
        $this->featuresKeys = $this->getFeaturesKeys();
    }

    private function getProductData()
    {
        $products = DfTools::getAvailableProductsForLanguage(
            $this->idLang,
            $this->idShop,
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
        $p['title'] = DfTools::cleanString($product['name']);
        $p['link'] = $this->getLink($product);
        $p['description'] = DfTools::cleanString($product['description_short']);
        $p['alternate_description'] = DfTools::cleanString($product['description']);
        $p['meta_keywords'] = DfTools::cleanString($product['meta_keywords']);
        $p['meta_title'] = DfTools::cleanString($product['meta_title']);
        $p['meta_description'] = DfTools::cleanString($product['meta_description']);
        $p['image_link'] = $this->getImageLink($product);
        $p['main_category'] = DfTools::cleanString($product['main_category']);
        $p['categories'] = DfTools::getCategoriesForProductIdAndLanguage(
            $product['id_product'],
            $this->idLang,
            $this->idShop,
            false
        );
        $p['availability'] = $this->getAvailability($product);
        $p['brand'] = DfTools::cleanString($product['manufacturer']);
        $p['mpn'] = DfTools::cleanString($product['mpn']);
        $p['ean13'] = DfTools::cleanString($product['ean13']);
        $p['upc'] = DfTools::cleanString($product['upc']);
        $p['reference'] = DfTools::cleanString($product['reference']);
        $p['supplier_reference'] = DfTools::cleanString($product['supplier_reference']);
        $p['extra_title_1'] = $p['title'];
        $p['extra_title_2'] = DfTools::splitReferences($p['title']);
        $p['tags'] = DfTools::cleanString($product['tags']);
        $p['stock_quantity'] = DfTools::cleanString($product['stock_quantity']);

        if (DfTools::versionGte('1.7.0.0')) {
            $p['isbn'] = DfTools::cleanString($product['isbn']);
        }

        if ($this->displayPrices) {
            $p['price'] = $this->getPrice($product);
            $p['sale_price'] = $this->getPrice($product, true);

            if ($this->multipriceEnabled) {
                $p['df_multiprice'] = $this->getMultiprice($product);
            }
        }

        if ($this->showProductFeatures) {
            $p = array_merge($p, $this->getFeatures($product));
        }

        if ($this->productVariations) {
            $p['item_group_id'] = $this->getItemGroupId($product);
            $p['group_id'] = $this->getItemGroupId($product);

            $p['variation_reference'] = $product['variation_reference'];
            $p['variation_supplier_reference'] = $product['variation_supplier_reference'];
            $p['variation_mpn'] = $product['variation_mpn'];
            $p['variation_ean13'] = $product['variation_ean13'];
            $p['variation_upc'] = $product['variation_upc'];
            $p['df_group_leader'] = (!is_null($product['df_group_leader'] && $product['df_group_leader']) ? true : false);

            $attributes = $this->getAttributes($product);

            $p = array_merge($p, $attributes);

            $p['df_variants_information'] = $this->getVariantsInformation($product);
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
            return DfTools::cleanURL(
                $this->link->getProductLink(
                    (int) $product['id_product'],
                    $product['link_rewrite'],
                    $product['cat_link_rew'],
                    $product['ean13'],
                    $this->idLang,
                    $this->idShop,
                    $product['id_product_attribute'],
                    $this->linkRewriteConf,
                    false,
                    true
                )
            );
        }

        return DfTools::cleanURL(
            $this->link->getProductLink(
                (int) $product['id_product'],
                $product['link_rewrite'],
                $product['cat_link_rew'],
                $product['ean13'],
                $this->idLang,
                $this->idShop,
                0,
                $this->linkRewriteConf
            )
        );
    }

    private function getImageLink($product)
    {
        if ($this->haveVariations($product)) {
            $idImage = DfTools::getVariationImg($product['id_product'], $product['id_product_attribute']);

            if (!empty($idImage)) {
                $imageLink = DfTools::cleanURL(
                    DfTools::getImageLink(
                        $product['id_product_attribute'],
                        $idImage,
                        $product['link_rewrite'],
                        $this->imageSize
                    )
                );
            } else {
                $imageLink = DfTools::cleanURL(
                    DfTools::getImageLink(
                        $product['id_product_attribute'],
                        $product['id_image'],
                        $product['link_rewrite'],
                        $this->imageSize
                    )
                );
            }

            // For variations with no specific pictures
            if (strpos($imageLink, '/-') > -1) {
                $imageLink = DfTools::cleanURL(
                    DfTools::getImageLink(
                        $product['id_product'],
                        $product['id_image'],
                        $product['link_rewrite'],
                        $this->imageSize
                    )
                );
            }

            return $imageLink;
        }

        return DfTools::cleanURL(
            DfTools::getImageLink(
                $product['id_product'],
                $product['id_image'],
                $product['link_rewrite'],
                $this->imageSize
            )
        );
    }

    private function getAvailability($product)
    {
        $available = (int) $product['available_for_order'] > 0;

        if ((int) $this->stockManagement) {
            $stock = \StockAvailable::getQuantityAvailableByProduct(
                $product['id_product'],
                isset($product['id_product_attribute']) ? $product['id_product_attribute'] : null,
                $this->idShop
            );
            $allowOosp = \Product::isAvailableWhenOutOfStock($product['out_of_stock']);

            return $available && ($stock > 0 || $allowOosp) ? 'in stock' : 'out of stock';
        } else {
            return $available ? 'in stock' : 'out of stock';
        }
    }

    private function getPrice($product, $salePrice = false)
    {
        if (!$product['show_price']) {
            return false;
        }

        if ($this->productVariations) {
            $idProductAttribute = $product['id_product_attribute'];
        } else {
            $idProductAttribute = null;
        }

        $productPrice = \Product::getPriceStatic(
            $product['id_product'],
            $this->useTax,
            $idProductAttribute,
            6,
            null,
            false,
            false
        );

        if (!$salePrice) {
            return $productPrice ? \Tools::convertPrice($productPrice, $this->idCurrency) : null;
        } else {
            $onsalePrice = \Product::getPriceStatic(
                $product['id_product'],
                $this->useTax,
                $idProductAttribute,
                6
            );

            return ($productPrice && $onsalePrice && $productPrice != $onsalePrice)
                ? \Tools::convertPrice($onsalePrice, $this->idCurrency) : null;
        }
    }

    private function getMultiprice($product)
    {
        $productId = $product['id_product'];
        $idProductAttribute = $this->productVariations ? $product['id_product_attribute'] : null;

        return DfTools::getMultiprice($productId, $this->useTax, $this->currencies, $idProductAttribute);
    }

    private function getFeatures($product)
    {
        $features = [];

        $keys = $this->featuresKeys;

        foreach (DfTools::getFeaturesForProduct($product['id_product'], $this->idLang, $keys) as $key => $values) {
            if (count($values) > 1) {
                foreach ($values as $value) {
                    $features[$this->slugify($key)][] = DfTools::cleanString($value);
                }
            } else {
                $features[$this->slugify($key)] = DfTools::cleanString($values[0]);
            }
        }

        return $features;
    }

    private function getFeaturesKeys()
    {
        $cfgFeaturesShown = explode(',', \Configuration::get('DF_FEATURES_SHOWN'));
        $allFeatureKeys = DfTools::getFeatureKeysForShopAndLang($this->idShop, $this->idLang);

        if (is_array($cfgFeaturesShown) && count($cfgFeaturesShown) > 0 && $cfgFeaturesShown[0] !== '') {
            return DfTools::getSelectedFeatures($allFeatureKeys, $cfgFeaturesShown);
        } else {
            return $allFeatureKeys;
        }
    }

    private function getAttributes($product)
    {
        $attributes = DfTools::getAttributesByCombination(
            $product['id_product_attribute'],
            $this->idLang,
            $this->attributesShown
        );

        $altAttributes = [];

        foreach ($attributes as $attribute) {
            $altAttributes[$this->slugify($attribute['group_name'])] = $attribute['name'];
        }

        return $altAttributes;
    }

    private function haveVariations($product)
    {
        if ($this->productVariations) {
            if (isset($product['id_product_attribute']) && (int) $product['id_product_attribute'] > 0) {
                return true;
            }
        }

        return false;
    }

    private function getVariantsInformation($product)
    {
        if (DfTools::hasAttributes($product['id_product']) && !$product['id_product_attribute']) {
            $productAttributes = DfTools::hasProductAttributes($product['id_product'], $this->attributesShown);

            if (empty($productAttributes)) {
                return [];
            }

            $attributes = DfTools::getAttributesName($productAttributes, $this->idLang);

            $names = array_column($attributes, 'name');

            return array_map([$this, 'slugify'], $names);
        }

        return [];
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
        $text = \Tools::strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }
}
