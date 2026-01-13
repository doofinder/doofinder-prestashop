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

namespace PrestaShop\Module\Doofinder\Feed;

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class DfProductBuild
 *
 * Builds product payloads for Doofinder export in PrestaShop.
 *
 * This class:
 * - Fetches product data using custom utilities (DfTools, DoofinderConstants).
 * - Processes attributes, features, variations, pricing, and stock.
 * - Can build JSON payloads or arrays for CSV export.
 */
class DfProductBuild
{
    /**
     * @var int shop ID
     */
    private $idShop;

    /**
     * @var int language ID
     */
    private $idLang;

    /**
     * @var int currency ID
     */
    private $idCurrency;

    /**
     * @var array available currencies in this shop
     */
    private $currencies;

    /**
     * @var array product IDs to process
     */
    private $products;

    /**
     * @var string attribute group configuration to display
     */
    private $attributesShown;

    /**
     * @var bool whether product prices should be displayed
     */
    private $displayPrices;

    /**
     * @var string preconfigured image size name
     */
    private $imageSize;

    /**
     * @var \Link prestaShop Link object for URL generation
     */
    private $link;

    /**
     * @var mixed URL rewriting configuration flag
     */
    private $linkRewriteConf;

    /**
     * @var bool whether product variations should be exported
     */
    private $productVariations;

    /**
     * @var bool whether product features should be exported
     */
    private $showProductFeatures;

    /**
     * @var mixed whether stock management is enabled (from PS configuration)
     */
    private $stockManagement;

    /**
     * @var bool whether prices should include tax
     */
    private $useTax;

    /**
     * @var mixed whether multi-price export is enabled
     */
    private $multipriceEnabled;

    /**
     * @var array IDs of features to export
     */
    private $featuresShown;

    /**
     * @var array keys of features selected for export
     */
    private $featuresKeys;

    /**
     * @var array A list of customer Groups data
     */
    private $customerGroupsData;

    /**
     * Constructor.
     *
     * Initializes configuration settings for building product data.
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @param int $idCurrency Currency ID
     */
    public function __construct($idShop, $idLang, $idCurrency)
    {
        $this->idShop = $idShop;
        $this->idLang = $idLang;
        $this->idCurrency = $idCurrency;
        $this->customerGroupsData = DfTools::getAdditionalCustomerGroupsAndDefaultCustomers();
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

    /**
     * Build the JSON payload of products for Doofinder export.
     *
     * @return string JSON-encoded payload
     */
    public function build()
    {
        $payload = [];

        \Shop::setContext(\Shop::CONTEXT_SHOP, $this->idShop);
        $products = $this->getProductData();

        if (empty($products)) {
            return json_encode($payload);
        }

        // Batch fetch all related data upfront to avoid N+1 queries
        $batchData = $this->batchFetchAllData($products);

        foreach ($products as $product) {
            $minPriceVariant = null;
            if ($this->productVariations && $product['variant_count'] > 0) {
                $variations = isset($batchData['variations'][$product['id_product']]) ? $batchData['variations'][$product['id_product']] : [];
                foreach ($variations as $variation) {
                    $variationKey = $product['id_product'] . '_' . $variation['id_product_attribute'];
                    $variationPrices = isset($batchData['variant_prices'][$variationKey]) ? $batchData['variant_prices'][$variationKey] : null;
                    if ($variationPrices) {
                        $minPriceVariant = $this->getMinPriceFromData($minPriceVariant, $variationPrices);
                    }
                    $payload[] = $this->buildVariationWithData($product, $variation, $batchData);
                }
                $payload[] = $this->buildProductWithData($product, $minPriceVariant, $batchData);
            } else {
                $payload[] = $this->buildProductWithData($product, null, $batchData);
            }
        }

        return json_encode($payload);
    }

    /**
     * Batch fetch all related data for products to avoid N+1 queries.
     *
     * @param array $products Array of products
     *
     * @return array Batch data containing variations, categories, features, attributes, images, prices, and stock
     */
    public function batchFetchAllData($products)
    {
        $data = [
            'variations' => [],
            'categories' => [],
            'category_links' => [],
            'features' => [],
            'attributes' => [],
            'variation_images' => [],
            'variant_prices' => [],
            'stock' => [],
            'variants_information' => [],
        ];

        if (empty($products)) {
            return $data;
        }

        $productIds = array_map('intval', array_column($products, 'id_product'));

        if ($this->productVariations) {
            $allVariations = $this->batchFetchVariations($productIds);
            foreach ($allVariations as $variation) {
                $data['variations'][$variation['id_product']][] = $variation;
            }
        }

        $data['categories'] = $this->batchFetchCategories($productIds);

        $allCategoryIds = [];
        if (null !== $products) {
            foreach ($products as $product) {
                if (!empty($product['category_ids'])) {
                    $categoryIds = explode(',', $product['category_ids']);
                    $allCategoryIds = array_merge($allCategoryIds, array_map('intval', $categoryIds));
                }
            }
        }

        if (!empty($allCategoryIds)) {
            $data['category_links'] = $this->batchFetchCategoryLinks($allCategoryIds);
        }

        if ($this->showProductFeatures) {
            $data['features'] = $this->batchFetchFeatures($productIds);
        }

        if ($this->productVariations && !empty($allVariations)) {
            $variationIds = array_column($allVariations, 'id_product_attribute');
            $data['attributes'] = $this->batchFetchAttributes($variationIds);
            $data['variation_images'] = $this->batchFetchVariationImages($productIds, $variationIds);
        }

        if ($this->displayPrices && $this->productVariations && !empty($allVariations)) {
            foreach ($allVariations as $variation) {
                $key = $variation['id_product'] . '_' . $variation['id_product_attribute'];
                $data['variant_prices'][$key] = DfTools::getVariantPrices(
                    $variation['id_product'],
                    $variation['id_product_attribute'],
                    $this->useTax,
                    $this->currencies,
                    $this->customerGroupsData
                );
            }
        }

        $variationIdsForStock = [];
        if ($this->productVariations && !empty($allVariations)) {
            $variationIdsForStock = array_column($allVariations, 'id_product_attribute');
        }
        $data['stock'] = $this->batchFetchStock($productIds, $variationIdsForStock);

        if ($this->productVariations) {
            $data['variants_information'] = $this->batchFetchVariantsInformation($productIds);
        }

        return $data;
    }

    /**
     * Batch fetch variations for multiple products.
     *
     * @param array $productIds Array of product IDs
     *
     * @return array All variations indexed by product ID
     */
    private function batchFetchVariations($productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $query = new \DbQuery();
        $query->select('pa.reference AS variation_reference, pa.ean13 AS variation_ean13, pa.upc AS variation_upc');
        $query->select('false as df_group_leader, 0 as variant_count');
        if (DfTools::versionGte('1.7.0.0')) {
            $query->select('pa.isbn AS isbn');
        }
        if (DfTools::versionGte('1.7.7.0')) {
            $query->select('pa.mpn AS variation_mpn');
        } else {
            $query->select('pa.reference AS variation_mpn');
        }
        $query->select('pa.id_product, pa.id_product_attribute');
        $query->from('product_attribute', 'pa');
        $query->join(\Shop::addSqlAssociation('product_attribute', 'pa'));
        $query->where('pa.id_product IN (' . implode(',', array_map('intval', $productIds)) . ')');

        $query->leftJoin('product', 'p', 'p.id_product = pa.id_product');
        $query->select('psp.product_supplier_reference AS variation_supplier_reference');
        $query->select('s.name AS supplier_name');
        $query->leftJoin('product_supplier', 'psp', 'p.`id_supplier` = psp.`id_supplier` AND p.`id_product` = psp.`id_product` AND pa.`id_product_attribute` = psp.`id_product_attribute`');
        $query->leftJoin('supplier', 's', 's.`id_supplier` = p.`id_supplier`');
        $query->select('sa.out_of_stock as out_of_stock, sa.quantity as stock_quantity');
        $query->leftJoin('stock_available', 'sa', 'p.id_product = sa.id_product AND sa.id_product_attribute = pa.id_product_attribute AND (sa.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ') OR (sa.id_shop = 0 AND sa.id_shop_group = ' . (int) \Shop::getContextShopGroupID() . '))');
        $query->select('pas.minimal_quantity AS minimum_quantity');
        $query->leftJoin('product_attribute_shop', 'pas', 'pa.id_product_attribute = pas.id_product_attribute');
        $query->groupBy('pa.id_product_attribute');

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        return $result ?: \Db::getInstance()->executeS($query);
    }

    /**
     * Batch fetch categories for multiple products.
     *
     * @param array $productIds Array of product IDs
     *
     * @return array Categories indexed by product ID (as arrays)
     */
    private function batchFetchCategories($productIds)
    {
        $categories = [];
        foreach ($productIds as $productId) {
            $catData = DfTools::getCategoriesForProductIdAndLanguage($productId, $this->idLang, $this->idShop, false);
            $categories[$productId] = (array) $catData;
        }

        return $categories;
    }

    /**
     * Batch fetch category links for multiple category IDs.
     *
     * @param array $categoryIds Array of category IDs
     *
     * @return array Category links indexed by category ID
     */
    private function batchFetchCategoryLinks($categoryIds)
    {
        $links = [];
        $link = \Context::getContext()->link;
        $useRewriting = (bool) \Configuration::get('PS_REWRITING_SETTINGS');

        foreach ($categoryIds as $categoryId) {
            $category = new \Category((int) $categoryId, $this->idLang, $this->idShop);
            if (\Validate::isLoadedObject($category)) {
                if ($useRewriting) {
                    $categoryLink = $link->getCategoryLink($category);
                    $links[$categoryId] = trim(parse_url($categoryLink, PHP_URL_PATH), '/');
                } else {
                    $links[$categoryId] = $categoryId;
                }
            }
        }

        return $links;
    }

    /**
     * Batch fetch features for multiple products.
     *
     * @param array $productIds Array of product IDs
     *
     * @return array Features indexed by product ID
     */
    private function batchFetchFeatures($productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $features = [];
        $keys = $this->featuresKeys;

        $query = new \DbQuery();
        $query->select('fp.id_product, fl.name, fvl.value');
        $query->from('feature_product', 'fp');
        $query->leftJoin('feature_lang', 'fl', 'fl.id_feature = fp.id_feature AND fl.id_lang = ' . (int) $this->idLang);
        $query->leftJoin('feature_value_lang', 'fvl', 'fvl.id_feature_value = fp.id_feature_value AND fvl.id_lang = ' . (int) $this->idLang);
        $query->where('fp.id_product IN (' . implode(',', array_map('intval', $productIds)) . ')');
    
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (!$result) {
            $result = \Db::getInstance()->executeS($query);
        }

        if ($result) {
            foreach ($result as $row) {
                if (in_array($row['name'], $keys, true)) {
                    $productId = (int) $row['id_product'];
                    if (!isset($features[$productId])) {
                        $features[$productId] = [];
                    }
                    if (!isset($features[$productId][$row['name']])) {
                        $features[$productId][$row['name']] = [];
                    }
                    $features[$productId][$row['name']][] = $row['value'];
                }
            }
        }

        return $features;
    }

    /**
     * Batch fetch attributes for multiple variations.
     *
     * @param array $variationIds Array of variation IDs
     *
     * @return array Attributes indexed by variation ID
     */
    private function batchFetchAttributes($variationIds)
    {
        if (empty($variationIds)) {
            return [];
        }

        $attributes = [];

        $query = new \DbQuery();
        $query->select('pc.id_product_attribute, pal.name, pagl.name AS group_name');
        $query->from('product_attribute_combination', 'pc');
        $query->leftJoin('attribute', 'pa', 'pc.id_attribute = pa.id_attribute');
        $query->leftJoin('attribute_lang', 'pal', 'pc.id_attribute = pal.id_attribute AND pal.id_lang = ' . (int) $this->idLang);
        $query->leftJoin('attribute_group_lang', 'pagl', 'pagl.id_attribute_group = pa.id_attribute_group AND pagl.id_lang = ' . (int) $this->idLang);
        $query->where('pc.id_product_attribute IN (' . implode(',', array_map('intval', $variationIds)) . ')');
        if ($this->attributesShown) {
            $query->where('pa.id_attribute_group IN (' . pSQL($this->attributesShown) . ')');
        }

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (!$result) {
            $result = \Db::getInstance()->executeS($query);
        }

        if ($result) {
            foreach ($result as $row) {
                $variationId = (int) $row['id_product_attribute'];
                if (!isset($attributes[$variationId])) {
                    $attributes[$variationId] = [];
                }
                $attributes[$variationId][DfTools::slugify($row['group_name'])] = $row['name'];
            }
        }

        return $attributes;
    }

    /**
     * Batch fetch variation images for multiple products and variations.
     *
     * @param array $productIds Array of product IDs
     * @param array $variationIds Array of variation IDs
     *
     * @return array Variation images indexed by product_id_attribute_id
     */
    private function batchFetchVariationImages($productIds, $variationIds)
    {
        if (empty($variationIds)) {
            return [];
        }

        $images = [];
        $query = new \DbQuery();
        $query->select('DISTINCT pai.id_product_attribute, pai.id_image, i.id_product, i.position');
        $query->from('product_attribute_image', 'pai');
        $query->innerJoin('image', 'i', 'i.id_image = pai.id_image AND i.id_product IN (' . implode(',', array_map('intval', $productIds)) . ')');
        $query->where('pai.id_product_attribute IN (' . implode(',', array_map('intval', $variationIds)) . ')');
        $query->orderBy('pai.id_product_attribute, i.position ASC');

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (!$result) {
            $result = \Db::getInstance()->executeS($query);
        }

        if ($result) {
            foreach ($result as $row) {
                $key = $row['id_product'] . '_' . $row['id_product_attribute'];
                if (!isset($images[$key])) {
                    $images[$key] = [];
                }
                $images[$key][] = (int) $row['id_image'];
            }
        }

        return $images;
    }

    /**
     * Batch fetch stock availability for products and variations.
     *
     * @param array $productIds Array of product IDs
     * @param array $variationIds Array of variation IDs
     *
     * @return array Stock data indexed by product_id_attribute_id
     */
    private function batchFetchStock($productIds, $variationIds)
    {
        $stock = [];
        if (empty($productIds)) {
            return $stock;
        }

        $shopIds = \Shop::getContextListShopID();
        $shopGroupId = \Shop::getContextShopGroupID();

        $query = new \DbQuery();
        $query->select('id_product, id_product_attribute, quantity, out_of_stock');
        $query->from('stock_available');
        $query->where('id_product IN (' . implode(',', array_map('intval', $productIds)) . ')');
        $query->where('(id_shop IN (' . implode(',', array_map('intval', $shopIds)) . ') OR (id_shop = 0 AND id_shop_group = ' . (int) $shopGroupId . '))');
        $attributeCondition = 'id_product_attribute = 0';
        if (!empty($variationIds)) {
            $attributeCondition .= ' OR id_product_attribute IN (' . implode(',', array_map('intval', $variationIds)) . ')';
        }
        $query->where('(' . $attributeCondition . ')');

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (!$result) {
            $result = \Db::getInstance()->executeS($query);
        }

        if ($result) {
            foreach ($result as $row) {
                $key = $row['id_product'] . '_' . $row['id_product_attribute'];
                $stock[$key] = [
                    'quantity' => (int) $row['quantity'],
                    'out_of_stock' => (int) $row['out_of_stock'],
                ];
            }
        }

        return $stock;
    }

    /**
     * Batch fetch variants information for multiple products.
     *
     * @param array $productIds Array of product IDs
     *
     * @return array Variants information indexed by product ID
     */
    private function batchFetchVariantsInformation($productIds)
    {
        $variantsInfo = [];
        if (empty($this->attributesShown)) {
            return $variantsInfo;
        }

        $attrGroups = implode(',', array_map('intval', explode(',', $this->attributesShown)));
        $query = new \DbQuery();
        $query->select('DISTINCT p.id_product, a.id_attribute_group');
        $query->from('product', 'p');
        $query->leftJoin('product_attribute', 'pa', 'p.id_product = pa.id_product');
        $query->leftJoin('product_attribute_combination', 'pac', 'pa.id_product_attribute = pac.id_product_attribute');
        $query->leftJoin('attribute', 'a', 'pac.id_attribute = a.id_attribute');
        $query->where('p.id_product IN (' . implode(',', array_map('intval', $productIds)) . ')');
        $query->where('a.id_attribute_group IN (' . $attrGroups . ')');
        $query->groupBy('p.id_product, a.id_attribute_group');

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (!$result) {
            $result = \Db::getInstance()->executeS($query);
        }

        if ($result) {
            $productGroups = [];
            foreach ($result as $row) {
                $productId = (int) $row['id_product'];
                if (!isset($productGroups[$productId])) {
                    $productGroups[$productId] = [];
                }
                $productGroups[$productId][] = (int) $row['id_attribute_group'];
            }

            foreach ($productGroups as $productId => $groupIds) {
                $attributes = DfTools::getAttributesName($groupIds, $this->idLang);
                $names = array_column($attributes, 'name');
                $variantsInfo[$productId] = array_map(['\PrestaShop\Module\Doofinder\Utils\DfTools', 'slugify'], $names);
            }
        }

        return $variantsInfo;
    }

    /**
     * Get the minimum price from pre-fetched data.
     *
     * @param array|null $currentMinPrice Current minimum price array (or null)
     * @param array $variantPrices Pre-fetched variant prices
     *
     * @return array|null Minimum price array or null
     */
    public function getMinPriceFromData($currentMinPrice, $variantPrices)
    {
        if (!$this->displayPrices) {
            return null;
        }

        if (!isset($currentMinPrice['onsale_price']) || $variantPrices['onsale_price'] < $currentMinPrice['onsale_price']) {
            return $variantPrices;
        }

        return $currentMinPrice;
    }

    /**
     * Build product payload using pre-fetched batch data.
     *
     * @param array $product Product data
     * @param array|null $minPriceVariant Minimum price data from variations
     * @param array $batchData Pre-fetched batch data
     * @param array $extraAttributesHeader Additional attribute headers to process
     * @param array $extraHeaders Additional product headers to include
     *
     * @return array Processed product payload
     */
    public function buildProductWithData($product, $minPriceVariant, $batchData, $extraAttributesHeader = [], $extraHeaders = [])
    {
        $productId = $product['id_product'];
        $variationId = isset($product['id_product_attribute']) ? $product['id_product_attribute'] : 0;
        $key = $productId . '_' . $variationId;

        if (isset($batchData['categories'][$productId])) {
            $product['_categories'] = $batchData['categories'][$productId];
        }
        if (!empty($product['category_ids'])) {
            $categoryIds = explode(',', $product['category_ids']);
            $product['_category_links'] = array_filter(array_map(function ($id) use ($batchData) {
                return isset($batchData['category_links'][$id]) ? $batchData['category_links'][$id] : null;
            }, $categoryIds));
        } else {
            $product['_category_links'] = [];
        }
        if (isset($batchData['features'][$productId])) {
            $product['_features'] = $batchData['features'][$productId];
        }
        if (isset($batchData['attributes'][$variationId])) {
            $product['_attributes'] = $batchData['attributes'][$variationId];
        }
        if (isset($batchData['variation_images'][$key])) {
            $product['_variation_images'] = $batchData['variation_images'][$key];
        }
        if (isset($batchData['stock'][$key])) {
            $product['_stock'] = $batchData['stock'][$key];
        }
        if (isset($batchData['variants_information'][$productId])) {
            $product['_variants_information'] = $batchData['variants_information'][$productId];
        }

        return $this->buildProduct($product, $minPriceVariant, $extraAttributesHeader, $extraHeaders);
    }

    /**
     * Build variation payload using pre-fetched batch data.
     *
     * @param array $product Base product data
     * @param array $variation Variation data
     * @param array $batchData Pre-fetched batch data
     * @param array $extraAttributesHeader Additional attribute headers to process
     * @param array $extraHeaders Additional product headers to include
     *
     * @return array Variation payload
     */
    public function buildVariationWithData($product, $variation, $batchData, $extraAttributesHeader = [], $extraHeaders = [])
    {
        $expanded_variation = array_merge($product, $variation);

        return $this->buildProductWithData($expanded_variation, [], $batchData, $extraAttributesHeader, $extraHeaders);
    }

    /**
     * Build product payload.
     *
     * @param array $product Product data
     * @param array|null $minPriceVariant Minimum price data from variations
     * @param array $extraAttributesHeader Additional attribute headers to process
     * @param array $extraHeaders Additional product headers to include
     *
     * @return array Processed product payload
     */
    public function buildProduct($product, $minPriceVariant = null, $extraAttributesHeader = [], $extraHeaders = [])
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
        $p['images_links'] = $this->getImagesLinks($product);
        $p['main_category'] = DfTools::cleanString($product['main_category']);
        $p['categories'] = is_array($product['_categories']) ? $product['_categories'] : [];
        $p['category_merchandising'] = isset($product['_category_links']) ? $product['_category_links'] : [];
        $p['availability'] = $this->getAvailability($product);
        $p['brand'] = DfTools::cleanString($product['manufacturer']);
        $p['mpn'] = DfTools::cleanString($product['mpn']);
        $p['ean13'] = DfTools::cleanString($product['ean13']);
        $p['upc'] = DfTools::cleanString($product['upc']);
        $p['reference'] = DfTools::cleanString($product['reference']);
        $p['supplier_reference'] = DfTools::cleanString($product['supplier_reference']);
        $p['supplier_name'] = DfTools::cleanString($product['supplier_name']);
        $p['extra_title_1'] = $p['title'];
        $p['extra_title_2'] = DfTools::splitReferences($p['title']);
        $p['minimum_quantity'] = DfTools::cleanString($product['minimum_quantity']);
        $p['creation_date'] = DfTools::dateStringToIso8601($product['creation_date']);

        $productTags = DfTools::cleanString($product['tags']);
        $p['tags'] = $productTags;

        if (is_string($productTags)) {
            // Extra steps to avoid possible duplicates in tags
            $productTags = explode(',', $productTags);
            $productTags = array_unique($productTags);
            // Escape slashes in tags
            $productTags = array_map(function ($tag) {
                return str_replace('/', '//', $tag);
            }, $productTags);
            $p['tags'] = implode('/', $productTags);
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

            if (DfTools::isParent($product) && is_array($minPriceVariant)) {
                if (
                    !is_null($minPriceVariant['onsale_price'])
                    && !is_null($minPriceVariant['price'])
                    && (empty($p['sale_price']) || $minPriceVariant['onsale_price'] < $p['sale_price'])
                ) {
                    $p['price'] = $minPriceVariant['price'];
                    $p['sale_price'] = ($minPriceVariant['onsale_price'] === $minPriceVariant['price']) ? null : $minPriceVariant['onsale_price'];
                    if ($this->multipriceEnabled) {
                        $p['df_multiprice'] = $minPriceVariant['multiprice'];
                    }
                }
            }
        }

        if ($this->productVariations) {
            $p['variation_reference'] = DfTools::cleanString($product['variation_reference']);
            $p['variation_supplier_reference'] = DfTools::cleanString($product['variation_supplier_reference']);
            $p['variation_mpn'] = DfTools::cleanString($product['variation_mpn']);
            $p['variation_ean13'] = DfTools::cleanString($product['variation_ean13']);
            $p['variation_upc'] = DfTools::cleanString($product['variation_upc']);
            $p['df_group_leader'] = (is_numeric($product['df_group_leader']) && 0 !== (int) $product['df_group_leader']);
            if ($p['df_group_leader'] && isset($product['_variants_information'])) {
                $p['df_variants_information'] = $product['_variants_information'];
            }

            $attributes = isset($product['_attributes']) ? $product['_attributes'] : $this->getAttributes($product);

            // Merge attributes into product payload - attributes take precedence over any product data
            if (!empty($attributes)) {
                $p = array_merge($p, $attributes);
            }

            // Ensure all attribute headers from extraAttributesHeader are present for CSV column consistency
            // Add empty values for headers that don't have attributes (already merged ones are skipped)
            foreach ($extraAttributesHeader as $extraAttributeHeader) {
                if ('attributes' !== $extraAttributeHeader && !array_key_exists($extraAttributeHeader, $p)) {
                    $p[$extraAttributeHeader] = '';
                }
            }
        }

        if ($this->showProductFeatures) {
            $productFeatures = isset($product['_features']) ? $product['_features'] : [];
            $p['features'] = $this->processFeatures($productFeatures);
        }

        // Process extra headers - but exclude attribute headers that were already processed above
        // This prevents overwriting attributes with values from $product array
        $processedAttributeHeaders = $this->productVariations ? $extraAttributesHeader : [];
        foreach ($extraHeaders as $extraHeader) {
            // Skip if this is an attribute header (to prevent overwriting with product data)
            if (array_key_exists($extraHeader, $p) || in_array($extraHeader, $processedAttributeHeaders, true)) {
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
        $product['categories'] = implode(DfTools::LIST_SEPARATOR, $product['categories']);
        $product['category_merchandising'] = implode(DfTools::LIST_SEPARATOR, $product['category_merchandising']);
        $product['images_links'] = implode(DfTools::LIST_SEPARATOR, $product['images_links']);

        if (array_key_exists('df_variants_information', $product)) {
            $product['df_variants_information'] = implode('%%', array_map(['\PrestaShop\Module\Doofinder\Utils\DfTools', 'slugify'], $product['df_variants_information']));
        }

        $product['df_group_leader'] = (is_array($product) && array_key_exists('df_group_leader', $product)) ? (int) $product['df_group_leader'] : DoofinderConstants::NO;

        if (array_key_exists('features', $product) && is_array($product['features'])) {
            $formattedAttributes = [];
            foreach ($product['features'] as $key => $value) {
                if (is_array($value)) {
                    $keyValueToReturn = [];
                    foreach ($value as $singleValue) {
                        $keyValueToReturn[] = $key . '=' . str_replace('/', '\/', $singleValue);
                    }
                    $formattedAttributes[] = implode('/', $keyValueToReturn);
                } else {
                    $formattedAttributes[] = $key . '=' . str_replace('/', '\/', $value);
                }
            }
            $product['attributes'] = str_replace('\"', '"', implode('/', $formattedAttributes));
            unset($product['features']);
        }

        $product = self::ensureCsvFieldsOrder($product, $allHeaders);

        return $product;
    }

    /**
     * Ensures that CSV fields are sorted in the correct header order.
     *
     * This function is required because the number of columns must remain consistent.
     * Only the attributes included in the headers will be printed, and they will appear
     * in the exact same order as defined by the headers.
     *
     * @param array $product Product data
     * @param array $allHeaders Full ordered header list
     *
     * @return array Product data with keys sorted to match the headers
     */
    private static function ensureCsvFieldsOrder($product, $allHeaders)
    {
        $productWithSortedAttributes = [];
        foreach ($allHeaders as $header) {
            $productWithSortedAttributes[$header] = array_key_exists($header, $product) ? $product[$header] : '';
        }

        return $productWithSortedAttributes;
    }

    /**
     * Retrieve available products information for a specific language.
     *
     * @return array
     */
    private function getProductData()
    {
        $products = DfTools::getAvailableProducts(
            $this->idLang,
            $this->productVariations,
            false,
            false,
            $this->products
        );

        return $products;
    }

    /**
     * Get product ID (variation-safe).
     *
     * @param array $product Product data
     *
     * @return string|int Product ID or variation identifier
     */
    private function getId($product)
    {
        if ($this->hasVariations($product)) {
            return 'VAR-' . $product['id_product_attribute'];
        }

        return $product['id_product'];
    }

    /**
     * Get item group ID for variations.
     *
     * @param array $product Product data
     *
     * @return string|int
     */
    private function getItemGroupId($product)
    {
        if ($this->hasVariations($product)) {
            return $product['id_product'];
        }

        return '';
    }

    /**
     * Get product link (variation-safe).
     *
     * @param array $product Product data
     *
     * @return string URL
     */
    private function getLink($product)
    {
        if ($this->hasVariations($product)) {
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

    /**
     * Get product image link (variation-safe).
     *
     * @param array $product Product data
     *
     * @return string Image URL or empty string
     */
    private function getImageLink($product)
    {
        if ($this->hasVariations($product)) {
            $variationId = $product['id_product_attribute'];
            $idImage = $product['_variation_images'][0];

            if (!empty($idImage)) {
                $imageLink = DfTools::getImageLink(
                    $variationId,
                    $idImage,
                    $product['link_rewrite'],
                    $this->imageSize
                );
            } else {
                $imageLink = DfTools::getImageLink(
                    $variationId,
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
        } else {
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

    /**
     * Get all product images links.
     *
     * Returns an array of all image URLs for the product.
     * For variations, returns only variation-specific images.
     * For regular products, returns all product images.
     *
     * @param array $product Product data
     *
     * @return array Array of image URLs
     */
    private function getImagesLinks($product)
    {
        $imageIds = [];
        $idForImageLink = null;

        if ($this->hasVariations($product)) {
            $variationId = $product['id_product_attribute'];
            $imageIds = isset($product['_variation_images']) ? $product['_variation_images'] : [];
            $idForImageLink = $variationId;
        } else {
            $imageIds = array_filter(array_map('intval', explode(',', $product['all_image_ids'])));
            $idForImageLink = (int) $product['id_product'];
        }

        if (empty($imageIds)) {
            return [];
        }

        $imageLinks = [];

        foreach ($imageIds as $idImage) {
            $imageLink = DfTools::getImageLink(
                $idForImageLink,
                $idImage,
                $product['link_rewrite'],
                $this->imageSize
            );

            // For variations with no specific pictures, skip invalid image links
            if ($this->hasVariations($product) && strpos($imageLink, '/-') > -1) {
                continue;
            }

            if (!empty($imageLink)) {
                $cleanLink = DfTools::cleanURL($imageLink);
                $imageLinks[] = $cleanLink;
            }
        }

        return $imageLinks;
    }

    /**
     * Get stock availability label.
     *
     * @param array $product Product data
     *
     * @return string 'in stock' or 'out of stock'
     */
    private function getAvailability($product)
    {
        $available = (int) $product['available_for_order'] > 0;

        if ((int) $this->stockManagement) {
            $stock = $product['_stock']['quantity'];
            $outOfStock = $product['_stock']['out_of_stock'];
            $allowOosp = \Product::isAvailableWhenOutOfStock($outOfStock);

            return $available && ($stock > 0 || $allowOosp) ? 'in stock' : 'out of stock';
        } else {
            return $available ? 'in stock' : 'out of stock';
        }
    }

    /**
     * Get product price (normal or sale).
     *
     * @param array $product Product data
     * @param bool $salePrice Whether to return the sale price
     *
     * @return float|bool|null Converted price, false if hidden
     */
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

        $productPrice = DfTools::getPrice($product['id_product'], $this->useTax, $idProductAttribute, true, null);

        if (!$salePrice) {
            return $productPrice ? \Tools::convertPrice($productPrice, $this->idCurrency) : null;
        } else {
            $onsalePrice = DfTools::getOnsalePrice($product['id_product'], $this->useTax, $idProductAttribute, true, null);

            return ($productPrice && $onsalePrice && $productPrice != $onsalePrice)
                ? \Tools::convertPrice($onsalePrice, $this->idCurrency) : null;
        }
    }

    /**
     * Get multiprice field for a product.
     *
     * @param array $product Product data
     *
     * @return array Multiprice information
     */
    private function getMultiprice($product)
    {
        $productId = $product['id_product'];
        $idProductAttribute = $this->productVariations ? $product['id_product_attribute'] : null;

        return DfTools::getMultiprice($productId, $this->useTax, $this->currencies, $idProductAttribute, $this->customerGroupsData);
    }

    /**
     * Process pre-fetched features data.
     *
     * @param array $featuresData Raw features data from batch fetch
     *
     * @return array Processed features
     */
    private function processFeatures($featuresData)
    {
        $features = [];
        foreach ($featuresData as $key => $values) {
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

    /**
     * Get features for a product.
     *
     * Features are a way to describe and filter your Products.
     * More info at: https://docs.prestashop-project.org/v.8-documentation/user-guide/selling/managing-catalog/managing-product-features
     *
     * @param array $product Product data
     *
     * @return array Processed features
     */
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

    /**
     * Get feature keys for filtering.
     *
     * @return array
     */
    private function getFeaturesKeys()
    {
        $allFeatureKeys = DfTools::getFeatureKeysForShopAndLang($this->idShop, $this->idLang);

        if (is_array($this->featuresShown) && count($this->featuresShown) > 0 && $this->featuresShown[0] !== '') {
            return DfTools::getSelectedFeatures($allFeatureKeys, $this->featuresShown);
        } else {
            return $allFeatureKeys;
        }
    }

    /**
     * Get attributes for a product variation.
     *
     * Attributes are the basis of product variations.
     * More info at: https://docs.prestashop-project.org/v.8-documentation/user-guide/selling/managing-catalog/managing-product-attributes
     *
     * @param array $product Product data
     *
     * @return array Processed attributes
     */
    private function getAttributes($product)
    {
        $attributes = DfTools::getAttributesByCombination(
            $product['id_product_attribute'],
            $this->idLang,
            $this->attributesShown
        );

        if (empty($attributes)) {
            return [];
        }

        $altAttributes = [];

        foreach ($attributes as $attribute) {
            $altAttributes[DfTools::slugify($attribute['group_name'])] = $attribute['name'];
        }

        return $altAttributes;
    }

    /**
     * Check whether product has variations (and variations export is enabled).
     *
     * @param array $product Product data
     *
     * @return bool
     */
    private function hasVariations($product)
    {
        if ($this->productVariations) {
            if (isset($product['id_product_attribute']) && (int) $product['id_product_attribute'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get sluggified attribute names for variant information.
     *
     * @param array $product Product data
     *
     * @return array
     */
    private function getVariantsInformation($product)
    {
        if (DfTools::hasAttributes($product['id_product']) && !$product['id_product_attribute']) {
            $productAttributes = DfTools::hasProductAttributes($product['id_product'], $this->attributesShown);

            if (empty($productAttributes)) {
                return [];
            }

            $attributes = DfTools::getAttributesName($productAttributes, $this->idLang);

            $names = array_column($attributes, 'name');

            return array_map(['\PrestaShop\Module\Doofinder\Utils\DfTools', 'slugify'], $names);
        }

        return [];
    }
}
