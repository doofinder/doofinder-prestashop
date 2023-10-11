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
define('CATEGORY_SEPARATOR', ' %% ');
define('CATEGORY_TREE_SEPARATOR', '>');
define('TXT_SEPARATOR', '|');

class DfTools
{
    // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
    const VALID_UTF8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF]
    [\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F]
    [\x80-\xBF]{2})|./x';

    //
    // Validation
    //

    public static function isBasicValue($v)
    {
        return $v && !empty($v) && Validate::isGenericName($v);
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
            $sql .= ' LIMIT ' . intval($limit);

            if (false !== $offset && is_numeric($offset)) {
                $sql .= ' OFFSET ' . intval($offset);
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
                WHEN name = 'home_default' THEN '1'
            END DESC;
        ";

        foreach (Db::getInstance()->ExecuteS(self::prepareSQL($sql)) as $size) {
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

        foreach (Db::getInstance()->ExecuteS(self::prepareSQL($sql)) as $currency) {
            $currencies[$currency['iso_code']] = $currency;
        }

        return $currencies;
    }

    /**
     * 1.[5].0.13 | 1.5.[0].5 | 1.5.0.[1]
     * 1.[6].0.6  | 1.5.[1].0 | 1.5.0.[5]
     *
     * @param  [type] $min_version [description]
     *
     * @return [type]              [description]
     */
    public static function versionGte($min_version)
    {
        $version = explode('.', _PS_VERSION_);
        $min_version = explode('.', $min_version);

        foreach ($version as $index => $value) {
            if (intval($value) > intval($min_version[$index])) {
                return true;
            } elseif (intval($value) < intval($min_version[$index])) {
                return false;
            }
        }

        return true;
    }

    public static function getSelectedFeatures($features, $selected_keys)
    {
        /**
         * Returns the features selected by user
         *
         * @param array features
         *
         * @return array of rows (assoc arrays)
         */
        $selected_features = [];

        foreach ($features as $key => $value) {
            if (in_array((string) $key, $selected_keys)) {
                $selected_features[] = $value;
            }
        }

        return $selected_features;
    }

    /**
     * Returns the features of a product
     *
     * @param int shop ID
     * @param int language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getFeatureKeysForShopAndLang($id_shop, $id_lang)
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
            '_ID_LANG_' => (int) pSQL($id_lang),
            '_ID_SHOP_' => (int) pSQL($id_shop),
        ]);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $names = [];
        foreach ($result as $elem) {
            $names[$elem['id_feature']] = $elem['name'];
        }

        return $names;
    }

    /**
     * Returns the features of a product
     *
     * @param int shop ID
     * @param int language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAttributeKeysForShopAndLang($id_shop, $id_lang)
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
            '_ID_LANG_' => (int) pSQL($id_lang),
            '_ID_SHOP_' => (int) pSQL($id_shop),
        ]);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $names = [];
        foreach ($result as $elem) {
            $names[] = $elem['name'];
        }

        return $names;
    }

    /**
     * Returns the features of a product
     *
     * @param int shop ID
     * @param int language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getVariationImg($id_product, $id_product_attribute)
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
            where pa.id_product = ' . (int) pSQL($id_product) . '
                and pa.id_product_attribute = ' . (int) pSQL($id_product_attribute) . '
            group by pa.id_product, pa.id_product_attribute,paic.id_attribute
            ) as P
            inner join _DB_PREFIX_image i
             on i.id_product = P.id_product and i.position =  P.min_position
            ';
        $sql = self::prepareSQL($sql, []);
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (isset($result[0])) {
            return $result[0]['id_image'];
        } else {
            return '';
        }
    }

    /**
     * Returns the features of a product
     *
     * @param int product ID
     * @param int language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getFeaturesForProduct($id_product, $id_lang, $feature_keys)
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
            '_ID_LANG_' => (int) pSQL($id_lang),
            '_ID_PRODUCT' => (int) pSQL($id_product),
        ]);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $features = [];

        foreach ($result as $elem) {
            if (in_array($elem['name'], $feature_keys)) {
                $features[$elem['name']][] = $elem['value'];
            }
        }

        return $features;
    }

    /**
     * Returns the product variation attributes
     *
     * @param int product Attribute ID
     * @param int language ID
     *
     * @return array of rows (assoc arrays)
     */
    public static function getAttributesForProductVariation($variation_id, $id_lang, $attribute_keys)
    {
        if (isset($variation_id) && $variation_id > 0) {
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
                '_ID_LANG_' => (int) pSQL($id_lang),
                '_VARIATION_ID' => (int) pSQL($variation_id),
            ]);

            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        } else {
            $result = [];
        }

        if (count($attribute_keys) > 0) {
            $attributes = array_fill(0, count($attribute_keys), '');
        } else {
            $attributes = [];
        }

        foreach ($result as $elem) {
            if (array_search($elem['group_name'], $attribute_keys) !== false) {
                $attributes[array_search($elem['group_name'], $attribute_keys)] = $elem['name'];
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
    public static function getAttributesByCombination($variation_id, $id_lang, $attr_limit = false)
    {
        if (isset($variation_id) && $variation_id > 0) {
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

            if ($attr_limit) {
                $sql .= ' AND pa.id_attribute_group IN (' . pSQL($attr_limit) . ')';
            }
            $sql = self::prepareSQL($sql, [
                '_ID_LANG_' => (int) pSQL($id_lang),
                '_VARIATION_ID' => (int) pSQL($variation_id),
            ]);

            return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
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
    public static function getAttributesName($attributes_ids, $id_lang)
    {
        $sql = '
            SELECT pag.id_attribute_group, pagl.name
            FROM _DB_PREFIX_attribute_group pag
            LEFT JOIN _DB_PREFIX_attribute_group_lang pagl ON pag.id_attribute_group = pagl.id_attribute_group  AND pagl.id_lang = _ID_LANG_
            WHERE pag.id_attribute_group IN (' . implode(',', $attributes_ids) . ')
            ';

        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) pSQL($id_lang),
        ]);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
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
    public static function getAvailableProductsForLanguage(
        $id_lang,
        $id_shop,
        $limit = false,
        $offset = false,
        $ids = null
    ) {
        $Shop = new Shop($id_shop);

        $isbn = '';
        if (dfTools::versionGte('1.7.0.0')) {
            $isbn = 'p.isbn,';
            if (dfTools::cfg($id_shop, 'DF_SHOW_PRODUCT_VARIATIONS') == 1) {
                $isbn_pa = 'IF(isnull(pa.id_product), p.isbn , pa.isbn) AS isbn,';
            }
        }

        $mpn = 'p.reference as mpn,';
        $mpn_pa = 'pa.reference AS variation_mpn,';
        if (dfTools::versionGte('1.7.7.0')) {
            $mpn = 'p.mpn AS mpn,';
            if (dfTools::cfg($id_shop, 'DF_SHOW_PRODUCT_VARIATIONS') == 1) {
                $mpn_pa = 'pa.mpn AS variation_mpn,';
            }
        }

        $sql = '
      SELECT
        ps.id_product,
        ps.show_price,
        __ID_CATEGORY_DEFAULT__,
        m.name AS manufacturer,
        ' . $mpn . '
        p.ean13 AS ean13,
        ' . $isbn . "
        p.upc,
        p.reference,
        p.supplier_reference,
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
          ON (p.id_category_default = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
        LEFT JOIN (_DB_PREFIX_tag tag
            INNER JOIN _DB_PREFIX_product_tag pt ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
          ON (pt.id_product = p.id_product)
        LEFT JOIN _DB_PREFIX_stock_available sa
          ON (p.id_product = sa.id_product AND sa.id_product_attribute = 0
            AND (sa.id_shop = _ID_SHOP_ OR
            (sa.id_shop = 0 AND sa.id_shop_group = _ID_SHOPGROUP_)))
      WHERE
        __IS_ACTIVE__
        __VISIBILITY__
        __PRODUCT_IDS__
      GROUP BY
        p.id_product
      ORDER BY
        p.id_product
    ";

        $sql_variations = "
      SELECT
        ps.id_product,
        ps.show_price,
        pa.id_product_attribute,
        pa.reference AS variation_reference,
        pa.supplier_reference AS variation_supplier_reference,
        $mpn_pa
        pa.ean13 AS variation_ean13,
        pa.upc AS variation_upc,
        pa_im.id_image AS variation_image_id,
        __ID_CATEGORY_DEFAULT__,
        m.name AS manufacturer,
        $mpn
        p.ean13 AS ean13,
        $isbn_pa
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
          ON (p.id_category_default = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
        LEFT OUTER JOIN _DB_PREFIX_product_attribute pa
          ON (p.id_product = pa.id_product)
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
        null AS variation_supplier_reference,
        null AS variation_mpn,
        null AS variation_ean13,
        null AS variation_upc,
        null AS variation_image_id,
        __ID_CATEGORY_DEFAULT__,
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
          ON (p.id_category_default = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
        LEFT JOIN (_DB_PREFIX_tag tag
            INNER JOIN _DB_PREFIX_product_tag pt ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
          ON (pt.id_product = p.id_product)
        LEFT JOIN _DB_PREFIX_stock_available sa
          ON (p.id_product = sa.id_product AND sa.id_product_attribute = 0
            AND (sa.id_shop = _ID_SHOP_ OR
            (sa.id_shop = 0 AND sa.id_shop_group = _ID_SHOPGROUP_)))
      WHERE
        __IS_ACTIVE__
        __VISIBILITY__
        __PRODUCT_IDS__
      GROUP BY
        p.id_product
      ORDER BY
        id_product
    ";

        if (dfTools::cfg($id_shop, 'DF_SHOW_PRODUCT_VARIATIONS') == 1) {
            $sql = $sql_variations;
        }

        // MIN: 1.5.0.9
        $id_category_default = self::versionGte('1.5.0.9') ? 'ps.id_category_default' : 'p.id_category_default';
        // MIN: 1.5.1.0
        $ims_cover = self::versionGte('1.5.1.0') ? 'ims.cover = 1' : 'im.cover = 1';
        $is_active = self::versionGte('1.5.1.0') ? 'ps.active = 1' : 'p.active = 1';

        if (self::versionGte('1.5.1.0')) {
            $visibility = "AND ps.visibility IN ('search', 'both')";
        } elseif (self::versionGte('1.5.0.9')) {
            $visibility = "AND p.visibility IN ('search', 'both')";
        } else {
            $visibility = '';
        }

        if (is_array($ids) && count($ids)) {
            $product_ids = 'AND p.id_product IN (' . implode(',', $ids) . ')';
        } else {
            $product_ids = '';
        }

        $sql = self::limitSQL($sql, $limit, $offset);
        $sql = self::prepareSQL($sql, [
            '_ID_LANG_' => (int) pSQL($id_lang),
            '_ID_SHOP_' => (int) pSQL($id_shop),
            '_ID_SHOPGROUP_' => (int) pSQL($Shop->id_shop_group),
            '_IMS_COVER_' => (string) pSQL($ims_cover),
            '__ID_CATEGORY_DEFAULT__' => (int) pSQL($id_category_default),
            '__IS_ACTIVE__' => (string) pSQL($is_active),
            '__VISIBILITY__' => (string) pSQL($visibility),
            '__PRODUCT_IDS__' => (string) pSQL($product_ids),
        ]);

        $sql = str_replace("\'", "'", $sql);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    protected static $root_category_ids;
    protected static $cached_category_paths = [];

    /**
     * Returns an array of "root" categories in Prestashop for a language.
     * The results are cached in a protected, static variable.
     *
     * @return array
     */
    public static function getRootCategoryIds($id_lang)
    {
        if (null === self::$root_category_ids) {
            self::$root_category_ids = [];
            foreach (Category::getRootCategories($id_lang) as $category) {
                self::$root_category_ids[] = $category['id_category'];
            }
        }

        return self::$root_category_ids;
    }

    /**
     * Returns the path to the first, no root ancestor category for the selected
     * category ID in a language for the selected shop.
     * Results are cached by category ID.
     *
     * @param int Category ID
     * @param int Language ID
     * @param int Shop ID
     * @param bool return full category path
     *
     * @return string
     */
    public static function getCategoryPath($id_category, $id_lang, $id_shop, $full = true)
    {
        if (isset(self::$cached_category_paths[$id_category])) {
            return self::$cached_category_paths[$id_category];
        }

        $excluded_ids = self::getRootCategoryIds($id_lang);

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

        if (count($excluded_ids) > 0 && $excluded_ids[0] != '') {
            $sql .= 'AND parent.id_category NOT IN (_EXCLUDED_IDS_) ';
        }

        $sql .= 'ORDER BY
        parent.nleft
      ;';

        $sql = self::prepareSQL($sql, [
            '_ID_CATEGORY_' => (int) pSQL($id_category),
            '_ID_SHOP_' => (int) pSQL($id_shop),
            '_ID_LANG_' => (int) pSQL($id_lang),
            '_EXCLUDED_IDS_' => (string) pSQL(implode(',', $excluded_ids)),
        ]);

        $sql = str_replace("\'", "'", $sql);

        $path = [];
        foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $row) {
            $path[] = str_replace([CATEGORY_TREE_SEPARATOR, CATEGORY_SEPARATOR], '-', $row['name']);
        }

        if ($full) {
            $path = implode(CATEGORY_TREE_SEPARATOR, $path);
        } else {
            $path = end($path);
        }

        $path = self::cleanString($path);
        self::$cached_category_paths[$id_category] = $path;

        return $path;
    }

    /**
     * Returns a string with all the paths for categories for a product in a language
     * for the selected shop. If $flat == false then returns them as an array.
     *
     * @param int Product ID
     * @param int Language ID
     * @param int Shop ID
     * @param bool optional implode values
     *
     * @return string or array
     */
    public static function getCategoriesForProductIdAndLanguage($id_product, $id_lang, $id_shop, $flat = true)
    {
        $use_main_category = (bool) dfTools::cfg($id_shop, 'DF_FEED_MAINCATEGORY_PATH', Doofinder::YES);

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

        if ($use_main_category) {
            $mainInnerSql = 'INNER JOIN _DB_PREFIX_product_shop ps '
                . 'ON (ps.id_product = _ID_PRODUCT_ AND ps.id_shop = _ID_SHOP_)';
            $mainCategoryInner = self::prepareSQL(
                $mainInnerSql,
                ['_ID_PRODUCT_' => (int) pSQL($id_product), '_ID_SHOP_' => (int) pSQL($id_shop)]
            );
            $mainCategoryWhere = 'AND ps.id_category_default = cp.id_category';
        }

        $sql = self::prepareSQL($sql, [
            '_ID_PRODUCT_' => (int) pSQL($id_product),
            '_MAIN_CATEGORY_INNER_' => (string) pSQL($mainCategoryInner),
            '_MAIN_CATEGORY_WHERE_' => (string) pSQL($mainCategoryWhere),
            '_ID_SHOP_' => (int) pSQL($id_shop),
        ]);

        $sql = str_replace("\'", "'", $sql);

        $categories = [];
        $last_saved = 0;
        $id_category0 = 0;
        $nleft0 = 0;
        $nright0 = 0;
        $use_full_path = (bool) dfTools::cfg($id_shop, 'DF_FEED_FULL_PATH', Doofinder::YES);

        foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $i => $row) {
            if (!$i) {
                $id_category0 = intval($row['id_category']);
                $nleft0 = intval($row['nleft']);
                $nright0 = intval($row['nright']);
            } else {
                $id_category1 = intval($row['id_category']);
                $nleft1 = intval($row['nleft']);
                $nright1 = intval($row['nright']);

                if ($nleft1 < $nleft0 && $nright1 > $nright0) {
                    // $id_category1 is an ancestor of $id_category0
                } elseif ($nleft1 < $nleft0 && $nright1 > $nright0) {
                    // $id_category1 is a child of $id_category0 so be replace $id_category0
                    $id_category0 = $id_category1;
                    $nleft0 = $nleft1;
                    $nright0 = $nright1;
                } else {
                    // $id_category1 is not a relative of $id_category0 so we save
                    // $id_category0 now and make $id_category1 the current category.
                    $categories[] = self::getCategoryPath($id_category0, $id_lang, $id_shop, $use_full_path);
                    $last_saved = $id_category0;

                    $id_category0 = $id_category1;
                    $nleft0 = $nleft1;
                    $nright0 = $nright1;
                }
            }
        } // endforeach

        if ($last_saved != $id_category0) {
            // The last item in loop didn't trigger the $id_category0 saving event.
            $categories[] = self::getCategoryPath($id_category0, $id_lang, $id_shop, $use_full_path);
        }

        return $flat ? implode(CATEGORY_SEPARATOR, $categories) : $categories;
    }

    /**
     * Check if product has variants
     *
     * @param int product ID
     *
     * @return int
     */
    public static function hasAttributes($id_product)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
            SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            ' . Shop::addSqlAssociation('product_attribute', 'pa') . '
            WHERE pa.`id_product` = ' . (int) $id_product
        );
    }

    /**
     * Check if product has attributes
     *
     * @param int product ID
     * @param string attribute groups IDs
     *
     * @return array
     */
    public static function hasProductAttributes($id_product, $attribute_groups_id)
    {
        if (!$attribute_groups_id) {
            return false;
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT a.id_attribute_group
            FROM `' . _DB_PREFIX_ . 'product` p
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON p.id_product = pa.id_product
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pa.id_product_attribute = pac.id_product_attribute
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON pac.id_attribute = a.id_attribute
            WHERE p.id_product = ' . pSQL($id_product) . ' AND id_attribute_group IN ( ' . pSQL($attribute_groups_id) . ')
            GROUP BY a.id_attribute_group
            '
        );

        return array_column($result, 'id_attribute_group');
    }

    public function getCategories($idLang, $active = true)
    {
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT c.id_category
            FROM `' . _DB_PREFIX_ . 'category` c
            ' . Shop::addSqlAssociation('category', 'c') . '
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON c.`id_category` = cl.`id_category`' . Shop::addSqlRestrictionOnLang('cl') . '
            WHERE id_parent != 0
            ' . ($idLang ? 'AND `id_lang` = ' . (int) $idLang : '') . '
            ' . ($active ? 'AND `active` = 1' : '') . '
            ' . (!$idLang ? 'GROUP BY c.id_category' : '')
        );

        return array_column($result, 'id_category');
    }

    public function getCmsPages($idLang, $id_shop, $active = true)
    {
        $result = CMS::getCMSPages($idLang, null, $active, $id_shop);

        return array_column($result, 'id_cms');
    }

    //
    // Text Tools
    //

    public static function truncateText($text, $length)
    {
        $l = intval($length);
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

    public static function stripHtml($text)
    {
        if (!function_exists('cb1')) {
            function cb1($matches)
            {
                return chr($matches[1]);
            }
        }

        if (!function_exists('cb2')) {
            function cb2($matches)
            {
                return chr('0x' . $matches[1]);
            }
        }
        html_entity_decode($text, ENT_QUOTES, 'ISO-8859-1');
        $text = preg_replace_callback(
            '/&#(\d+);/mu',
            'cb1',
            $text
        );  // decimal notation
        $text = preg_replace_callback(
            '/&#x([a-f0-9]+);/miu',
            'cb2',
            $text
        );  // hex notation
        $text = str_replace('><', '> <', $text);
        $text = preg_replace('/\<br(\s*)?\/?\>/i', ' ', $text);
        $text = strip_tags($text);

        return $text;
    }

    public static function cleanURL($text)
    {
        $text = trim($text);
        $text = preg_replace("/\r|\n/", '', $text);
        $text = explode('?', $text);

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
        $text = preg_replace('/[^\P{C}]+/u', ' ', $text);
        $text = str_replace(TXT_SEPARATOR, '-', $text);
        $text = str_replace(["\t", "\r", "\n"], ' ', $text);
        $text = self::stripHtml($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        $text = preg_replace('/^["\']+/', '', $text); // remove first quotes

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

        return str_replace($forbidden, '', $text);
    }

    public static function splitReferences($text)
    {
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
        $v = Tools::getValue($parameter, null);

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
     * @return Language
     */
    public static function getLanguageFromRequest()
    {
        $context = Context::getContext();
        $id_lang = Tools::getValue('language', $context->language->id);

        if (!is_numeric($id_lang)) {
            $id_lang = Language::getIdByIso($id_lang);
        }

        return new Language($id_lang);
    }

    /**
     * Returns a Currency object with the currency configured in the plugin for
     * the given ISO language $code parameter. If no currency is found the method
     * returns the default one for the current context.
     *
     * @param string $code ISO language code
     *
     * @return Currency
     */
    public static function getCurrencyForLanguage($code)
    {
        $optname = 'DF_GS_CURRENCY_' . strtoupper($code);
        $id_currency = Configuration::get($optname);

        if ($id_currency) {
            return new Currency(Currency::getIdByIsoCode($id_currency));
        }

        return new Currency(Context::getContext()->currency->id);
    }

    /**
     * Returns a Currency object based on the 'currency' parameter from the
     * request. If no currency is found then the function searches one in the
     * plugin configuration based on the $lang parameter. If none is configured
     * then the default one from the current context is used.
     *
     * @param Language $lang
     *
     * @return Currency
     */
    public static function getCurrencyForLanguageFromRequest(Language $lang)
    {
        if ($id_currency = Tools::getValue('currency')) {
            if (is_numeric($id_currency)) {
                $id_currency = intval($id_currency);
            } else {
                $id_currency = Currency::getIdByIsoCode(strtoupper($id_currency));
            }
        } else {
            $optname = 'DF_GS_CURRENCY_' . strtoupper($lang->iso_code);
            $id_currency = Currency::getIdByIsoCode(Configuration::get($optname));
        }

        if (!$id_currency) {
            $context = Context::getContext();
            $id_currency = $context->currency->id;
        }

        return new Currency($id_currency);
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
        $context = Context::getContext();
        $shop = new Shop($context->shop->id);
        $base = (($ssl && $context->link->ssl_enable) ? 'https://' : 'http://') . $shop->domain;

        return $base . _MODULE_DIR_ . basename(dirname(__FILE__)) . '/' . $path;
    }

    public static function fixURL($url)
    {
        if (preg_match('~^https?://~', $url) === 0) {
            $url = "http://$url";
        }

        return $url;
    }

    public static function getImageLink($id_product, $id_image, $link_rewrite, $image_size)
    {
        $context = Context::getContext();
        $url = $context->link->getImageLink($link_rewrite, "$id_product-$id_image", $image_size);

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
     * Returns a configuration value for a $key and a $id_shop. If the value is
     * not found (or it's false) then returns a $default value.
     *
     * @param int $id_shop shop id
     * @param string $key configuration variable name
     * @param mixed $default default value
     *
     * @return mixed
     */
    public static function cfg($id_shop, $key, $default = false)
    {
        $v = Configuration::get($key, null, null, $id_shop);
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

    public static function escapeSlashes($string)
    {
        return $string = str_replace('/', '//', $string);
    }

    public static function validateSecurityToken($dfsec_hash)
    {
        $doofinder_api_key = Configuration::get('DF_API_KEY');
        if (!empty($doofinder_api_key) && $dfsec_hash != $doofinder_api_key) {
            header('HTTP/1.1 403 Forbidden', true, 403);
            $msgError = 'Forbidden access.'
                . ' Maybe security token missed.'
                . ' Please check on your doofinder module'
                . ' configuration page the new URL'
                . ' for your feed';
            exit($msgError);
        }
    }
}
