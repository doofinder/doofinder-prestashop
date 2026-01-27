<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Manager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class LanguageManager
 *
 * Provides utility methods for retrieving language and currency information
 * related to Doofinder search engine configuration in PrestaShop.
 *
 * Functions include:
 * - Mapping Doofinder search engine hash IDs to PrestaShop language IDs
 * - Retrieving currency ISO codes by ID
 * - Extracting the base language code from a locale code
 * - Mapping locale codes to language IDs
 */
class LanguageManager
{
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
}
