<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Feed;

use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Detects and resolves collisions between merchant defined names and the field names
 * the feed already uses.
 *
 * An attribute group or a feature becomes a document field named after itself, so a group
 * called "Title" produces a `title` field that takes over the product name. Every colliding
 * name is resolved one way or the other: either it replaces the canonical field, or it is
 * left out of the document so that the canonical field is kept.
 */
class DfFieldConflicts
{
    public const TYPE_ATTRIBUTE = 'attribute';
    public const TYPE_FEATURE = 'feature';

    /**
     * Field names the product feed can emit on its own. Some of them are conditional
     * (prices, variations, isbn), but all of them are reserved regardless.
     *
     * @var string[]
     */
    private const CANONICAL_FIELDS = [
        'alternate_description',
        'attributes',
        'availability',
        'brand',
        'categories',
        'category_merchandising',
        'creation_date',
        'description',
        'df_group_leader',
        'df_multiprice',
        'df_variants_information',
        'ean13',
        'extra_title_1',
        'extra_title_2',
        'features',
        'id',
        'image_link',
        'images_links',
        'isbn',
        'item_group_id',
        'link',
        'main_category',
        'meta_description',
        'meta_title',
        'minimum_quantity',
        'mpn',
        'price',
        'purchase_price',
        'reference',
        'sale_price',
        'stock_quantity',
        'supplier_name',
        'supplier_reference',
        'tags',
        'title',
        'unit_price',
        'upc',
        'variation_ean13',
        'variation_mpn',
        'variation_reference',
        'variation_supplier_reference',
        'variation_upc',
    ];

    /**
     * @return string[]
     */
    public static function canonicalFields()
    {
        return self::CANONICAL_FIELDS;
    }

    /**
     * Check whether a name is one of the feed's own field names.
     *
     * @param string $slug Slugified name
     *
     * @return bool
     */
    public static function isCanonical($slug)
    {
        return in_array($slug, self::CANONICAL_FIELDS, true);
    }

    /**
     * Resolve the field name a merchant defined attribute or feature must be emitted under.
     *
     * A name that collides with a canonical field is only emitted when it is meant to replace
     * it; otherwise it is left out, so that the canonical field keeps its own value.
     *
     * @param string $slug Slugified name of the attribute group or feature
     * @param bool $replaces Whether the merchant field replaces the canonical one
     *
     * @return string|null The name to emit, or null when it must not be indexed
     */
    public static function fieldName($slug, $replaces)
    {
        if (!self::isCanonical($slug)) {
            return $slug;
        }

        return $replaces ? $slug : null;
    }

    /**
     * List the attribute groups and features of a shop whose name collides with a feed field.
     *
     * The name, and therefore the slug, comes from the language tables, so the same entity can
     * collide in one language and not in another.
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     *
     * @return array<int,array{type:string,id:int,name:string,slug:string}>
     */
    public static function detect($idShop, $idLang)
    {
        $conflicts = [];

        foreach (DfTools::getAttributeKeysForShopAndLang($idShop, $idLang) as $id => $name) {
            $slug = DfTools::slugify($name);
            if (self::isCanonical($slug)) {
                $conflicts[] = [
                    'type' => self::TYPE_ATTRIBUTE,
                    'id' => (int) $id,
                    'name' => $name,
                    'slug' => $slug,
                ];
            }
        }

        foreach (DfTools::getFeatureKeysForShopAndLang($idShop, $idLang) as $id => $name) {
            $slug = DfTools::slugify($name);
            if (self::isCanonical($slug)) {
                $conflicts[] = [
                    'type' => self::TYPE_FEATURE,
                    'id' => (int) $id,
                    'name' => $name,
                    'slug' => $slug,
                ];
            }
        }

        return $conflicts;
    }
}
