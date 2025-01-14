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

class DfTools
{
    const CATEGORY_SEPARATOR = ' %% ';
    const CATEGORY_TREE_SEPARATOR = '>';
    const TXT_SEPARATOR = '|';
    // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
    const VALID_UTF8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF]
    [\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F]
    [\x80-\xBF]{2})|./x';

    protected static $rootCategoryIds;
    protected static $cachedCategoryPaths = [];

    //
    // Validation
    //

    public static function isBasicValue($v)
    {
        return $v && \Validate::isGenericName($v);
    }

    //
    // SQL Tools
    //

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

    public static function limitSQL($sql, $limit = false, $offset = false)
    {
        if (false !== $limit && is_numeric($limit)) {
            $sql .= ' LIMIT ' . (int) $limit;

            if (false !== $offset && is_numeric($offset)) {
                $sql .= ' OFFSET ' . (int) $offset;
            }
        }

        return $sql;
    }

    //
    // SQL Queries

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
     * @param  [type] $minVersion [description]
     *
     * @return [type]              [description]
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

    public static function getSelectedFeatures($features, $selectedKeys)
    {
        /**
         * Returns the features selected by user
         *
         * @param array features
         *
         * @return array of rows (assoc arrays)
         */
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
            '_ID_LANG_' => (int) pSQL($idLang),
            '_ID_SHOP_' => (int) pSQL($idShop),
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
            '_ID_LANG_' => (int) pSQL($idLang),
            '_ID_SHOP_' => (int) pSQL($idShop),
        ]);

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $names = [];
        foreach ($result as $elem) {
            $names[] = $elem['name'];
        }

        return $names;
    }

    /**
     * Returns the features of a product
     *
     * @param int $idProduct ID of the product
     * @param int $idProductAttribute id of the Product attribute
     *
     * @return array of rows (assoc arrays)
     */
    public static function getVariationImg($idProduct, $idProductAttribute)
    {
        $sql = '
      SELECT i.id_image
            from
            (
            select pa.id_product, pa.id_product_attribute,paic.id_attribute,min(i.position) as min_position
            from _DB_PREFIX_product_attribute pa
             inner join _DB_PREFIX_product_attribute_image pai
               on pai.id_product_attribute = pa.id_product_attribute
             inner join  _DB_PREFIX_product_attribute_combination paic
               on pai.id_product_attribute = paic.id_product_attribute
             inner join _DB_PREFIX_image i
               on pai.id_image = i.id_image
            where pa.id_product = ' . (int) pSQL($idProduct) . '
                and pa.id_product_attribute = ' . (int) pSQL($idProductAttribute) . '
            group by pa.id_product, pa.id_product_attribute,paic.id_attribute
            ) as P
            inner join _DB_PREFIX_image i
             on i.id_product = P.id_product and i.position =  P.min_position
            ';
        $sql = self::prepareSQL($sql, []);
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (isset($result[0])) {
            return $result[0]['id_image'];
        } else {
            return '';
        }
    }

    /**
     * Returns the features of a product
     *
     * @param int $idProduct product ID
     * @param int language ID
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
            '_ID_LANG_' => (int) pSQL($idLang),
            '_ID_PRODUCT' => (int) pSQL($idProduct),
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
     * Returns the product variation attributes
     *
     * @param int $variationId product variation Attribute ID
     * @param int $idLang language ID
     * @param array $attributeKeys keys of the attributes associated to the product
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAttributesForProductVariation($variationId, $idLang, $attributeKeys)
    {
        if (is_numeric($variationId) && $variationId > 0) {
            $sql = '
        SELECT pc.id_product_attribute,
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
          pc.id_product_attribute = _VARIATION_ID
      ';

            $sql = self::prepareSQL($sql, [
                '_ID_LANG_' => (int) pSQL($idLang),
                '_VARIATION_ID' => (int) pSQL($variationId),
            ]);

            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } else {
            $result = [];
        }

        if (count($attributeKeys) > 0) {
            $attributes = array_fill(0, count($attributeKeys), '');
        } else {
            $attributes = [];
        }

        foreach ($result as $elem) {
            if (array_search($elem['group_name'], $attributeKeys) !== false) {
                $attributes[array_search($elem['group_name'], $attributeKeys)] = $elem['name'];
            }
        }

        return $attributes;
    }

    /**
     * Returns the product combination attributes
     *
     * @param int product Attribute ID
     * @param bool attribute groups IDs
     * @param int language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAttributesByCombination($variationId, $idLang, $attrLimit = false)
    {
        if (isset($variationId) && $variationId > 0) {
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

            if ($attrLimit) {
                $sql .= ' AND pa.id_attribute_group IN (' . pSQL($attrLimit) . ')';
            }
            $sql = self::prepareSQL($sql, [
                '_ID_LANG_' => (int) pSQL($idLang),
                '_VARIATION_ID' => (int) pSQL($variationId),
            ]);

            return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } else {
            return [];
        }
    }

    /**
     * Get attributes name
     *
     * @param array attributes ids
     * @param int Language ID
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
            '_ID_LANG_' => (int) pSQL($idLang),
        ]);

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Returns the products available for a language
     *
     * @param int language ID
     * @param int Optional. Default false. Number of products to get.
     * @param int Optional. Default false. Offset to start the select from.
     * @param string Optional. Fields to select.
     * @param array Optional. Filter product ids.
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAvailableProductsForLanguage($idLang, $idShop, $limit = false, $offset = false, $ids = null)
    {
        $Shop = new \Shop($idShop);

        $isbn = '';
        $isbnPa = '';
        if (self::versionGte('1.7.0.0')) {
            $isbn = 'p.isbn,';
            if (self::cfg($idShop, 'DF_SHOW_PRODUCT_VARIATIONS') == 1) {
                $isbnPa = 'IF(isnull(pa.id_product), p.isbn , pa.isbn) AS isbn,';
            }
        }

        $mpn = 'p.reference as mpn,';
        $mpnPa = 'pa.reference AS variation_mpn,';
        if (self::versionGte('1.7.7.0')) {
            $mpn = 'p.mpn AS mpn,';
            if (self::cfg($idShop, 'DF_SHOW_PRODUCT_VARIATIONS') == 1) {
                $mpnPa = 'pa.mpn AS variation_mpn,';
            }
        }

        $sql = '
      SELECT
        ps.id_product,
        ps.show_price,
        cl.name as main_category,
        __ID_CATEGORY_DEFAULT_FIELD__,
        m.name AS manufacturer,
        ' . $mpn . '
        p.ean13 AS ean13,
        ' . $isbn . "
        p.upc,
        p.reference,
        psp.product_supplier_reference AS supplier_reference,
        pl.name,
        pl.description,
        pl.description_short,
        pl.meta_title,
        pl.meta_keywords,
        pl.meta_description,
        GROUP_CONCAT(tag.name SEPARATOR ',') AS tags,
        pl.link_rewrite,
        cl.link_rewrite AS cat_link_rew,
        im.id_image,
        ps.available_for_order,
        sa.out_of_stock,
        sa.quantity as stock_quantity
      FROM
        _DB_PREFIX_product p
        INNER JOIN _DB_PREFIX_product_shop ps
          ON (p.id_product = ps.id_product AND ps.id_shop = _ID_SHOP_)
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON (p.id_product = pl.id_product AND pl.id_shop = _ID_SHOP_ AND pl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_manufacturer m
          ON (p.id_manufacturer = m.id_manufacturer)
        LEFT JOIN _DB_PREFIX_category_lang cl
          ON (__ID_CATEGORY_DEFAULT_FIELD__ = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
        LEFT JOIN (_DB_PREFIX_tag tag
            INNER JOIN _DB_PREFIX_product_tag pt ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
          ON (pt.id_product = p.id_product)
        LEFT JOIN _DB_PREFIX_stock_available sa
          ON (p.id_product = sa.id_product AND sa.id_product_attribute = 0
            AND (sa.id_shop = _ID_SHOP_ OR
            (sa.id_shop = 0 AND sa.id_shop_group = _ID_SHOPGROUP_)))
        LEFT JOIN _DB_PREFIX_product_supplier psp
          ON (p.id_product = psp.id_product AND psp.id_product_attribute = 0)
      WHERE
        __IS_ACTIVE__
        __VISIBILITY__
        __PRODUCT_IDS__
      GROUP BY
        p.id_product
      ORDER BY
        p.id_product
    ";

        $sqlVariations = "
      SELECT
        ps.id_product,
        ps.show_price,
        pa.id_product_attribute,
        pa.reference AS variation_reference,
        psp.product_supplier_reference AS variation_supplier_reference,
        $mpnPa
        pa.ean13 AS variation_ean13,
        pa.upc AS variation_upc,
        pa_im.id_image AS variation_image_id,
        cl.name as main_category,
        __ID_CATEGORY_DEFAULT_FIELD__,
        m.name AS manufacturer,
        $mpn
        p.ean13 AS ean13,
        $isbnPa
        p.upc AS upc,
        p.reference AS reference,
        p.supplier_reference AS supplier_reference,
        0 AS df_group_leader,
        pl.name,
        pl.description,
        pl.description_short,
        pl.meta_title,
        pl.meta_keywords,
        pl.meta_description,
        GROUP_CONCAT(tag.name SEPARATOR ',') AS tags,
        pl.link_rewrite,
        cl.link_rewrite AS cat_link_rew,
        im.id_image,
        ps.available_for_order,
        sa.out_of_stock,
        sa.quantity as stock_quantity
      FROM
        _DB_PREFIX_product p
        INNER JOIN _DB_PREFIX_product_shop ps
          ON (p.id_product = ps.id_product AND ps.id_shop = _ID_SHOP_)
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON (p.id_product = pl.id_product AND pl.id_shop = _ID_SHOP_ AND pl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_manufacturer m
          ON (p.id_manufacturer = m.id_manufacturer)
        LEFT JOIN _DB_PREFIX_category_lang cl
          ON (__ID_CATEGORY_DEFAULT_FIELD__ = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
        LEFT OUTER JOIN _DB_PREFIX_product_attribute pa
          ON (p.id_product = pa.id_product)
        LEFT JOIN _DB_PREFIX_product_supplier psp
          ON (p.id_product = psp.id_product AND pa.id_product_attribute = psp.id_product_attribute)
        LEFT JOIN _DB_PREFIX_product_attribute_shop pas
          ON (pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = _ID_SHOP_)
        LEFT JOIN _DB_PREFIX_product_attribute_image pa_im
          ON (pa_im.id_product_attribute = pa.id_product_attribute)
        LEFT JOIN (_DB_PREFIX_tag tag
            INNER JOIN _DB_PREFIX_product_tag pt ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
          ON (pt.id_product = p.id_product)
        LEFT JOIN _DB_PREFIX_stock_available sa
          ON (p.id_product = sa.id_product
            AND sa.id_product_attribute = IF(isnull(pa.id_product), 0, pa.id_product_attribute)
            AND (sa.id_shop = _ID_SHOP_ OR
            (sa.id_shop = 0 AND sa.id_shop_group = _ID_SHOPGROUP_)))
      WHERE
        __IS_ACTIVE__
        __VISIBILITY__
        __PRODUCT_IDS__
        AND pa.id_product_attribute is not null
      GROUP BY pa.id_product_attribute, p.id_product
      UNION
      SELECT
        ps.id_product,
        ps.show_price,
        0,
        null AS variation_reference,
        psp.product_supplier_reference AS variation_supplier_reference,
        null AS variation_mpn,
        null AS variation_ean13,
        null AS variation_upc,
        null AS variation_image_id,
        cl.name as main_category,
        __ID_CATEGORY_DEFAULT_FIELD__ as id_category_default,
        m.name AS manufacturer,
        $mpn
        p.ean13 AS ean13,
        $isbn
        p.upc,
        p.reference,
        p.supplier_reference,
        1 AS df_group_leader,
        pl.name,
        pl.description,
        pl.description_short,
        pl.meta_title,
        pl.meta_keywords,
        pl.meta_description,
        GROUP_CONCAT(tag.name SEPARATOR ',') AS tags,
        pl.link_rewrite,
        cl.link_rewrite AS cat_link_rew,
        im.id_image,
        ps.available_for_order,
        sa.out_of_stock,
        sa.quantity as stock_quantity
      FROM
        _DB_PREFIX_product p
        INNER JOIN _DB_PREFIX_product_shop ps
          ON (p.id_product = ps.id_product AND ps.id_shop = _ID_SHOP_)
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON (p.id_product = pl.id_product AND pl.id_shop = _ID_SHOP_ AND pl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_manufacturer m
          ON (p.id_manufacturer = m.id_manufacturer)
        LEFT JOIN _DB_PREFIX_category_lang cl
          ON (__ID_CATEGORY_DEFAULT_FIELD__ = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
        LEFT JOIN (_DB_PREFIX_tag tag
            INNER JOIN _DB_PREFIX_product_tag pt ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
          ON (pt.id_product = p.id_product)
        LEFT JOIN _DB_PREFIX_stock_available sa
          ON (p.id_product = sa.id_product AND sa.id_product_attribute = 0
            AND (sa.id_shop = _ID_SHOP_ OR
            (sa.id_shop = 0 AND sa.id_shop_group = _ID_SHOPGROUP_)))
        LEFT JOIN _DB_PREFIX_product_supplier psp
          ON (p.id_product = psp.id_product AND psp.id_product_attribute = 0)
      WHERE
        __IS_ACTIVE__
        __VISIBILITY__
        __PRODUCT_IDS__
      GROUP BY
        p.id_product
      ORDER BY
        id_product
    ";

        if (self::cfg($idShop, 'DF_SHOW_PRODUCT_VARIATIONS') == 1) {
            $sql = $sqlVariations;
        }

        // MIN: 1.5.0.9
        $idCategoryDefault = self::versionGte('1.5.0.9') ? 'ps.id_category_default' : 'p.id_category_default';
        // MIN: 1.5.1.0
        $imsCover = self::versionGte('1.5.1.0') ? 'ims.cover = 1' : 'im.cover = 1';
        $isActive = self::versionGte('1.5.1.0') ? 'ps.active = 1' : 'p.active = 1';

        if (self::versionGte('1.5.1.0')) {
            $visibility = "AND ps.visibility IN ('search', 'both')";
        } elseif (self::versionGte('1.5.0.9')) {
            $visibility = "AND p.visibility IN ('search', 'both')";
        } else {
            $visibility = '';
        }

        if (is_array($ids) && count($ids)) {
            $productIds = 'AND p.id_product IN (' . implode(',', $ids) . ')';
        } else {
            $productIds = '';
        }

        $sql = self::limitSQL($sql, $limit, $offset);
        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) pSQL($idLang),
            '_ID_SHOP_' => (int) pSQL($idShop),
            '_ID_SHOPGROUP_' => (int) pSQL($Shop->id_shop_group),
            '_IMS_COVER_' => (string) pSQL($imsCover),
            '__ID_CATEGORY_DEFAULT_FIELD__' => (string) pSQL($idCategoryDefault),
            '__IS_ACTIVE__' => (string) pSQL($isActive),
            '__VISIBILITY__' => (string) pSQL($visibility),
            '__PRODUCT_IDS__' => (string) pSQL($productIds),
        ]);

        $sql = str_replace("\'", "'", $sql);

        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
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
            '_ID_CATEGORY_' => (int) pSQL($idCategory),
            '_ID_SHOP_' => (int) pSQL($idShop),
            '_ID_LANG_' => (int) pSQL($idLang),
            '_EXCLUDED_IDS_' => (string) pSQL(implode(',', $excludedIds)),
        ]);

        $sql = str_replace("\'", "'", $sql);

        $path = [];
        foreach (\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $row) {
            $path[] = str_replace([self::CATEGORY_TREE_SEPARATOR, self::CATEGORY_SEPARATOR], '-', $row['name']);
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
                ['_ID_PRODUCT_' => (int) pSQL($idProduct), '_ID_SHOP_' => (int) pSQL($idShop)]
            );
            $mainCategoryWhere = 'AND ps.id_category_default = cp.id_category';
        }

        $sql = self::prepareSQL($sql, [
            '_ID_PRODUCT_' => (int) pSQL($idProduct),
            '_MAIN_CATEGORY_INNER_' => (string) pSQL($mainCategoryInner),
            '_MAIN_CATEGORY_WHERE_' => (string) pSQL($mainCategoryWhere),
            '_ID_SHOP_' => (int) pSQL($idShop),
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

        return $flat ? implode(self::CATEGORY_SEPARATOR, $categories) : $categories;
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
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
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
     * @return array
     */
    public static function hasProductAttributes($idProduct, $attributeGroupsId)
    {
        if (!$attributeGroupsId) {
            return false;
        }

        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT a.id_attribute_group
            FROM `' . _DB_PREFIX_ . 'product` p
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON p.id_product = pa.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pa.id_product_attribute = pac.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON pac.id_attribute = a.id_attribute
            WHERE p.id_product = ' . pSQL($idProduct) . ' AND id_attribute_group IN ( ' . pSQL($attributeGroupsId) . ')
            GROUP BY a.id_attribute_group
            '
        );

        return array_column($result, 'id_attribute_group');
    }

    public static function getCategories($idLang, $active = true)
    {
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT c.id_category
            FROM `' . _DB_PREFIX_ . 'category` c
            ' . \Shop::addSqlAssociation('category', 'c') . '
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON c.`id_category` = cl.`id_category`' . \Shop::addSqlRestrictionOnLang('cl') . '
            WHERE id_parent != 0
            ' . ($idLang ? 'AND `id_lang` = ' . (int) $idLang : '') . '
            ' . ($active ? 'AND `active` = 1' : '') . '
            ' . (!$idLang ? 'GROUP BY c.id_category' : '')
        );

        return array_column($result, 'id_category');
    }

    public static function getCmsPages($idLang, $idShop, $active = true)
    {
        $result = \CMS::getCMSPages($idLang, null, $active, $idShop);

        return array_column($result, 'id_cms');
    }

    //
    // Text Tools
    //

    public static function truncateText($text, $length)
    {
        $l = (int) $length;
        $c = trim(preg_replace('/\s+/', ' ', $text));

        if (strlen($c) <= $l) {
            return $c;
        }

        $n = 0;
        $r = '';
        foreach (explode(' ', $c) as $p) {
            if (($tmp = $n + strlen($p) + 1) <= $l) {
                $n = $tmp;
                $r .= " $p";
            } else {
                break;
            }
        }

        return $r;
    }

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

    public static function cleanString($text)
    {
        if (is_null($text)) {
            return null;
        }

        $text = preg_replace('/[^\P{C}]+/u', ' ', $text);
        $text = str_replace(["\t", "\r", "\n"], ' ', $text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        $text = preg_replace('/^["\']+/', '', $text); // remove first quotes
        $text = str_replace(self::TXT_SEPARATOR, '&#124;', $text);

        return preg_replace(self::VALID_UTF8, '$1', $text);
    }

    /**
     * Cleans a string in an extreme way to deal with conflictive strings like
     * titles that contains references that can be searched with or without
     * certain characters.
     *
     * TODO: Make it configurable from the admin.
     */
    public static function cleanReferences($text)
    {
        $forbidden = ['-'];
        $text = (is_string($text)) ? $text : '';

        return str_replace($forbidden, '', $text);
    }

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

    public static function fixURL($url)
    {
        if (preg_match('~^https?://~', $url) === 0) {
            $url = "http://$url";
        }

        return $url;
    }

    public static function getImageLink($idProduct, $idImage, $linkRewrite, $imageSize)
    {
        $context = \Context::getContext();
        $url = $context->link->getImageLink($linkRewrite, "$idProduct-$idImage", $imageSize);

        return self::fixURL($url);
    }

    /**
     * Returns a data feed link for a given language ISO code. The link declares
     * the usage of the currency configured in the plugin by default.
     *
     * @param string $langIsoCode ISO language code
     *
     * @return string URL
     */
    public static function getFeedURL($langIsoCode)
    {
        $currency = self::getCurrencyForLanguage($langIsoCode);
        $feedUrl = self::getModuleLink('feed.php') . '?language=' . strtoupper($langIsoCode);
        $feedUrl .= '&currency=' . strtoupper($currency->iso_code);

        return $feedUrl;
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

    public static function walkApplyHtmlEntities(&$item, $key)
    {
        if (is_string($item)) {
            $item = htmlentities($item);
        }
    }

    public static function jsonEncode($data)
    {
        array_walk_recursive($data, [get_class(), 'walkApplyHtmlEntities']);

        return str_replace('\\/', '/', html_entity_decode(json_encode($data)));
    }

    public static function escapeSlashes($text)
    {
        return (is_string($text)) ? str_replace('/', '//', $text) : null;
    }

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

    public static function getMinVariantPrices($products, $includeTaxes, $currencies)
    {
        $context = \Context::getContext();

        $minPricesByProductId = [];
        foreach ($products as $product) {
            if (self::isParent($product)) {
                continue;
            }

            $productId = $product['id_product'];
            $variantId = $product['id_product_attribute'];
            $variantPrice = self::getPrice($productId, $includeTaxes, $variantId);
            $variantOnsalePrice = self::getOnsalePrice($productId, $includeTaxes, $variantId);
            $variantMultiprice = self::getFormattedMultiprice($productId, $includeTaxes, $currencies, $variantId);

            if (key_exists($productId, $minPricesByProductId)) {
                $currentMinPrices = $minPricesByProductId[$productId];

                /*
                Even though, in order to track the minimum, we can only focus on
                the sale price, we still need both prices of the variant
                in order to properly and consistently populate the price of
                the parent to show the proper price vs sale_price when searching
                in the layer
                */
                if ($variantOnsalePrice < $currentMinPrices['onsale_price']) {
                    $minPricesByProductId[$productId]['price'] = $variantPrice;
                    $minPricesByProductId[$productId]['onsale_price'] = $variantOnsalePrice;
                    $minPricesByProductId[$productId]['multiprice'] = $variantMultiprice;
                    $minPricesByProductId[$productId]['link'] = self::getVariantUrl($product, $context);
                }
            } else {
                $minPricesByProductId[$productId] = [
                    'price' => $variantPrice,
                    'onsale_price' => $variantOnsalePrice,
                    'multiprice' => $variantMultiprice,
                    'link' => self::getVariantUrl($product, $context),
                ];
            }
        }

        return $minPricesByProductId;
    }

    public static function isParent($product)
    {
        return isset($product['id_product_attribute']) && is_numeric($product['id_product_attribute']) && (int) $product['id_product_attribute'] === 0;
    }

    public static function getPrice($productId, $includeTaxes, $variantId = null)
    {
        return \Product::getPriceStatic(
            $productId,
            $includeTaxes,
            $variantId,
            6,
            null,
            false,
            false
        );
    }

    public static function getOnsalePrice($productId, $includeTaxes, $variantId = null)
    {
        return \Product::getPriceStatic(
            $productId,
            $includeTaxes,
            $variantId,
            6
        );
    }

    /**
     * Given a product and a list of currencies, returns the multiprice map.
     *
     * An example of a value for this field is
     * ["EUR" => ["price" => 5, "sale_price" => 3], "GBP" => ["price" => 4.3, "sale_price" => 2.7]]
     * for a list containing two currencies ["EUR", "GBP"].
     *
     * @param int $productId Id of the product to calculate the multiprice for
     * @param bool $includeTaxes Determines if taxes have to be included in the calculated prices
     * @param array $currencies List of currencies to consider for the multiprice calculation
     * @param int $variantId When specified, the multiprice will be calculated for that variant
     *
     * @return array
     */
    public static function getMultiprice($productId, $includeTaxes, $currencies, $variantId = null)
    {
        $multiprice = [];
        $price = self::getPrice($productId, $includeTaxes, $variantId);
        $onsale_price = self::getOnsalePrice($productId, $includeTaxes, $variantId);

        foreach ($currencies as $currency) {
            if ($currency['deleted'] == 0 && $currency['active'] == 1) {
                $convertedPrice = \Tools::convertPrice($price, $currency);
                $convertedOnsalePrice = \Tools::convertPrice($onsale_price, $currency);
                $currencyCode = $currency['iso_code'];
                $pricesMap = ['price' => $convertedPrice];

                if ($convertedPrice != $convertedOnsalePrice) {
                    $pricesMap['sale_price'] = $convertedOnsalePrice;
                }

                $multiprice[$currencyCode] = $pricesMap;
            }
        }

        return $multiprice;
    }

    /**
     * Given a product and a list of currencies, returns the multiprice field
     * in the correct format to be used in the feed CSV.
     *
     * An example of a value for this field is
     * "price_EUR=5/sale_price_EUR=3/price_GBP=4.3/sale_price_GBP=2.7"
     * for a list containing two currencies ["EUR", "GBP"].
     *
     * @param int $productId Id of the product to calculate the multiprice for
     * @param bool $includeTaxes Determines if taxes have to be included in the calculated prices
     * @param array $currencies List of currencies to consider for the multiprice calculation
     * @param int $variantId When specified, the multiprice will be calculated for that variant
     *
     * @return string
     */
    public static function getFormattedMultiprice($productId, $includeTaxes, $currencies, $variantId = null)
    {
        $multiprice = self::getMultiprice($productId, $includeTaxes, $currencies);

        return self::formatMultiprice($multiprice);
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
     * @param int $multiprice Multiprice map to be formatted
     *
     * @return string
     */
    private static function formatMultiprice($multiprice)
    {
        $multiprices = [];

        foreach ($multiprice as $currency => $prices) {
            foreach ($prices as $price_name => $value) {
                $multiprices[] = $currency . '_' . $price_name . '=' . $value;
            }
        }

        return implode('/', $multiprices);
    }

    private static function getVariantUrl($product, $context)
    {
        $context = \Context::getContext();
        $cfgModRewrite = self::cfg($context->shop->id, 'PS_REWRITING_SETTINGS', DoofinderConstants::YES);

        return self::cleanURL(
            $context->link->getProductLink(
                (int) $product['id_product'],
                $product['link_rewrite'],
                $product['cat_link_rew'],
                $product['ean13'],
                $context->language->id,
                $context->shop->id,
                (int) $product['id_product_attribute'],
                $cfgModRewrite,
                false,
                true
            )
        );
    }
}
