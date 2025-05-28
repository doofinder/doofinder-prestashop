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
    private $featuresShown;
    private $featuresKeys;

    public function __construct($idShop, $idLang, $idCurrency)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
        $this->idCurrency = $idCurrency;
        $this->currencies = \Currency::getCurrenciesByIdShop($idShop);
        $this->attributesShown = DfTools::cfg($idShop, 'DF_GROUP_ATTRIBUTES_SHOWN', '');
        $this->displayPrices = (bool) DfTools::cfg($idShop, 'DF_GS_DISPLAY_PRICES', DoofinderConstants::YES);
        $this->imageSize = \Configuration::get('DF_GS_IMAGE_SIZE');
        $this->link = \Context::getContext()->link;
        $this->linkRewriteConf = \Configuration::get('PS_REWRITING_SETTINGS');
        $this->productVariations = (bool) \Configuration::get('DF_SHOW_PRODUCT_VARIATIONS');
        $this->showProductFeatures = (bool) \Configuration::get('DF_SHOW_PRODUCT_FEATURES');
        $this->stockManagement = \Configuration::get('PS_STOCK_MANAGEMENT');
        $this->useTax = (bool) DfTools::cfg($idShop, 'DF_GS_PRICES_USE_TAX', DoofinderConstants::YES);
        $this->multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');
        $this->featuresShown = explode(',', DfTools::cfg($idShop, 'DF_FEATURES_SHOWN', ''));
        $this->featuresKeys = $this->getFeaturesKeys();
    }

    /**
     * Get the list of currencies available for the shop.
     *
     * @return array
     */
    public function getCurrencies()
    {
        return $this->currencies;
    }

    /**
     * Get the configured attributes to be shown.
     *
     * @return string
     */
    public function getAttributesShown()
    {
        return $this->attributesShown;
    }

    /**
     * Check if prices should be displayed.
     *
     * @return bool
     */
    public function shouldDisplayPrices()
    {
        return $this->displayPrices;
    }

    /**
     * Get the configured image size.
     *
     * @return string
     */
    public function getImageSize()
    {
        return $this->imageSize;
    }

    /**
     * Get the configured features to be shown.
     *
     * @return array
     */
    public function getFeaturesShown()
    {
        return $this->featuresShown;
    }

    /**
     * Check if product variations should be shown.
     *
     * @return bool
     */
    public function shouldShowProductVariations()
    {
        return $this->productVariations;
    }

    /**
     * Check if product features should be shown.
     *
     * @return bool
     */
    public function shouldShowProductFeatures()
    {
        return $this->showProductFeatures;
    }

    /**
     * Check if stock management is enabled.
     *
     * @return mixed
     */
    public function getStockManagement()
    {
        return $this->stockManagement;
    }

    /**
     * Check if prices should include tax.
     *
     * @return bool
     */
    public function shouldUseTaxes()
    {
        return $this->useTax;
    }

    /**
     * Check if the multiprice feature is enabled.
     *
     * @return bool
     */
    public function isMultipriceEnabled()
    {
        return $this->multipriceEnabled;
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
        $payload = [];

        $products = $this->getProductData();

        $minPriceVariantByProductId = [];
        if ($this->productVariations) {
            $minPriceVariantByProductId = DfTools::getMinVariantPrices($products, $this->useTax, $this->currencies, $this->idLang, $this->idShop);
        }

        foreach ($products as $product) {
            $payload[] = $this->buildProduct($product, $minPriceVariantByProductId);
        }

        return json_encode($payload);
    }

    public function buildProduct($product, $minPriceVariantByProductId = [], $extraAttributesHeader = [], $extraHeaders = [])
    {
        $p = [];

        $p['id'] = $this->getId($product);
        if ($this->productVariations) {
            $p['item_group_id'] = $this->getItemGroupId($product);
        }
        $p['title'] = DfTools::cleanString($product['name']);
        $p['link'] = $this->getLink($product);
        $p['description'] = DfTools::cleanString($product['description_short']);
        $p['alternate_description'] = DfTools::cleanString($product['description']);
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
        $p['category_merchandising'] = DfTools::getCategoryLinksById(
            $product['category_ids'],
            $this->idLang,
            $this->idShop
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

        $productTags = DfTools::cleanString($product['tags']);
        $p['tags'] = $productTags;

        if (is_string($productTags)) {
            // Extra steps to avoid possible duplicates in tags
            $productTags = explode(',', $productTags);
            $productTags = array_unique($productTags);
            $p['tags'] = implode(',', $productTags);
        }

        if (DfTools::versionGte('1.7.0.0')) {
            $p['isbn'] = DfTools::cleanString($product['isbn']);
        }

        $p['stock_quantity'] = DfTools::cleanString($product['stock_quantity']);

        // Extra calculation for Pack products.
        if (class_exists('Pack')
        && method_exists('Pack', 'isPack')
        && method_exists('Pack', 'getQuantity')
        && array_key_exists('id_product_attribute', $product)
        && \Pack::isPack((int) $product['id_product'])) {
            $p['stock_quantity'] = \Pack::getQuantity($product['id_product'], $product['id_product_attribute']);
        }

        if ($this->displayPrices) {
            $p['price'] = $this->getPrice($product);
            $p['sale_price'] = $this->getPrice($product, true);

            if ($this->multipriceEnabled) {
                $p['df_multiprice'] = $this->getMultiprice($product);
            }

            if (DfTools::isParent($product) && array_key_exists($p['id'], $minPriceVariantByProductId)) {
                $minVariant = $minPriceVariantByProductId[$p['id']];
                if (
                    !is_null($minVariant['onsale_price'])
                    && !is_null($minVariant['price'])
                    && (empty($p['sale_price']) || $minVariant['onsale_price'] < $p['sale_price'])
                ) {
                    $p['price'] = $minVariant['price'];
                    $p['sale_price'] = ($minVariant['onsale_price'] === $minVariant['price']) ? null : $minVariant['onsale_price'];
                    if ($this->multipriceEnabled) {
                        $p['df_multiprice'] = $minVariant['multiprice'];
                    }
                }
            }
        }

        if ($this->productVariations) {
            $p['variation_reference'] = $product['variation_reference'];
            $p['variation_supplier_reference'] = $product['variation_supplier_reference'];
            $p['variation_mpn'] = $product['variation_mpn'];
            $p['variation_ean13'] = $product['variation_ean13'];
            $p['variation_upc'] = $product['variation_upc'];
            $p['df_group_leader'] = (is_numeric($product['df_group_leader']) && 0 !== (int) $product['df_group_leader']);
            $p['df_variants_information'] = $this->getVariantsInformation($product);

            $attributes = $this->getAttributes($product);

            $p = array_merge($p, $attributes);

            foreach ($extraAttributesHeader as $extraAttributeHeader) {
                if ('attributes' !== $extraAttributeHeader && !array_key_exists($extraAttributeHeader, $p) && array_key_exists($extraAttributeHeader, $attributes)) {
                    $p[$extraAttributeHeader] = $attributes[$extraAttributeHeader];
                }
            }
        }

        if ($this->showProductFeatures) {
            $p['features'] = $this->getFeatures($product);
        }

        foreach ($extraHeaders as $extraHeader) {
            if (!empty($p[$extraHeader])) {
                continue;
            }
            $p[$extraHeader] = isset($product[$extraHeader]) ? DfTools::cleanString($product[$extraHeader]) : '';
        }

        return $p;
    }

    /**
     * Applies specific transformations to a product's data for CSV export.
     *
     * This method performs several modifications on the product array, like:
     * - If multi-price is enabled, formats the multiprice field.
     * - Joins category values using a predefined separator.
     * - If variants information exists, it slugifies and joins them using "%%".
     * - Casts the group leader flag to an integer.
     * - Iterates over extra headers to process attribute values:
     *   - For each non-empty extra header, it concatenates key-value pairs in the format "key=value",
     *     with the value cleaned and any "/" characters escaped.
     * - Processes features (if present and an array) by converting them into an attributes string
     *   formatted as "key=value" pairs joined with "/". The original features key is removed.
     * - Ensures the final product fields are ordered according to the given headers.
     *
     * @param array $product the associative array representing the product data
     * @param array $extraHeaders an array of additional headers to process in the product data
     * @param array $allHeaders an array specifying the order of CSV fields
     *
     * @return array the transformed product array ready for CSV export
     */
    public function applySpecificTransformationsForCsv($product, $extraHeaders, $allHeaders)
    {
        if ($this->multipriceEnabled) {
            $product['df_multiprice'] = DfTools::getFormattedMultiprice($product['df_multiprice']);
        }
        $product['categories'] = implode(DfTools::CATEGORY_SEPARATOR, $product['categories']);
        $product['category_merchandising'] = implode(DfTools::CATEGORY_SEPARATOR, $product['category_merchandising']);

        if (array_key_exists('df_variants_information', $product)) {
            $product['df_variants_information'] = implode('%%', array_map([__NAMESPACE__ . '\DfTools', 'slugify'], $product['df_variants_information']));
        }

        $product['df_group_leader'] = (is_array($product) && array_key_exists('df_group_leader', $product)) ? (int) $product['df_group_leader'] : DoofinderConstants::NO;

        if (array_key_exists('features', $product) && is_array($product['features'])) {
            $formattedAttributes = [];
            foreach ($product['features'] as $key => $value) {
                if (is_array($value)) {
                    $keyValueToReturn = [];
                    foreach ($value as $singleValue) {
                        $keyValueToReturn[] = $key . '=' . $singleValue;
                    }
                    $formattedAttributes[] = implode('/', $keyValueToReturn);
                } else {
                    $formattedAttributes[] = $key . '=' . $value;
                }
            }
            $product['attributes'] = str_replace('\"', '"', implode('/', $formattedAttributes));
            unset($product['features']);
        }

        $product = self::ensureCsvFieldsOrder($product, $allHeaders);

        return $product;
    }

    private static function ensureCsvFieldsOrder($product, $allHeaders)
    {
        $productWithSortedAttributes = [];
        foreach ($allHeaders as $header) {
            $productWithSortedAttributes[$header] = array_key_exists($header, $product) ? $product[$header] : '';
        }

        return $productWithSortedAttributes;
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
                $imageLink = DfTools::getImageLink(
                    $product['id_product_attribute'],
                    $idImage,
                    $product['link_rewrite'],
                    $this->imageSize
                );
            } else {
                $imageLink = DfTools::getImageLink(
                    $product['id_product_attribute'],
                    $product['id_image'],
                    $product['link_rewrite'],
                    $this->imageSize
                );
            }

            // For variations with no specific pictures
            if (strpos($imageLink, '/-') > -1) {
                $imageLink = DfTools::getImageLink(
                    $product['id_product'],
                    $product['id_image'],
                    $product['link_rewrite'],
                    $this->imageSize
                );
            }
        }else{
            $imageLink = DfTools::getImageLink(
                $product['id_product'],
                $product['id_image'],
                $product['link_rewrite'],
                $this->imageSize
            );
        }

        if (empty($imageLink)) {
            return '';
        }
         
        return DfTools::cleanURL($imageLink);
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
                    $features[DfTools::slugify($key)][] = DfTools::cleanString($value);
                }
            } else {
                $features[DfTools::slugify($key)] = DfTools::cleanString($values[0]);
            }
        }

        return $features;
    }

    private function getFeaturesKeys()
    {
        $allFeatureKeys = DfTools::getFeatureKeysForShopAndLang($this->idShop, $this->idLang);

        if (is_array($this->featuresShown) && count($this->featuresShown) > 0 && $this->featuresShown[0] !== '') {
            return DfTools::getSelectedFeatures($allFeatureKeys, $this->featuresShown);
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
            $altAttributes[DfTools::slugify($attribute['group_name'])] = $attribute['name'];
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

            return array_map([__NAMESPACE__ . '\DfTools', 'slugify'], $names);
        }

        return [];
    }
}
