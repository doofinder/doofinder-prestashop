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

class LanguageManager
{
    /**
     * Get the language associated with a search engine
     *
     * @param bool $hashid hashid of the Search Engine
     *
     * @return bool|int
     */
    public static function getLanguageByHashid($hashid)
    {
        $result = \Db::getInstance()->getValue('
            SELECT name
            FROM ' . _DB_PREFIX_ . 'configuration
            WHERE name like "DF_HASHID_%" and value = "' . pSQL($hashid) . '";
        ');

        if ($result) {
            $key = str_replace('DF_HASHID_', '', $result);
            $iso_code_parts = explode('_', $key);
            $iso_code = end($iso_code_parts);

            return (int) self::getLanguageIdByLocale($iso_code);
        } else {
            return false;
        }
    }

    /**
     * Get the ISO of a currency
     *
     * @param int $id currency ID
     *
     * @return string
     */
    public static function getIsoCodeById($id)
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
            SELECT `iso_code` FROM ' . _DB_PREFIX_ . 'currency WHERE `id_currency` = ' . (int) $id
        );
    }

    /**
     * Gets the ISO code of a language code
     *
     * @param string $code 3-letter Month abbreviation
     *
     * @return string
     */
    public static function getLanguageCode($code)
    {
        // $code is in the form of 'xx-YY' where xx is the language code
        // and 'YY' a country code identifying a variant of the language.
        $langCountry = explode('-', $code);

        return $langCountry[0];
    }

    /**
     * Returns language id from locale
     *
     * @param string $locale locale IETF language tag
     *
     * @return int|false|null
     */
    private static function getLanguageIdByLocale($locale)
    {
        $sanitized_locale = pSQL(strtolower($locale));

        return \Db::getInstance()
            ->getValue(
                'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang`
                WHERE `language_code` = \'' . $sanitized_locale . '\'
                OR `iso_code` = \'' . $sanitized_locale . '\''
            );
    }
}
