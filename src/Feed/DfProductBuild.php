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
     * @var array Cached variations data keyed by product ID
     */
    private $cachedVariations = [];

    /**
     * @var array Cached categories data keyed by product ID
     */
    private $cachedCategories = [];

    /**
     * @var array Cached category links data keyed by category ID
     */
    private $cachedCategoryLinks = [];

    /**
     * @var array Cached features data keyed by product ID
     */
    private $cachedFeatures = [];

    /**
     * @var array Cached attributes data keyed by variation ID
     */
    private $cachedAttributes = [];

    /**
     * @var array Cached variation images data keyed by 'productId_attributeId'
     */
    private $cachedVariationImages = [];

    /**
     * @var array Cached stock data keyed by 'productId' or 'productId_attributeId'
     */
    private $cachedStock = [];

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

        // Preload all data in batch to avoid N+1 queries
        $this->preloadBatchData($products);

        foreach ($products as $product) {
            $minPriceVariant = null;
            $productId = (int) $product['id_product'];
            
            if ($this->productVariations && $product['variant_count'] > 0) {
                // Get variations from cache
                $variations = isset($this->cachedVariations[$productId])
                    ? $this->cachedVariations[$productId]
                    : [];
                
                foreach ($variations as $variation) {
                    $minPriceVariant = $this->getMinPrice($minPriceVariant, $variation);
                    $payload[] = $this->buildVariation($product, $variation);
                }

                $payload[] = $this->buildProduct($product, $minPriceVariant);
            } else {
                $payload[] = $this->buildProduct($product, null);
            }
        }

        return json_encode($payload);
    }

    /**
     * Preload all data needed for building products in batch to avoid N+1 queries.
     *
     * @param array $products Array of product data
     */
    private function preloadBatchData($products)
    {
        if (empty($products)) {
            return;
        }

        $productIds = array_map(function ($product) {
            return (int) $product['id_product'];
        }, $products);

        // Load all variations in batch (with integrated attributes and images)
        if ($this->productVariations) {
            $this->cachedVariations = DfTools::getProductVariationsBatch(
                $productIds,
                $this->idLang,
                $this->attributesShown
            );
        }

        // Load all categories in batch
        $this->cachedCategories = DfTools::getCategoriesForProductsBatch(
            $productIds,
            $this->idLang,
            $this->idShop,
            false
        );

        // Collect all category IDs for batch loading category links
        $allCategoryIds = [];
        foreach ($products as $product) {
            if (isset($product['category_ids']) && !empty($product['category_ids'])) {
                $categoryIds = explode(',', $product['category_ids']);
                $allCategoryIds = array_merge($allCategoryIds, $categoryIds);
            }
        }
        if (!empty($allCategoryIds)) {
            $this->cachedCategoryLinks = DfTools::getCategoryLinksByIdBatch(
                $allCategoryIds,
                $this->idLang,
                $this->idShop
            );
        }

        // Features are now integrated in getAvailableProducts query, parse from products
        if ($this->showProductFeatures) {
            $this->cachedFeatures = [];
            foreach ($products as $product) {
                if (isset($product['features_data']) && !empty($product['features_data'])) {
                    $productId = (int) $product['id_product'];
                    $this->cachedFeatures[$productId] = $this->parseFeaturesData($product['features_data']);
                }
            }
        }

        // Attributes and variation images are now integrated in getProductVariationsBatch query
        if ($this->productVariations) {
            $this->cachedAttributes = [];
            $this->cachedVariationImages = [];
            foreach ($this->cachedVariations as $variations) {
                foreach ($variations as $variation) {
                    $variationId = isset($variation['id_product_attribute']) ? (int) $variation['id_product_attribute'] : 0;
                    $productId = isset($variation['id_product']) ? (int) $variation['id_product'] : 0;
                    
                    // Parse attributes from integrated data
                    if (isset($variation['attributes_data']) && !empty($variation['attributes_data'])) {
                        $this->cachedAttributes[$variationId] = $this->parseAttributesData($variation['attributes_data']);
                    }
                    
                    // Parse variation images from integrated data
                    if (isset($variation['variation_image_ids']) && !empty($variation['variation_image_ids'])) {
                        $cacheKey = $productId . '_' . $variationId;
                        $this->cachedVariationImages[$cacheKey] = array_filter(
                            array_map('intval', explode(',', $variation['variation_image_ids']))
                        );
                    }
                }
            }
        }

        // Load all stock information in batch
        $stockData = [];
        foreach ($products as $product) {
            $stockData[] = [
                'id_product' => (int) $product['id_product'],
                'id_product_attribute' => 0,
            ];
        }
        if ($this->productVariations) {
            foreach ($this->cachedVariations as $variations) {
                foreach ($variations as $variation) {
                    $stockData[] = [
                        'id_product' => (int) $variation['id_product'],
                        'id_product_attribute' => (int) $variation['id_product_attribute'],
                    ];
                }
            }
        }
        if (!empty($stockData)) {
            $this->cachedStock = DfTools::getStockAvailableBatch($stockData, $this->idShop);
        }
    }

    /**
     * Get the minimum price among product variations.
     *
     * @param array|null $currentMinPrice Current minimum price array (or null)
     * @param array $variation Variation data
     *
     * @return array|null Minimum price array or null
     */
    public function getMinPrice($currentMinPrice, $variation)
    {
        if ($this->displayPrices) {
            $variantPrices = DfTools::getVariantPrices($variation['id_product'], $variation['id_product_attribute'], $this->useTax, $this->currencies, $this->customerGroupsData);
            if (!isset($currentMinPrice['onsale_price']) || $variantPrices['onsale_price'] < $currentMinPrice['onsale_price']) {
                return $variantPrices;
            } else {
                return $currentMinPrice;
            }
        } else {
            return null;
        }
    }

    /**
     * Build variation payload from product and variation data.
     *
     * @param array $product Base product data
     * @param array $variation Variation data
     *
     * @return array Variation payload
     */
    public function buildVariation($product, $variation)
    {
        $expanded_variation = array_merge($product, $variation, $extraAttributesHeader = [], $extraHeaders = []);

        return $this->buildProduct($expanded_variation, [], $extraAttributesHeader, $extraHeaders);
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
        $productId = (int) $product['id_product'];
        
        // Use cached categories if available, otherwise fallback to individual query
        if (isset($this->cachedCategories[$productId])) {
            $p['categories'] = $this->cachedCategories[$productId];
        } else {
            $p['categories'] = DfTools::getCategoriesForProductIdAndLanguage(
                $product['id_product'],
                $this->idLang,
                $this->idShop,
                false
            );
        }
        
        // Use cached category links if available
        if (isset($product['category_ids']) && !empty($product['category_ids'])) {
            $categoryIds = explode(',', $product['category_ids']);
            $categoryLinks = [];
            foreach ($categoryIds as $categoryId) {
                $categoryId = (int) trim($categoryId);
                if (isset($this->cachedCategoryLinks[$categoryId])) {
                    $categoryLinks[] = $this->cachedCategoryLinks[$categoryId];
                } else {
                    // Fallback to individual query if not in cache
                    $categoryLinks = DfTools::getCategoryLinksById(
                        $product['category_ids'],
                        $this->idLang,
                        $this->idShop
                    );
                    break;
                }
            }
            $p['category_merchandising'] = $categoryLinks;
        } else {
            $p['category_merchandising'] = [];
        }
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
            $p['features'] = $this->getFeatures($product, $productId);
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
        $featureKeys = $this->showProductFeatures ? $this->featuresKeys : null;
        
        $products = DfTools::getAvailableProducts(
            $this->idLang,
            $this->productVariations,
            false,
            false,
            $this->products,
            $featureKeys
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
            $productId = (int) $product['id_product'];
            $attributeId = (int) $product['id_product_attribute'];
            $cacheKey = $productId . '_' . $attributeId;

            // Use cached variation images if available
            if (isset($this->cachedVariationImages[$cacheKey]) && !empty($this->cachedVariationImages[$cacheKey])) {
                $idImage = $this->cachedVariationImages[$cacheKey][0];
            } else {
                // Fallback to individual query
                $idImage = DfTools::getVariationImg($productId, $attributeId);
            }

            if (!empty($idImage)) {
                $imageLink = DfTools::getImageLink(
                    $attributeId,
                    $idImage,
                    $product['link_rewrite'],
                    $this->imageSize
                );
            } else {
                $imageLink = DfTools::getImageLink(
                    $attributeId,
                    $product['id_image'],
                    $product['link_rewrite'],
                    $this->imageSize
                );
            }

            // For variations with no specific pictures
            if (strpos($imageLink, '/-') > -1) {
                $imageLink = DfTools::getImageLink(
                    $productId,
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
            $productId = (int) $product['id_product'];
            $attributeId = (int) $product['id_product_attribute'];
            $cacheKey = $productId . '_' . $attributeId;

            // Use cached variation images if available
            if (isset($this->cachedVariationImages[$cacheKey])) {
                $imageIds = $this->cachedVariationImages[$cacheKey];
            } else {
                // Fallback to individual query
                $imageIds = DfTools::getVariationImages($productId, $attributeId);
            }
            $idForImageLink = $attributeId;
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
            $productId = (int) $product['id_product'];
            $attributeId = isset($product['id_product_attribute']) ? (int) $product['id_product_attribute'] : 0;
            $cacheKey = $attributeId > 0 ? $productId . '_' . $attributeId : $productId;

            // Use cached stock if available
            if (isset($this->cachedStock[$cacheKey])) {
                $stock = $this->cachedStock[$cacheKey]['quantity'];
                $outOfStock = $this->cachedStock[$cacheKey]['out_of_stock'];
            } else {
                // Fallback to individual query
                $stock = \StockAvailable::getQuantityAvailableByProduct(
                    $productId,
                    $attributeId > 0 ? $attributeId : null,
                    $this->idShop
                );
                $outOfStock = isset($product['out_of_stock']) ? (int) $product['out_of_stock'] : 0;
            }
            
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
     * Parse features data from integrated query result.
     *
     * @param string $featuresData Features data in format "name:value|||name:value"
     *
     * @return array Processed features
     */
    private function parseFeaturesData($featuresData)
    {
        $features = [];
        $pairs = explode('|||', $featuresData);
        
        foreach ($pairs as $pair) {
            if (strpos($pair, ':') === false) {
                continue;
            }
            list($name, $value) = explode(':', $pair, 2);
            $name = trim($name);
            $value = trim($value);
            
            if (empty($name) || empty($value)) {
                continue;
            }
            
            // Filter by feature keys if configured
            if (!empty($this->featuresKeys) && !in_array($name, $this->featuresKeys, true)) {
                continue;
            }
            
            if (!isset($features[$name])) {
                $features[$name] = [];
            }
            $features[$name][] = $value;
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
     * @param int $productId Product ID (for cache lookup)
     *
     * @return array Processed features
     */
    private function getFeatures($product, $productId)
    {
        $features = [];

        // Use cached features if available (from integrated query), otherwise fallback to individual query
        if (isset($this->cachedFeatures[$productId])) {
            $productFeatures = $this->cachedFeatures[$productId];
        } else {
            $keys = $this->featuresKeys;
            $productFeatures = DfTools::getFeaturesForProduct($product['id_product'], $this->idLang, $keys);
        }

        foreach ($productFeatures as $key => $values) {
            if (is_array($values) && count($values) > 1) {
                foreach ($values as $value) {
                    $features[DfTools::slugify($key)][] = DfTools::cleanString($value);
                }
            } else {
                $value = is_array($values) ? $values[0] : $values;
                $features[DfTools::slugify($key)] = DfTools::cleanString($value);
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
     * Parse attributes data from integrated query result.
     *
     * @param string $attributesData Attributes data in format "group_name:name|||group_name:name"
     *
     * @return array Processed attributes in format [['group_name' => ..., 'name' => ...], ...]
     */
    private function parseAttributesData($attributesData)
    {
        $attributes = [];
        $pairs = explode('|||', $attributesData);
        
        foreach ($pairs as $pair) {
            if (strpos($pair, ':') === false) {
                continue;
            }
            list($groupName, $name) = explode(':', $pair, 2);
            $groupName = trim($groupName);
            $name = trim($name);
            
            if (empty($groupName) || empty($name)) {
                continue;
            }
            
            $attributes[] = [
                'group_name' => $groupName,
                'name' => $name,
            ];
        }
        
        return $attributes;
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
        if (!isset($product['id_product_attribute']) || (int) $product['id_product_attribute'] <= 0) {
            return [];
        }

        $variationId = (int) $product['id_product_attribute'];

        // Use cached attributes if available (from integrated query), otherwise fallback to individual query
        if (isset($this->cachedAttributes[$variationId])) {
            $attributes = $this->cachedAttributes[$variationId];
        } else {
            $attributes = DfTools::getAttributesByCombination(
                $variationId,
                $this->idLang,
                $this->attributesShown
            );
        }

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
