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

namespace PrestaShop\Module\Doofinder\Utils;

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class DfTools
 *
 * Utility class for Doofinder PrestaShop module.
 *
 * Provides:
 * - Encryption helper
 * - Data validation helpers
 * - SQL preparation tools
 * - Configuration queries (image sizes, hashids)
 */
class DfTools
{
    /**
     * Separator used for list fields.
     */
    const LIST_SEPARATOR = ' %% ';

    /**
     * Separator used for category tree paths.
     */
    const CATEGORY_TREE_SEPARATOR = '>';

    /**
     * Separator used for text values.
     */
    const TXT_SEPARATOR = '|';

    /**
     * Regex to filter valid UTF-8 characters.
     *
     * @see http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
     */
    const VALID_UTF8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF]
    [\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F]
    [\x80-\xBF]{2})|./x';

    /**
     * @var array|null cached root category IDs
     */
    protected static $rootCategoryIds;

    /**
     * @var array cached category paths for optimization
     */
    protected static $cachedCategoryPaths = [];

    /**
     * @var array|null cached customer groups data for optimization
     */
    protected static $cachedCustomerGroupsData;

    /**
     * Hash a password using PrestaShop's cookie key.
     *
     * @param string $passwd Plain password to hash
     *
     * @return string MD5-hashed password
     */
    public static function encrypt($passwd)
    {
        return md5(_COOKIE_KEY_ . $passwd);
    }

    // ------------------------------------------------------------
    // SQL tools
    // ------------------------------------------------------------

    /**
     * Replace tokens in an SQL query.
     *
     * @param string $sql SQL query with tokens
     * @param array $args Key-value pairs to replace in the query
     *
     * @return string SQL query with replaced tokens
     */
    public static function prepareSQL($sql, $args = [])
    {
        $keys = ['_DB_PREFIX_'];
        $values = [_DB_PREFIX_];

        foreach ($args as $k => $v) {
            $keys[] = $k;
            $values[] = $v;
        }

        return str_replace($keys, $values, $sql);
    }

    // ------------------------------------------------------------
    // SQL queries
    // ------------------------------------------------------------

    /**
     * Returns an array of image size names to be used in a <select> box. Array (assoc) with the value of each key as value for it
     *
     * @return array
     */
    public static function getAvailableImageSizes()
    {
        $sizes = [];
        $tableName = 'home';
        $tableName = (method_exists(get_class(new \ImageType()), 'getFormattedName')) ? \ImageType::getFormattedName($tableName) : $tableName . '_default';
        $sql = "
        SELECT
            `name` AS DF_GS_IMAGE_SIZE,
            `name`
        FROM
            `_DB_PREFIX_image_type`
        WHERE
            `products` = 1
        ORDER BY
            CASE
                WHEN name = '" . $tableName . "' THEN '1'
            END DESC;
        ";

        $imageSizes = \Db::getInstance()->ExecuteS(self::prepareSQL($sql));
        foreach ($imageSizes as $size) {
            $sizes[$size['DF_GS_IMAGE_SIZE']] = $size;
        }

        return $sizes;
    }

    /**
     * Generate all hash ID keys for each active language and currency.
     *
     * Builds labels and keys for single-price and multiprice modes.
     *
     * @return array Array of hash ID keys and labels
     */
    public static function getHashidKeys()
    {
        $hashidKeys = [];
        $context = \Context::getContext();
        $currencies = \Currency::getCurrenciesByIdShop($context->shop->id);
        $languages = \Language::getLanguages(true, $context->shop->id);
        foreach ($languages as $language) {
            if (0 === (int) $language['active']) {
                continue;
            }
            foreach ($currencies as $currency) {
                if (1 === (int) $currency['deleted'] || 0 === (int) $currency['active']) {
                    continue;
                }
                $currencyIso = strtoupper($currency['iso_code']);
                $langFullIso = strtoupper($language['language_code']);
                $hashidKeys[] = [
                    'currency' => $currencyIso,
                    'language' => $langFullIso,
                    'label' => $currencyIso . ' - ' . $langFullIso,
                    'labelMultiprice' => $langFullIso,
                    'key' => 'DF_HASHID_' . $currencyIso . '_' . $langFullIso,
                    'keyMultiprice' => 'DF_HASHID_' . $langFullIso,
                ];
            }
        }

        return $hashidKeys;
    }

    /**
     * Returns an assoc. array. Keys are currency ISO codes. Values are currency
     * names.
     *
     * @return array
     */
    public static function getAvailableCurrencies()
    {
        $currencies = [];
        $sql = '
      SELECT
        `iso_code`,
        `name`
      FROM
        `_DB_PREFIX_currency`
      WHERE
        `active` = 1
      ORDER BY `name`;
    ';

        foreach (\Db::getInstance()->ExecuteS(self::prepareSQL($sql)) as $currency) {
            $currencies[$currency['iso_code']] = $currency;
        }

        return $currencies;
    }

    /**
     * 1.[5].0.13 | 1.5.[0].5 | 1.5.0.[1]
     * 1.[6].0.6  | 1.5.[1].0 | 1.5.0.[5]
     *
     * @param string $minVersion Minimum version to compare with the current version
     *
     * @return bool true if current version is greater than or equal to $minVersion
     */
    public static function versionGte($minVersion)
    {
        $version = explode('.', _PS_VERSION_);
        $minVersion = explode('.', $minVersion);

        foreach ($version as $index => $value) {
            if ((int) $value > (int) $minVersion[$index]) {
                return true;
            } elseif ((int) $value < (int) $minVersion[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the features selected by user
     *
     * @param array $features
     * @param array $selectedKeys
     *
     * @return array
     */
    public static function getSelectedFeatures($features, $selectedKeys)
    {
        $selectedFeatures = [];

        foreach ($features as $key => $value) {
            if (in_array((string) $key, $selectedKeys, true)) {
                $selectedFeatures[] = $value;
            }
        }

        return $selectedFeatures;
    }

    /**
     * Returns the features of a product
     *
     * @param int $idShop shop ID
     * @param int $idLang language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getFeatureKeysForShopAndLang($idShop, $idLang)
    {
        $sql = '
      SELECT fl.name, fl.id_feature

      FROM
        _DB_PREFIX_feature_shop fs
        LEFT JOIN _DB_PREFIX_feature_lang fl
          ON (fl.id_feature = fs.id_feature AND fl.id_lang = _ID_LANG_)

      WHERE
        fs.id_shop = _ID_SHOP_
    ';

        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) $idLang,
            '_ID_SHOP_' => (int) $idShop,
        ]);
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $names = [];
        foreach ($result as $elem) {
            $names[$elem['id_feature']] = $elem['name'];
        }

        return $names;
    }

    /**
     * Returns the features of a product
     *
     * @param int $idShop shop ID
     * @param int $idLang language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAttributeKeysForShopAndLang($idShop, $idLang)
    {
        $sql = '
      SELECT agl.name

      FROM
        _DB_PREFIX_attribute_group_shop ags
        LEFT JOIN _DB_PREFIX_attribute_group_lang agl
          ON (agl.id_attribute_group = ags.id_attribute_group AND agl.id_lang = _ID_LANG_)

      WHERE
        ags.id_shop = _ID_SHOP_
    ';

        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) $idLang,
            '_ID_SHOP_' => (int) $idShop,
        ]);

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $names = [];
        foreach ($result as $elem) {
            $names[] = $elem['name'];
        }

        return $names;
    }

    /**
     * Returns all images for a product variation.
     *
     * @param int $idProduct ID of the product
     * @param int $idProductAttribute id of the Product attribute
     *
     * @return array Array of image IDs
     */
    public static function getVariationImages($idProduct, $idProductAttribute)
    {
        $sql = '
            SELECT DISTINCT pai.id_image
            FROM ' . _DB_PREFIX_ . 'product_attribute_image pai
            INNER JOIN ' . _DB_PREFIX_ . 'image i ON (i.id_image = pai.id_image AND i.id_product = ' . (int) $idProduct . ')
            WHERE pai.id_product_attribute = ' . (int) $idProductAttribute . '
            ORDER BY i.position ASC
        ';
        $sql = self::prepareSQL($sql, []);
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        return $result ? array_map('intval', array_column($result, 'id_image')) : [];
    }

    /**
     * Returns the first image for a product variation.
     *
     * @param int $idProduct ID of the product
     * @param int $idProductAttribute id of the Product attribute
     *
     * @return int|null
     */
    public static function getVariationImg($idProduct, $idProductAttribute)
    {
        $images = self::getVariationImages($idProduct, $idProductAttribute);
        return !empty($images) ? $images[0] : null;
    }

    /**
     * Returns the features of a product
     *
     * @param int $idProduct product ID
     * @param int $idLang language ID
     * @param array $featureKeys keys of the features associated to the product
     *
     * @return array of rows (assoc arrays)
     */
    public static function getFeaturesForProduct($idProduct, $idLang, $featureKeys)
    {
        $sql = '
      SELECT fl.name,
             fvl.value

      FROM
        _DB_PREFIX_feature_product fp
        LEFT JOIN _DB_PREFIX_feature_lang fl
          ON (fl.id_feature = fp.id_feature AND fl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_feature_value_lang fvl
          ON (fvl.id_feature_value = fp.id_feature_value AND fvl.id_lang = _ID_LANG_)

      WHERE
        fp.id_product = _ID_PRODUCT
    ';

        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) $idLang,
            '_ID_PRODUCT' => (int) $idProduct,
        ]);

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $features = [];

        foreach ($result as $elem) {
            if (in_array($elem['name'], $featureKeys, true)) {
                $features[$elem['name']][] = $elem['value'];
            }
        }

        return $features;
    }

    /**
     * Returns the product combination attributes
     *
     * @param int $variationId product Attribute ID
     * @param int $idLang language ID
     * @param string $attrLimit attribute groups IDs
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAttributesByCombination($variationId, $idLang, $attrLimit = '')
    {
        if ($variationId > 0) {
            $sql = 'SELECT pc.id_product_attribute,
                    pal.name,
                    pagl.name AS group_name

            FROM
            _DB_PREFIX_product_attribute_combination pc
                LEFT JOIN _DB_PREFIX_attribute pa
                    ON pc.id_attribute = pa.id_attribute
                LEFT JOIN _DB_PREFIX_attribute_lang pal
                    ON (pc.id_attribute = pal.id_attribute AND pal.id_lang = _ID_LANG_)
                LEFT JOIN _DB_PREFIX_attribute_group_lang pagl
                    ON (pagl.id_attribute_group = pa.id_attribute_group AND pagl.id_lang = _ID_LANG_)
            WHERE
            pc.id_product_attribute = _VARIATION_ID';

            if (strlen(trim($attrLimit)) !== 0) {
                $sql .= ' AND pa.id_attribute_group IN (' . pSQL($attrLimit) . ')';
            }
            $sql = self::prepareSQL($sql, [
                '_ID_LANG_' => (int) $idLang,
                '_VARIATION_ID' => (int) $variationId,
            ]);

            return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } else {
            return [];
        }
    }

    /**
     * Get attributes name
     *
     * @param array $attributesIds attributes ids
     * @param int $idLang Language ID
     *
     * @return array
     */
    public static function getAttributesName($attributesIds, $idLang)
    {
        $sql = '
            SELECT pag.id_attribute_group, pagl.name
            FROM _DB_PREFIX_attribute_group pag
            LEFT JOIN _DB_PREFIX_attribute_group_lang pagl ON pag.id_attribute_group = pagl.id_attribute_group  AND pagl.id_lang = _ID_LANG_
            WHERE pag.id_attribute_group IN (' . implode(',', $attributesIds) . ')
            ';

        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) $idLang,
        ]);

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Retrieve available products information for a specific language.
     *
     * This method fetches products with their associated data including:
     * - Basic product information (reference, EAN13, UPC, ISBN, etc.)
     * - Product descriptions and meta information
     * - Stock information
     * - Category information
     * - Manufacturer details
     * - Product tags
     * - Variation information
     *
     * @param int $idLang Language ID to retrieve product information in
     * @param bool $checkLeadership Whether to check if product is a variant group leader (true by default)
     * @param int|bool $limit Maximum number of products to retrieve (false for no limit)
     * @param int|bool $offset Offset for pagination (false for no offset)
     * @param array|null $ids Specific product IDs to retrieve (not implemented in current code)
     *
     * @return array|false|\mysqli_result|\PDOStatement|resource|null Array of products with their associated information
     */
    public static function getAvailableProducts($idLang, $checkLeadership = true, $limit = false, $offset = false, $ids = null)
    {
        if (null === $ids) {
            $ids = self::getAvailableProductsIds($idLang, $limit, $offset);
            if (empty($ids)) {
                return [];
            }
        }

        $query = new \DbQuery();

        if (self::versionGte('1.7.7.0')) {
            $query->select('p.mpn AS mpn');
        } else {
            $query->select('p.reference AS mpn');
        }

        // Product table fields
        $query->select('p.ean13 AS ean13, p.upc, p.reference');

        // Product shop table fields
        $query->select('product_shop.id_product, product_shop.show_price, product_shop.available_for_order, product_shop.minimal_quantity AS minimum_quantity, product_shop.date_add AS creation_date');
        $query->join(\Shop::addSqlAssociation('product', 'p'));

        $query->from('product', 'p');

        if (self::versionGte('1.7.0.0')) {
            $query->select('p.isbn');
        }

        // Variation fields
        $query->select('0 AS id_product_attribute');

        // Product image table fields
        $imsCoverField = self::versionGte('1.5.1.0') ? 'ims.cover = 1' : 'im.cover = 1';

        $query->select('MIN(ims.id_image) AS id_image');
        $query->select('GROUP_CONCAT(DISTINCT ims_all.id_image ORDER BY im.position ASC) AS all_image_ids');
        $query->leftJoin('image', 'im', 'im.id_product = p.id_product');
        $query->leftJoin(
            'image_shop',
            'ims',
            'im.id_image = ims.id_image
            AND ims.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ')
            AND ' . $imsCoverField
        );
        // Join image_shop for all images (filtered by shop) to ensure we only get shop-available images
        $query->leftJoin(
            'image_shop',
            'ims_all',
            'im.id_image = ims_all.id_image
            AND ims_all.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ')'
        );

        // Product supplier reference
        // See: [PR#181](https://github.com/doofinder/doofinder-prestashop/pull/181)
        $query->select('COALESCE(psp.product_supplier_reference, p.supplier_reference) as supplier_reference, psp.product_supplier_reference AS variation_supplier_reference');
        $query->leftJoin(
            'product_supplier',
            'psp',
            'p.id_supplier = psp.id_supplier
            AND p.`id_product` = psp.`id_product`
            AND psp.`id_product_attribute` = 0'
        );

        $query->select('MIN(sa.out_of_stock) as out_of_stock, MIN(sa.quantity) as stock_quantity');
        $query->leftJoin(
            'stock_available',
            'sa',
            'product_shop.id_product = sa.id_product
            AND sa.id_product_attribute = 0
            AND (sa.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ')
            OR (sa.id_shop = 0 AND sa.id_shop_group = ' . (int) \Shop::getContextShopGroupID() . '))'
        );

        $query->select('pl.name, pl.description, pl.description_short, pl.meta_title, pl.meta_description, pl.link_rewrite');
        $query->leftJoin(
            'product_lang',
            'pl',
            'p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . (int) $idLang . \Shop::addSqlRestrictionOnLang('pl')
        );

        // Category default
        $idCategoryField = self::versionGte('1.5.0.9') ? 'product_shop.id_category_default' : 'p.id_category_default';
        $query->select('default_category_lang.name as main_category, default_category_lang.link_rewrite AS cat_link_rew');
        $query->select($idCategoryField . ' as id_category_default');
        $query->leftJoin(
            'category_lang',
            'default_category_lang',
            $idCategoryField . ' = default_category_lang.id_category
            AND default_category_lang.id_lang = ' . (int) $idLang . '
            AND default_category_lang.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ')'
        );

        // Manufacturer
        $query->select('m.name AS manufacturer');
        $query->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');

        $query->select('s.name AS supplier_name');
        $query->leftJoin('supplier', 's', 's.`id_supplier` = p.`id_supplier`');

        $query->select('GROUP_CONCAT(tag.name ORDER BY tag.name) AS tags');
        $query->leftJoin(
            'product_tag',
            'pt',
            'pt.id_product = product_shop.id_product'
        );
        $query->leftJoin(
            'tag',
            'tag',
            'pt.`id_tag` = tag.`id_tag` AND tag.`id_lang` = ' . (int) $idLang
        );

        $query->select('GROUP_CONCAT(DISTINCT(cl.id_category) ORDER BY cl.id_category) AS category_ids');
        $query->leftJoin(
            'category_product',
            'cp',
            'cp.id_product = product_shop.id_product'
        );
        $query->leftJoin(
            'category_lang',
            'cl',
            'cl.`id_category` = cp.`id_category`
            AND cl.`id_shop` IN (' . implode(', ', \Shop::getContextListShopID()) . ')
            AND cl.`id_lang` = ' . (int) $idLang . '
            AND cp.id_category > 2'
        );

        $query->select('IFNULL(vc.count, 0) as variant_count');
        if ($checkLeadership) {
            $query->select('IF(NOT ISNULL(vc.count) AND vc.count > 0,true, false) as df_group_leader');
        } else {
            $query->select('false as df_group_leader');
        }

        $query->join('LEFT JOIN (
                SELECT
                    id_product,
                    count(*) as count
                FROM
                    ' . _DB_PREFIX_ . 'product_attribute pas
                GROUP BY
                    id_product
            ) vc ON vc.id_product = product_shop.id_product');

        $query->select('null AS variation_reference, null AS variation_mpn,
            null AS variation_ean13, null AS variation_upc, null AS variation_image_id');

        if (self::versionGte('1.5.1.0')) {
            $query->where('product_shop.`active` = 1');
        } else {
            $query->where('p.`active` = 1');
        }

        if (self::versionGte('1.5.1.0')) {
            $query->where("product_shop.`visibility` IN ('search', 'both')");
        } elseif (self::versionGte('1.5.0.9')) {
            $query->where("p.`visibility` IN ('search', 'both')");
        }

        $query->where('product_shop.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ')');

        $query->where('product_shop.id_product IN (' . implode(',', array_map('intval', $ids)) . ')');

        $query->orderBy('product_shop.id_product');
        $query->groupBy('product_shop.id_product');

        try {
            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
            if (!$result) {
                $result = \Db::getInstance()->executeS($query);
            }
        } catch (\PrestaShopException $e) {
            $result = \Db::getInstance()->executeS($query);
        }

        return $result;
    }

    /**
     * Returns an array of "root" categories in Prestashop for a language.
     * The results are cached in a protected, static variable.
     *
     * @return array
     */
    public static function getRootCategoryIds($idLang)
    {
        if (null === self::$rootCategoryIds) {
            self::$rootCategoryIds = [];
            foreach (\Category::getRootCategories($idLang) as $category) {
                self::$rootCategoryIds[] = $category['id_category'];
            }
        }

        return self::$rootCategoryIds;
    }

    /**
     * Returns the path to the first, no root ancestor category for the selected
     * category ID in a language for the selected shop.
     * Results are cached by category ID.
     *
     * @param int $idCategory Category ID
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     * @param bool $full return full category path
     *
     * @return string
     */
    public static function getCategoryPath($idCategory, $idLang, $idShop, $full = true)
    {
        if (isset(self::$cachedCategoryPaths[$idCategory])) {
            return self::$cachedCategoryPaths[$idCategory];
        }

        $excludedIds = self::getRootCategoryIds($idLang);

        $sql = '
      SELECT
        cl.name
      FROM
        _DB_PREFIX_category_lang cl INNER JOIN _DB_PREFIX_category parent
          ON (parent.id_category = cl.id_category)
        INNER JOIN _DB_PREFIX_category_shop cs ON (cs.id_category = parent.id_category),
        _DB_PREFIX_category node
      WHERE
        node.nleft BETWEEN parent.nleft AND parent.nright
        AND node.id_category = _ID_CATEGORY_
        AND cl.id_shop = _ID_SHOP_
        AND cl.id_lang = _ID_LANG_
        AND cs.id_shop = _ID_SHOP_
        AND parent.level_depth <> 0
        AND parent.active = 1 ';

        if (count($excludedIds) > 0 && '' !== (string) $excludedIds[0]) {
            $sql .= 'AND parent.id_category NOT IN (_EXCLUDED_IDS_) ';
        }

        $sql .= 'ORDER BY
        parent.nleft
      ;';

        $sql = self::prepareSQL($sql, [
            '_ID_CATEGORY_' => (int) $idCategory,
            '_ID_SHOP_' => (int) $idShop,
            '_ID_LANG_' => (int) $idLang,
            '_EXCLUDED_IDS_' => pSQL(implode(',', $excludedIds)),
        ]);

        $sql = str_replace("\'", "'", $sql);

        $path = [];
        foreach (\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $row) {
            $path[] = str_replace([self::CATEGORY_TREE_SEPARATOR, self::LIST_SEPARATOR], '-', $row['name']);
        }

        if ($full) {
            $path = implode(self::CATEGORY_TREE_SEPARATOR, $path);
        } else {
            $path = end($path);
        }

        $path = self::cleanString($path);
        self::$cachedCategoryPaths[$idCategory] = $path;

        return $path;
    }

    /**
     * Returns an array containing the paths for categories for a product in a language for the selected shop.
     *
     * @param string $categoryIds Comma separated list of category IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     *
     * @return array
     */
    public static function getCategoryLinksById($categoryIds, $idLang, $idShop)
    {
        $categoryIds = explode(',', $categoryIds);
        $link = \Context::getContext()->link;
        $urls = [];

        foreach ($categoryIds as $category_id) {
            $category = new \Category((int) $category_id, $idLang, $idShop);
            if (!\Validate::isLoadedObject($category)) {
                continue;
            }
            if ((bool) \Configuration::get('PS_REWRITING_SETTINGS')) {
                $categoryLink = $link->getCategoryLink($category);
                $urls[] = trim(parse_url($categoryLink, PHP_URL_PATH), '/');
            } else {
                $urls[] = $category_id;
            }
        }

        return $urls;
    }

    /**
     * Returns a string with all the paths for categories for a product in a language
     * for the selected shop. If $flat == false then returns them as an array.
     *
     * @param int $idProduct Product ID
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     * @param bool $flat optional implode values
     *
     * @return string or array
     */
    public static function getCategoriesForProductIdAndLanguage($idProduct, $idLang, $idShop, $flat = true)
    {
        $useMainCategory = (bool) self::cfg($idShop, 'DF_FEED_MAINCATEGORY_PATH', DoofinderConstants::YES);

        $sql = '
      SELECT DISTINCT
        c.id_category,
        c.id_parent,
        c.level_depth,
        c.nleft,
        c.nright
      FROM
        _DB_PREFIX_category c
        INNER JOIN _DB_PREFIX_category_product cp
          ON (c.id_category = cp.id_category AND cp.id_product = _ID_PRODUCT_)
        INNER JOIN _DB_PREFIX_category_shop cs
          ON (c.id_category = cs.id_category AND cs.id_shop = _ID_SHOP_)
          _MAIN_CATEGORY_INNER_
      WHERE
        c.active = 1
        _MAIN_CATEGORY_WHERE_
      ORDER BY
        c.nleft DESC,
        c.nright ASC;
    ';
        $mainCategoryInner = '';
        $mainCategoryWhere = '';

        if ($useMainCategory) {
            $mainInnerSql = 'INNER JOIN _DB_PREFIX_product_shop ps '
                . 'ON (ps.id_product = _ID_PRODUCT_ AND ps.id_shop = _ID_SHOP_)';
            $mainCategoryInner = self::prepareSQL(
                $mainInnerSql,
                ['_ID_PRODUCT_' => (int) $idProduct, '_ID_SHOP_' => (int) $idShop]
            );
            $mainCategoryWhere = 'AND ps.id_category_default = cp.id_category';
        }

        $sql = self::prepareSQL($sql, [
            '_ID_PRODUCT_' => (int) $idProduct,
            '_MAIN_CATEGORY_INNER_' => pSQL($mainCategoryInner),
            '_MAIN_CATEGORY_WHERE_' => pSQL($mainCategoryWhere),
            '_ID_SHOP_' => (int) $idShop,
        ]);

        $sql = str_replace("\'", "'", $sql);

        $categories = [];
        $lastSaved = 0;
        $idCategory0 = 0;
        $nleft0 = 0;
        $nright0 = 0;
        $useFullPath = (bool) self::cfg($idShop, 'DF_FEED_FULL_PATH', DoofinderConstants::YES);

        foreach (\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $i => $row) {
            if (!$i) {
                $idCategory0 = (int) $row['id_category'];
                $nleft0 = (int) $row['nleft'];
                $nright0 = (int) $row['nright'];
            } else {
                $idCategory1 = (int) $row['id_category'];
                $nleft1 = (int) $row['nleft'];
                $nright1 = (int) $row['nright'];

                if ($nleft1 < $nleft0 && $nright1 > $nright0) {
                    // $idCategory1 is an ancestor of $idCategory0
                } elseif ($nleft1 < $nleft0 && $nright1 > $nright0) {
                    // $idCategory1 is a child of $idCategory0 so be replace $idCategory0
                    $idCategory0 = $idCategory1;
                    $nleft0 = $nleft1;
                    $nright0 = $nright1;
                } else {
                    // $idCategory1 is not a relative of $idCategory0 so we save
                    // $idCategory0 now and make $idCategory1 the current category.
                    $categories[] = self::getCategoryPath($idCategory0, $idLang, $idShop, $useFullPath);
                    $lastSaved = $idCategory0;

                    $idCategory0 = $idCategory1;
                    $nleft0 = $nleft1;
                    $nright0 = $nright1;
                }
            }
        } // endforeach

        if ($lastSaved != $idCategory0) {
            // The last item in loop didn't trigger the $idCategory0 saving event.
            $categories[] = self::getCategoryPath($idCategory0, $idLang, $idShop, $useFullPath);
        }

        return $flat ? implode(self::LIST_SEPARATOR, $categories) : $categories;
    }

    /**
     * Check if product has variants
     *
     * @param int $idProduct product ID
     *
     * @return int
     */
    public static function hasAttributes($idProduct)
    {
        return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
            SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            ' . \Shop::addSqlAssociation('product_attribute', 'pa') . '
            WHERE pa.`id_product` = ' . (int) $idProduct
        );
    }

    /**
     * Check if product has attributes
     *
     * @param int $idProduct product ID
     * @param string $attributeGroupsId attribute groups IDs
     *
     * @return array|false
     */
    public static function hasProductAttributes($idProduct, $attributeGroupsId)
    {
        if (!$attributeGroupsId) {
            return false;
        }

        $attributeGroupsId = implode(',', array_map('intval', explode(',', $attributeGroupsId)));

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT a.id_attribute_group
            FROM `' . _DB_PREFIX_ . 'product` p
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON p.id_product = pa.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pa.id_product_attribute = pac.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON pac.id_attribute = a.id_attribute
            WHERE p.id_product = ' . (int) $idProduct . ' AND id_attribute_group IN (' . $attributeGroupsId . ')
            GROUP BY a.id_attribute_group
            '
        );

        return array_column($result, 'id_attribute_group');
    }

    /**
     * Get category IDs for a specific language and active status.
     *
     * This method retrieves all category IDs that are not root categories (id_parent != 0)
     * for a given language. Optionally filters by active status.
     *
     * @param int $idLang Language ID (0 for all languages)
     * @param int|bool $limit Whether to limit the result (default: false)
     * @param int|bool $offset Whether to offset the result (default: false)
     * @param bool $active Whether to filter by active categories only (default: true)
     *
     * @return array Array of category IDs
     */
    public static function getCategories($idLang, $limit = false, $offset = false, $active = true)
    {
        $query = new \DbQuery();
        $query->select('c.id_category');
        $query->from('category', 'c');
        $query->join(\Shop::addSqlAssociation('category', 'c'));
        $query->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category`' . \Shop::addSqlRestrictionOnLang('cl'));
        $query->where('c.id_parent != 0');

        if ($idLang) {
            $query->where('cl.`id_lang` = ' . (int) $idLang);
        }

        if ($active) {
            $query->where('c.`active` = 1');
        }

        if (!$idLang) {
            $query->groupBy('c.id_category');
        }

        if ($limit) {
            $query->limit((int) $limit, (int) $offset);
        }

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        return array_column($result, 'id_category');
    }

    /**
     * Get CMS page IDs for a specific language, shop, and active status.
     *
     * This method retrieves all CMS page IDs using PrestaShop's built-in
     * getCMSPages method and returns only the IDs.
     *
     * @param int $idLang Language ID
     * @param int|null $idShop Shop ID
     * @param int|bool $limit Whether to limit the result (default: false)
     * @param int|bool $offset Whether to offset the result (default: false)
     * @param bool $active Whether to filter by active pages only (default: true)
     *
     * @return array Array of CMS page IDs
     */
    public static function getCmsPages($idLang, $idShop, $limit = false, $offset = false, $active = true)
    {
        $query = new \DbQuery();
        $query->select('*');
        $query->from('cms', 'c');

        if ($idLang) {
            if ($idShop) {
                $query->innerJoin('cms_lang', 'l', 'c.id_cms = l.id_cms AND l.id_lang = ' . (int) $idLang . ' AND l.id_shop = ' . (int) $idShop);
            } else {
                $query->innerJoin('cms_lang', 'l', 'c.id_cms = l.id_cms AND l.id_lang = ' . (int) $idLang);
            }
        }

        if ($idShop) {
            $query->innerJoin('cms_shop', 'cs', 'c.id_cms = cs.id_cms AND cs.id_shop = ' . (int) $idShop);
        }

        if ($active) {
            $query->where('c.active = 1');
        }

        $query->orderBy('position');

        if ($limit) {
            $query->limit((int) $limit, (int) $offset);
        }

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        return array_column($result, 'id_cms');
    }

    //
    // Text Tools
    //

    /**
     * Clean and normalize a URL string.
     *
     * This method performs comprehensive URL cleaning including:
     * - Removing line breaks and normalizing whitespace
     * - Properly encoding URL components
     * - Adding protocol if missing
     * - Encoding query parameters
     * - Filtering invalid UTF-8 characters
     *
     * @param string $text The URL to clean
     *
     * @return string The cleaned and normalized URL
     */
    public static function cleanURL($text)
    {
        $text = trim($text);
        $text = preg_replace("/\r|\n/", '', $text);
        $text = explode('?', $text);
        $text = str_replace(self::TXT_SEPARATOR, '%7C', $text);

        $baseUrl = [];
        foreach (explode('/', $text[0]) as $part) {
            if (in_array(strtolower($part), ['http:', 'https:', ''])) {
                $baseUrl[] = $part;
            } else {
                $baseUrl[] = rawurlencode($part);
            }
        }
        $text[0] = implode('/', $baseUrl);

        if (stristr($text[0], 'http://') === false && stristr($text[0], 'https://') === false) {
            $text[0] = 'http://' . $text[0];
        }

        if (isset($text[1])) {
            $params = [];
            foreach (explode('&', $text[1]) as $param) {
                $param = explode('=', $param);
                foreach ($param as $idx => $part) {
                    $param[$idx] = urlencode($part);
                }
                $params[] = implode('=', $param);
            }
            $text[1] = implode('&', $params);
        }

        $text = implode('?', $text);

        $text = preg_replace(self::VALID_UTF8, '$1', $text);

        return urldecode($text);
    }

    /**
     * Clean and normalize a text string for safe use in feeds.
     *
     * This method performs comprehensive text cleaning including:
     * - Removing control characters
     * - Stripping HTML and PHP tags
     * - Normalizing whitespace
     * - Replacing text separators with HTML entities
     * - Removing backslashes
     * - Filtering invalid UTF-8 sequences
     *
     * @param string|null $text The text to clean
     *
     * @return string|null The cleaned text, or null if input was null
     */
    public static function cleanString($text)
    {
        if (is_null($text)) {
            return null;
        }

        // Replace control characters with spaces
        $text = preg_replace('/[^\P{C}]+/u', ' ', $text);

        // Remove HTML and PHP tags
        $text = strip_tags($text);

        // Normalize whitespace by replacing consecutive spaces with a single space
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove leading and trailing whitespace
        $text = trim($text);

        // Replace text separator with HTML entity for pipe character
        $text = str_replace(self::TXT_SEPARATOR, '&#124;', $text);

        // // Would remove backslashes from escaped chars
        // $text = stripcslashes($text);

        // Remove escaping as for CSV we escape with "
        $text = str_replace('\\', '', $text);

        // Filter out invalid UTF-8 sequences using a predefined regex pattern
        return preg_replace(self::VALID_UTF8, '$1', $text);
    }

    /**
     * Split references by adding spaces between non-digit and digit characters.
     *
     * This method adds spaces between non-digit and digit characters in a string,
     * which helps with reference parsing and searching.
     *
     * @param mixed $text The text to process
     *
     * @return mixed The processed text with spaces added, or original value if not a string
     */
    public static function splitReferences($text)
    {
        if (!is_string($text)) {
            return $text;
        }

        return preg_replace("/([^\d\s])([\d])/", '$1 $2', $text);
    }

    //
    // Things from request / URL Tools

    /**
     * Returns a boolean value for the $parameter specified. If the parameter does
     * not exist (is NULL) then $default is returned instead.
     *
     * This method supports multiple ways of saying YES or NO.
     */
    public static function getBooleanFromRequest($parameter, $default = false)
    {
        $v = \Tools::getValue($parameter, null);

        if ($v === null) {
            return $default;
        }

        switch (strtolower($v)) {
            case 'false':
            case 'off':
            case 'no':
                return false;
            case 'true':
            case 'on':
            case 'yes':
            case 'si':
                return true;
            default:
                return (bool) $v;
        }
    }

    /**
     * Returns a Language object based on the 'language' parameter from the
     * request. If no language is found then the default one from the current
     * context is used.
     *
     * @return \Language
     */
    public static function getLanguageFromRequest()
    {
        $context = \Context::getContext();
        $idLang = \Tools::getValue('language', $context->language->id);

        if (!is_numeric($idLang)) {
            $idLang = \Language::getIdByIso($idLang);
        }

        return new \Language($idLang);
    }

    /**
     * Returns a Currency object with the currency configured in the plugin for
     * the given ISO language $code parameter. If no currency is found the method
     * returns the default one for the current context.
     *
     * @param string $code ISO language code
     *
     * @return \Currency
     */
    public static function getCurrencyForLanguage($code)
    {
        $optname = 'DF_GS_CURRENCY_' . strtoupper($code);
        $idCurrency = \Configuration::get($optname);

        if ($idCurrency) {
            return new \Currency(\Currency::getIdByIsoCode($idCurrency));
        }

        return new \Currency(\Context::getContext()->currency->id);
    }

    /**
     * Returns a Currency object based on the 'currency' parameter from the
     * request. If no currency is found then the function searches one in the
     * plugin configuration based on the $lang parameter. If none is configured
     * then the default one from the current context is used.
     *
     * @param \Language $lang
     *
     * @return \Currency
     */
    public static function getCurrencyForLanguageFromRequest(\Language $lang)
    {
        if ($idCurrency = \Tools::getValue('currency')) {
            if (is_numeric($idCurrency)) {
                $idCurrency = (int) $idCurrency;
            } else {
                $idCurrency = \Currency::getIdByIsoCode(strtoupper($idCurrency));
            }
        } else {
            $optname = 'DF_GS_CURRENCY_' . strtoupper($lang->iso_code);
            $idCurrency = \Currency::getIdByIsoCode(\Configuration::get($optname));
        }

        if (!$idCurrency) {
            $context = \Context::getContext();
            $idCurrency = $context->currency->id;
        }

        return new \Currency($idCurrency);
    }

    /**
     * Returns a HTTP(S) link for a file from this module.
     *
     * @param string $path file path relative to this module's root
     * @param bool $ssl return a secure URL
     *
     * @return string URL
     */
    public static function getModuleLink($path, $ssl = false)
    {
        $context = \Context::getContext();
        $base = (($ssl && \Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://') . $context->shop->domain;

        return $base . _MODULE_DIR_ . basename(dirname(__FILE__)) . '/' . $path;
    }

    /**
     * Fix URL by adding http:// protocol if missing.
     *
     * This method checks if a URL has a protocol (http:// or https://) and
     * adds http:// if none is present.
     *
     * @param string $url The URL to fix
     *
     * @return string The URL with protocol added if needed
     */
    public static function fixURL($url)
    {
        if (preg_match('~^https?://~', $url) === 0) {
            $url = "http://$url";
        }

        return $url;
    }

    /**
     * Generate a complete image URL for a product image.
     *
     * This method creates a full URL for a product image using PrestaShop's
     * link generator and ensures the URL has a proper protocol.
     *
     * @param int $idProduct Product ID
     * @param int $idImage Image ID
     * @param string $linkRewrite URL-friendly product name
     * @param string $imageSize Image size identifier
     *
     * @return string The complete image URL, or empty string if parameters are invalid
     */
    public static function getImageLink($idProduct, $idImage, $linkRewrite, $imageSize)
    {
        $context = \Context::getContext();
        if (empty($idProduct) || empty($idImage)) {
            return '';
        }
        $url = $context->link->getImageLink($linkRewrite, "$idProduct-$idImage", $imageSize);

        return self::fixURL($url);
    }

    /**
     * Wraps a Javascript piece of code if no script tag is found.
     *
     * @param string $jsCode javascript code
     *
     * @return string
     */
    public static function fixScriptTag($jsCode)
    {
        $result = trim(preg_replace('/<!--(.*?)-->/', '', $jsCode));
        if (strlen($result) && !preg_match('/<script([^>]*?)>/', $result)) {
            $result = "<script type=\"text/javascript\">\n$result\n</script>";
        }

        return $result;
    }

    /**
     * Wraps a CSS piece of code if no <style> tag is found.
     *
     * @param string $cssCode CSS code
     *
     * @return string
     */
    public static function fixStyleTag($cssCode)
    {
        $result = trim(preg_replace('/<!--(.*?)-->/', '', $cssCode));
        if (strlen($result) && !preg_match('/<style([^>]*?)>/', $result)) {
            $result = "<style type=\"text/css\">\n$result\n</style>";
        }

        return $result;
    }

    /**
     * Flush buffers
     *
     * @return void
     */
    public static function flush()
    {
        if (function_exists('flush')) {
            @flush();
        }
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
    }

    /**
     * Returns a configuration value for a $key and a $idShop. If the value is
     * not found (or it's false) then returns a $default value.
     *
     * @param int $idShop shop id
     * @param string $key configuration variable name
     * @param mixed $default default value
     *
     * @return mixed
     */
    public static function cfg($idShop, $key, $default = false)
    {
        $v = \Configuration::get($key, null, null, $idShop);
        if ($v === false) {
            return $default;
        }

        return $v;
    }

    /**
     * Retrieves a configuration value for a specific shop.
     *
     * This method checks whether the given key exists in the configuration for the specified shop.
     * If the key exists for the shop, it returns the corresponding value.
     * If not, returns the specified `$default` value.
     * However, only if the provided shop ID matches the default shop it also checks whether the key exists globally (if the shop specific value is not found).
     * If the key does not exist in any of the conditions, it returns the provided default value.
     *
     * @param string $key the configuration key to retrieve
     * @param int $idShop the ID of the shop for which the configuration should be retrieved
     * @param mixed $default the default value to return if the key is not found (default is an empty string)
     *
     * @return string|false the configuration value for the given key and shop, or the default value if not found
     */
    public static function getConfigByShop($key, $idShop, $default = '')
    {
        if (\Configuration::hasKey($key, null, null, $idShop)) {
            return \Configuration::get($key, null, null, $idShop, $default);
        } elseif (\Configuration::hasKey($key) && (int) \Configuration::get('PS_SHOP_DEFAULT') === (int) $idShop) {
            return \Configuration::get($key);
        }

        return $default;
    }

    /**
     * Callback function to apply HTML entities to string values in an array.
     *
     * This function is used as a callback with array_walk_recursive to convert
     * all string values in a nested array to HTML entities for safe JSON encoding.
     *
     * @param mixed &$item The array item (passed by reference)
     * @param mixed $key The array key (not used)
     *
     * @return void
     */
    public static function walkApplyHtmlEntities(&$item, $key)
    {
        if (is_string($item)) {
            $item = htmlentities($item);
        }
    }

    /**
     * Safely encode data to JSON with HTML entity handling.
     *
     * This method applies HTML entities to all string values in the data array,
     * then encodes to JSON and decodes HTML entities to produce clean output.
     * Also fixes escaped forward slashes in the JSON output.
     *
     * @param mixed $data The data to encode to JSON
     *
     * @return string JSON encoded string with HTML entities properly handled
     */
    public static function jsonEncode($data)
    {
        array_walk_recursive($data, [get_class(), 'walkApplyHtmlEntities']);

        return str_replace('\\/', '/', html_entity_decode(json_encode($data)));
    }

    /**
     * Validate the security token for Doofinder API access.
     *
     * This method compares the provided security hash with the configured
     * Doofinder API key. If they don't match and an API key is configured,
     * it sends a 403 Forbidden response and exits the script.
     *
     * @param string $dfsecHash The security hash to validate
     *
     * @return void Exits with 403 error if validation fails
     */
    public static function validateSecurityToken($dfsecHash)
    {
        $doofinderApiKey = \Configuration::get('DF_API_KEY');
        if (!empty($doofinderApiKey) && $dfsecHash != $doofinderApiKey) {
            header('HTTP/1.1 403 Forbidden', true, 403);
            $msgError = 'Forbidden access.'
                . ' Maybe security token missed.'
                . ' Please check on your doofinder module'
                . ' configuration page the new URL'
                . ' for your feed';
            exit($msgError);
        }
    }

    /**
     * Get all price information for a product variant.
     *
     * This method retrieves the regular price, onsale price, and multiprice
     * information for a specific product variant (combination).
     *
     * For B2B cases, the input data structure for $customerGroupsData is:
     * [
     *    ['id_group' => 4, 'id_customer' => 120, 'price_display_method' => 1],
     *    ['id_group' => 5, 'id_customer' => 251, 'price_display_method' => 0],
     *    ...
     * ]
     * The price_display_method field (0 = with tax, 1 = without tax) determines whether
     * prices for each customer group should include taxes.
     *
     * @param int $idProduct Product ID
     * @param int $idProductAttribute Product attribute/variant ID
     * @param bool $includeTaxes Whether to include taxes in prices
     * @param array $currencies Array of currency information for multiprice calculation
     * @param array $customerGroupsData List of customer groups to consider for price calculation (optional)
     *
     * @return array Array containing price, onsale_price, multiprice, and id_product_attribute
     */
    public static function getVariantPrices($idProduct, $idProductAttribute, $includeTaxes, $currencies, $customerGroupsData = [])
    {
        $variantPrice = self::getPrice($idProduct, $includeTaxes, $idProductAttribute);
        $variantOnsalePrice = self::getOnsalePrice($idProduct, $includeTaxes, $idProductAttribute);
        $variantMultiprice = self::getMultiprice($idProduct, $includeTaxes, $currencies, $idProductAttribute, $customerGroupsData);

        return [
            'price' => $variantPrice,
            'onsale_price' => $variantOnsalePrice,
            'multiprice' => $variantMultiprice,
            'id_product_attribute' => $idProductAttribute,
        ];
    }

    /**
     * Check if a product is a parent product (not a variant).
     *
     * A product is considered a parent if it has an id_product_attribute field
     * that is numeric and equals 0, indicating it's the base product rather than a variant.
     *
     * @param array $product Product data array
     *
     * @return bool True if the product is a parent, false otherwise
     */
    public static function isParent($product)
    {
        return isset($product['id_product_attribute']) && is_numeric($product['id_product_attribute']) && (int) $product['id_product_attribute'] === 0;
    }

    /**
     * Get the regular price for a product or variant.
     *
     * This method retrieves the standard price for a product, optionally for a specific variant.
     * It can include or exclude taxes and apply proper decimal rounding based on currency precision.
     *
     * @param int $productId Product ID
     * @param bool $includeTaxes Whether to include taxes in the price
     * @param int|null $variantId Product variant ID (null for base product)
     * @param bool $applyDecimalRounding Whether to apply currency-specific decimal rounding
     * @param int|null $customerId Customer ID representing the Customer Group (defaults to null)
     *
     * @return float The product price
     */
    public static function getPrice($productId, $includeTaxes, $variantId = null, $applyDecimalRounding = true, $customerId = null)
    {
        return self::calculatePrice($productId, $includeTaxes, $variantId, $applyDecimalRounding, $customerId, false);
    }

    /**
     * Get the onsale price for a product or variant.
     *
     * This method retrieves the sale/discounted price for a product, optionally for a specific variant.
     * It can include or exclude taxes and apply proper decimal rounding based on currency precision.
     *
     * @param int $productId Product ID
     * @param bool $includeTaxes Whether to include taxes in the price
     * @param int|null $variantId Product variant ID (null for base product)
     * @param bool $applyDecimalRounding Whether to apply currency-specific decimal rounding
     * @param int|null $customerId Customer ID representing the Customer Group (defaults to null)
     *
     * @return float The product onsale price
     */
    public static function getOnsalePrice($productId, $includeTaxes, $variantId = null, $applyDecimalRounding = true, $customerId = null)
    {
        return self::calculatePrice($productId, $includeTaxes, $variantId, $applyDecimalRounding, $customerId);
    }

    /**
     * Given a product and a list of currencies, returns the multiprice map.
     *
     * For B2B cases, the input data structure for $customerGroupsData is:
     * [
     *    ['id_group' => 4, 'id_customer' => 120, 'price_display_method' => 1],
     *    ['id_group' => 5, 'id_customer' => 251, 'price_display_method' => 0],
     *    ...
     * ]
     * The price_display_method field (0 = with tax, 1 = without tax) determines whether
     * prices for each customer group should include taxes. If not provided, it will be
     * retrieved from the customer group settings.
     *
     * An example of a value for this field is
     * ["EUR" => ["price" => 5, "sale_price" => 3], "GBP" => ["price" => 4.3, "sale_price" => 2.7]]
     * for a list containing two currencies ["EUR", "GBP"].
     * In case of B2B prices it would be:
     * ["EUR" => ["price" => 5, "sale_price" => 3], "EUR_5" => ["price" => 4, "sale_price" => 2], ...]
     *
     * Note: The $includeTaxes parameter is used for base currency prices (non-B2B).
     * For B2B customer group prices, each group's price_display_method setting is used instead.
     *
     * @param int $productId Id of the product to calculate the multiprice for
     * @param bool $includeTaxes Determines if taxes have to be included in the calculated prices (for base currency prices only)
     * @param array $currencies List of currencies to consider for the multiprice calculation
     * @param int $variantId When specified, the multiprice will be calculated for that variant
     * @param array $customerGroupsData List of customer groups to consider for price calculation
     *
     * @return array
     */
    public static function getMultiprice($productId, $includeTaxes, $currencies, $variantId = null, $customerGroupsData = [])
    {
        $multiprice = [];
        $price = self::getPrice($productId, $includeTaxes, $variantId, false);
        $onsale_price = self::getOnsalePrice($productId, $includeTaxes, $variantId, false);

        if (empty($customerGroupsData)) {
            $hasCustomerGroups = false;
        } else {
            $hasCustomerGroups = true;
            $isPrestaShop15 = !self::versionGte('1.6.0.0');
            $cachedCustomers = [];
            $customerGroupTaxSettings = [];

            if ($isPrestaShop15) {
                foreach ($customerGroupsData as $customerGroupData) {
                    $customerId = $customerGroupData['id_customer'];
                    if (!isset($cachedCustomers[$customerId])) {
                        $cachedCustomers[$customerId] = new \Customer($customerId);
                    }
                }
            }

            // Pre-calculate tax settings for each customer group (outside currency loop)
            foreach ($customerGroupsData as $customerGroupData) {
                $groupId = $customerGroupData['id_group'];
                // Note: price_display_method is reversed (0 = with tax, 1 = without tax)
                $customerGroupTaxSettings[$groupId] = isset($customerGroupData['price_display_method'])
                    ? !(bool) $customerGroupData['price_display_method']
                    : $includeTaxes;
            }

            // Pre-fetch all customer group prices once (outside currency loop) to minimize Product::getPriceStatic calls
            $customerGroupPrices = [];
            $customerGroupOnsalePrices = [];
            foreach ($customerGroupsData as $customerGroupData) {
                $groupId = $customerGroupData['id_group'];
                $customerId = $customerGroupData['id_customer'];
                $groupIncludeTaxes = $customerGroupTaxSettings[$groupId];

                // Set customer context for PrestaShop 1.5 before fetching prices
                if ($isPrestaShop15) {
                    \Context::getContext()->customer = $cachedCustomers[$customerId];
                }

                $customerGroupPrices[$groupId] = self::getPrice($productId, $groupIncludeTaxes, $variantId, false, $customerId);
                $customerGroupOnsalePrices[$groupId] = self::getOnsalePrice($productId, $groupIncludeTaxes, $variantId, false, $customerId);
            }

            // Reset customer context for PrestaShop 1.5
            if ($isPrestaShop15) {
                \Context::getContext()->customer = null;
            }
        }

        foreach ($currencies as $currency) {
            if ($currency['deleted'] == 0 && $currency['active'] == 1) {
                // Backward compatibility with PrestaShop 1.5
                $currencyId = !empty($currency['id']) ? $currency['id'] : $currency['id_currency'];
                $decimals = self::getCurrencyPrecision($currencyId);
                $convertedPrice = \Tools::ps_round(\Tools::convertPrice($price, $currency), $decimals);
                $convertedOnsalePrice = \Tools::ps_round(\Tools::convertPrice($onsale_price, $currency), $decimals);
                $currencyCode = $currency['iso_code'];
                $pricesMap = ['price' => $convertedPrice];

                if ($convertedPrice != $convertedOnsalePrice) {
                    $pricesMap['sale_price'] = $convertedOnsalePrice;
                }

                $multiprice[$currencyCode] = $pricesMap;

                if ($hasCustomerGroups) {
                    foreach ($customerGroupsData as $customerGroupData) {
                        $groupId = $customerGroupData['id_group'];
                        $customerGroupPrice = $customerGroupPrices[$groupId];
                        $customerGroupOnsalePrice = $customerGroupOnsalePrices[$groupId];

                        $convertedPrice = \Tools::ps_round(\Tools::convertPrice($customerGroupPrice, $currency), $decimals);
                        $convertedOnsalePrice = \Tools::ps_round(\Tools::convertPrice($customerGroupOnsalePrice, $currency), $decimals);
                        $pricesMap = ['price' => $convertedPrice];
                        if ($convertedPrice !== $convertedOnsalePrice) {
                            $pricesMap['sale_price'] = $convertedOnsalePrice;
                        }
                        $multiprice[$currencyCode . '_' . $groupId] = $pricesMap;
                    }
                }
            }
        }

        return $multiprice;
    }

    /**
     * Returns the API Key without the region part.
     *
     * @return string
     */
    public static function getFormattedApiKey()
    {
        $apiKey = explode('-', \Configuration::get('DF_API_KEY'));

        return end($apiKey);
    }

    /**
     * Transforms a given multiprice map into the correct format to be processed
     * to the format required by the CSV feed.
     *
     * For an input like
     * ["EUR" => ["price" => 5, "sale_price" => 3], "GBP" => ["price" => 4.3, "sale_price" => 2.7]]
     * it would produce
     * "price_EUR=5/sale_price_EUR=3/price_GBP=4.3/sale_price_GBP=2.7"
     *
     * @param array $multiprice Multiprice map to be formatted
     *
     * @return string
     */
    public static function getFormattedMultiprice($multiprice)
    {
        $multiprices = [];

        foreach ($multiprice as $currency => $prices) {
            foreach ($prices as $price_name => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                $multiprices[] = $currency . '_' . $price_name . '=' . $value;
            }
        }

        return implode('/', $multiprices);
    }

    /**
     * Convert text to a URL-friendly slug.
     *
     * This method converts text to a slug by:
     * - Replacing non-letter/digit characters with hyphens
     * - Trimming hyphens from edges
     * - Transliterating to ASCII
     * - Converting to lowercase
     * - Removing unwanted characters
     * - Returning 'n-a' for empty or null input
     *
     * @param string|null $text The text to slugify
     *
     * @return string The URL-friendly slug
     */
    public static function slugify($text)
    {
        if (null === $text) {
            return 'n-a';
        }

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

    /**
     * Checks if the module is enabled for a specific shop.
     *
     * This method determines whether the Doofinder module is enabled for a given shop by:
     * - Checking a cache key to see if the result is already stored.
     * - If not cached, querying the database to check if the module is assigned to the specified shop.
     * - Storing the result in the cache to optimize future calls.
     *
     * @param int $shopId the ID of the shop to check
     *
     * @return bool true if the module is enabled for the shop, false otherwise
     */
    public static function isModuleEnabledInShop($shopId)
    {
        $moduleName = DoofinderConstants::NAME;
        $cacheKey = 'Module::isEnabled' . $moduleName . '_' . $shopId;
        if (!\Cache::isStored($cacheKey)) {
            $active = false;
            $idModule = \Module::getModuleIdByName($moduleName);
            if (\Db::getInstance()->getValue('SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module_shop` WHERE `id_module` = ' . (int) $idModule . ' AND `id_shop` = ' . (int) $shopId)) {
                $active = true;
            }
            \Cache::store($cacheKey, (bool) $active);

            return (bool) $active;
        }

        return \Cache::retrieve($cacheKey);
    }

    /**
     * Check if a string contains a substring (PHP 8+ compatibility).
     *
     * This method provides a compatibility layer for the str_contains function
     * introduced in PHP 8.0. It uses the native function if available, otherwise
     * falls back to strpos for older PHP versions.
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     *
     * @return bool True if the substring is found, false otherwise
     */
    public static function str_contains($haystack, $needle)
    {
        if (function_exists('str_contains')) {
            return \str_contains($haystack, $needle);
        }

        return '' === $needle || false !== strpos($haystack, $needle);
    }

    /**
     * Validates the Installation ID.
     *
     * @param string $installationId
     *
     * @return bool
     */
    public static function validateInstallationId($installationId)
    {
        if (!empty($installationId) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $installationId)) {
            return true;
        }

        return false;
    }

    /**
     * Validates the Api Key.
     *
     * @param string $apiKey
     *
     * @return bool
     */
    public static function validateApiKey($apiKey)
    {
        if (!empty($apiKey) && preg_match('/^[a-zA-Z0-9]{3}-[a-fA-F0-9]{40}$/', $apiKey)) {
            return true;
        }

        return false;
    }

    /**
     * Get the ids of the available products.
     * When the catalog is large, this is much faster than get the products by offset and limit.
     *
     * @param int $idLang The language ID
     * @param int|false $limit The maximum number of products to return
     * @param int|false $offset The offset for pagination
     */
    public static function getAvailableProductsIds($idLang, $limit, $offset)
    {
        $idQuery = new \DbQuery();
        $idQuery->select('product_shop.id_product');
        $idQuery->from('product', 'p');
        $idQuery->join(\Shop::addSqlAssociation('product', 'p'));

        if (self::versionGte('1.5.1.0')) {
            $idQuery->where('product_shop.`active` = 1');
            $idQuery->where("product_shop.`visibility` IN ('search', 'both')");
        } else {
            $idQuery->where('p.`active` = 1');
            if (self::versionGte('1.5.0.9')) {
                $idQuery->where("p.`visibility` IN ('search', 'both')");
            }
        }

        $idQuery->where('product_shop.id_shop IN (' . implode(', ', \Shop::getContextListShopID()) . ')');
        $idQuery->orderBy('product_shop.id_product');

        if ($limit) {
            $idQuery->limit((int) $limit, (int) $offset);
        }

        try {
            $response = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($idQuery);
            // If the result is false or null, fallback to default DB instance
            if (!$response) {
                $response = \Db::getInstance()->executeS($idQuery);
            }
        } catch (\PrestaShopException $e) {
            // Fallback to default DB instance on exception
            $response = \Db::getInstance()->executeS($idQuery);
        }

        $productsIds = [];
        foreach ($response as $product) {
            $productsIds[] = $product['id_product'];
        }

        return $productsIds;
    }

    /**
     * Get currency precision compatible from PrestaShop 1.5 to 9
     *
     * @param int|\Currency $currency Currency ID or object
     *
     * @return int Number of decimals to round to
     */
    public static function getCurrencyPrecision($currency)
    {
        // Ensure we have a Currency object
        if (!($currency instanceof \Currency)) {
            $currency = new \Currency((int) $currency);
        }

        // PrestaShop 1.7.6+ has a precision property/method
        if (property_exists($currency, 'precision')) {
            return (int) $currency->precision;
        }

        if (method_exists($currency, 'getPrecision') && is_callable([$currency, 'getPrecision'])) {
            return (int) $currency->getPrecision();
        }

        // For PrestaShop < 1.7.6, use "decimals" boolean flag
        if (property_exists($currency, 'decimals')) {
            return ((bool) $currency->decimals) ? 2 : 0;
        }

        // Fallback (it shouldn't happen)
        return 2;
    }

    /**
     * Get the additional customer groups and default customers.
     *
     * This method returns a list of customer groups and their default customers.
     * The customer groups are the ones that are not native to PrestaShop.
     * The default customers are the ones that are associated with the customer groups.
     * The price_display_method field indicates whether prices should include tax (1) or exclude tax (0).
     *
     * Result: [['id_group' => 4, 'id_customer' => 120, 'price_display_method' => 1], ['id_group' => 5, 'id_customer' => 251, 'price_display_method' => 0], ...]
     *
     * @return array
     */
    public static function getAdditionalCustomerGroupsAndDefaultCustomers()
    {
        if (self::$cachedCustomerGroupsData !== null) {
            return self::$cachedCustomerGroupsData;
        }

        if (!\Group::isCurrentlyUsed()) {
            return [];
        }

        $unidentifiedGroup = (int) \Configuration::get('PS_UNIDENTIFIED_GROUP');
        $guestGroup = (int) \Configuration::get('PS_GUEST_GROUP');
        $customerGroup = (int) \Configuration::get('PS_CUSTOMER_GROUP');
        $nativeGroups = [$unidentifiedGroup, $guestGroup, $customerGroup];

        $query = new \DbQuery();
        $query->select('cg.id_group, MIN(cg.id_customer) AS id_customer, g.price_display_method');
        $query->from('customer_group', 'cg');
        $query->leftJoin('group', 'g', 'cg.id_group = g.id_group');
        $query->where('cg.id_group NOT IN (' . implode(',', $nativeGroups) . ')');
        $query->groupBy('cg.id_group, g.price_display_method');

        $customerGroupsData = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        // To guarantee compatibility with PrestaShop 1.5
        self::$cachedCustomerGroupsData = array_filter($customerGroupsData, function ($groupData) {
            return is_numeric($groupData['id_group']) && is_numeric($groupData['id_customer']);
        });

        return self::$cachedCustomerGroupsData;
    }

    /**
     * Retrieves whether prices are shown for a given customer group.
     *
     * This function checks the "show_prices" setting of the group.
     *
     * @param int $idGroup the ID of the customer group
     *
     * @return bool true if prices are shown for this group, false otherwise
     */
    public static function getCustomerGroupPriceVisibility($idGroup)
    {
        $query = new \DbQuery();
        $query->select('g.show_prices');
        $query->from('group', 'g');
        $query->where('g.id_group = ' . (int) $idGroup);

        return (bool) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Try to convert a date string to ISO 8601 format (e.g. 2025-12-22T13:03:31Z). If the conversion fails, return the original date string unconverted.
     *
     * @param string $dateString The date string to convert
     *
     * @return string The ISO 8601 formatted date
     */
    public static function dateStringToIso8601($dateString)
    {
        $timestamp = strtotime($dateString);
        if (false === $timestamp) {
            return self::cleanString($dateString);
        }

        return date('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * Get the regular price/onsale price for a product or variant.
     *
     * This method retrieves the regular price or sale/discounted price for a product, optionally for a specific variant depending on
     * the $useReduction parameter.
     * It can include or exclude taxes and apply proper decimal rounding based on currency precision.
     *
     * @param int $productId Product ID
     * @param bool $includeTaxes Whether to include taxes in the price
     * @param int|null $variantId Product variant ID (null for base product)
     * @param bool $applyDecimalRounding Whether to apply currency-specific decimal rounding
     * @param int|null $customerId Customer ID representing the Customer Group (defaults to null)
     * @param bool $useReduction Whether to use the reduction price or the regular price
     *
     * @return float The product regular price or onsale price
     */
    private static function calculatePrice($productId, $includeTaxes, $variantId = null, $applyDecimalRounding = true, $customerId = null, $useReduction = true)
    {
        if (is_null($customerId)) {
            // We have to specify almost all parameters to avoid different prices calculations if an user is logged in.
            // See https://github.com/PrestaShop/PrestaShop/blob/8.1.0/classes/Product.php#L3602.
            // $use_group_reduction and $use_customer_price must remain as false for these cases.
            $specificPriceOutput = null;

            return \Product::getPriceStatic(
                $productId,
                $includeTaxes,
                $variantId,
                $applyDecimalRounding ? self::getCurrencyPrecision(\Context::getContext()->currency->id) : 6,
                null,
                false,
                $useReduction,
                1,
                false,
                $customerId,
                null,
                null,
                $specificPriceOutput,
                true,
                false,
                null,
                false
            );
        }

        return \Product::getPriceStatic(
            $productId,
            $includeTaxes,
            $variantId,
            $applyDecimalRounding ? self::getCurrencyPrecision(\Context::getContext()->currency->id) : 6,
            null,
            false,
            $useReduction,
            1,
            false,
            $customerId
        );
    }
}
