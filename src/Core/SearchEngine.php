<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Core;

use PrestaShop\Module\Doofinder\Api\DoofinderLayerApi;
use PrestaShop\Module\Doofinder\Api\EasyREST;
use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Manager\LanguageManager;
use PrestaShop\Module\Doofinder\Manager\UrlManager;
use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class SearchEngine
 *
 * Provides methods for managing and retrieving search engine configuration
 * including hashid retrieval and updates.
 */
class SearchEngine
{
    /**
     * Get the configuration key for the language and currency corresponding to the hashid
     *
     * @param int $idLang
     * @param int $idCurrency
     * @param int|null $shopGroupId
     * @param int|null $shopId
     *
     * @return string
     */
    public static function getHashId($idLang, $idCurrency, $shopGroupId = null, $shopId = null)
    {
        $context = \Context::getContext();
        $currIso = strtoupper(LanguageManager::getIsoCodeById($idCurrency));
        $lang = new \Language($idLang);
        $hashidKey = 'DF_HASHID_' . $currIso . '_' . strtoupper($lang->language_code);
        $shopGroupId = isset($shopGroupId) ? $shopGroupId : $context->shop->id_shop_group;
        $shopId = isset($shopId) ? $shopId : $context->shop->id;
        $hashid = \Configuration::get($hashidKey, $idLang, $shopGroupId, $shopId);

        if (!$hashid) {
            // If not found, try to obtain hashid without context
            $hashid = \Configuration::get($hashidKey, $idLang);
        }

        if (!$hashid) {
            // If not found, try to obtain hashid without idLang
            $hashid = \Configuration::get($hashidKey);
        }

        if (!$hashid) {
            $hashidKey = 'DF_HASHID_' . $currIso . '_' . strtoupper(LanguageManager::getLanguageCode($lang->language_code));
            $hashid = \Configuration::get($hashidKey);
        }

        return $hashid;
    }

    /**
     * Update the hashid of the search engines of the store in the configuration
     *
     * @param int|null $idShopGroup
     * @param int|null $idShop
     *
     * @return true
     */
    public static function setSearchEnginesByConfig($idShopGroup = null, $idShop = null)
    {
        $context = \Context::getContext();
        $idShopGroup = isset($idShopGroup) ? $idShopGroup : $context->shop->id_shop_group;
        $idShop = isset($idShop) ? $idShop : $context->shop->id;
        $installationID = \Configuration::get('DF_INSTALLATION_ID', null, $idShopGroup, $idShop);
        $apiKey = \Configuration::get('DF_API_KEY');
        $region = \Configuration::get('DF_REGION');

        $data = DoofinderLayerApi::getInstallationData($installationID, $apiKey, $region);

        foreach ($data['config']['search_engines'] as $lang => $currencies) {
            foreach ($currencies as $currency => $hashid) {
                $hashidKey = 'DF_HASHID_' . strtoupper($currency) . '_' . strtoupper($lang);
                \Configuration::updateValue($hashidKey, $hashid, false, $idShopGroup, $idShop);
            }
        }

        return true;
    }

    /**
     * Creates the Search Engine for a single language/currency combination on
     * a single shop, via the universal install endpoint, without resending
     * (and reprocessing) the whole store.
     *
     * @param int $shopId
     * @param int $idLang
     * @param int $idCurrency
     *
     * @return string|null the new Search Engine hashid, or null on failure
     */
    public static function createForLanguageAndCurrency($shopId, $idLang, $idCurrency)
    {
        $shop = \Shop::getShop($shopId);
        $shopGroupId = $shop['id_shop_group'];
        $installationID = \Configuration::get('DF_INSTALLATION_ID', null, $shopGroupId, $shopId);

        if (empty($installationID)) {
            DoofinderConfig::debug("Cannot create Search Engine: shop {$shopId} has no Doofinder installation yet.");

            return null;
        }

        $lang = new \Language($idLang);
        $currency = new \Currency($idCurrency);

        if (!\Validate::isLoadedObject($lang) || !\Validate::isLoadedObject($currency)) {
            DoofinderConfig::debug("Cannot create Search Engine: invalid language ({$idLang}) or currency ({$idCurrency}).");

            return null;
        }

        $existingHash = self::getHashId($idLang, $idCurrency, $shopGroupId, $shopId);
        if ($existingHash) {
            return $existingHash;
        }

        $multipriceEnabled = (bool) \Configuration::get('DF_MULTIPRICE_ENABLED', null, null, null, true);
        $feedUrl = $multipriceEnabled
            ? UrlManager::getFeedUrl($shopId, $lang->iso_code)
            : UrlManager::getFeedUrl($shopId, $lang->iso_code, $currency->iso_code);

        $client = new EasyREST();
        $apiKey = DfTools::getFormattedApiKey();

        $payload = json_encode([
            'store_id' => $installationID,
            'language' => $lang->language_code,
            'currency' => $currency->iso_code,
            'feed_url' => $feedUrl,
            'callback_url' => UrlManager::getProcessCallbackUrl($shopId),
        ]);

        DoofinderConfig::debug("Creating Search Engine for language {$lang->language_code} / currency {$currency->iso_code} on shop {$shopId}");

        $response = $client->post(
            UrlManager::getCreateSearchEngineUrl(\Configuration::get('DF_REGION')),
            $payload,
            null,
            null,
            'application/json',
            ['Authorization: Token ' . $apiKey]
        );

        if ($response->getResponseCode() !== 201) {
            $message = "Failed to create Search Engine for language {$lang->language_code} / currency {$currency->iso_code} on shop {$shopId}. Response: " . $response->response;
            DoofinderConfig::debug($message);
            \PrestaShopLogger::addLog($message, 3, null, 'Module', null, true);

            return null;
        }

        $data = json_decode($response->response, true);
        $hashidKey = 'DF_HASHID_' . strtoupper($currency->iso_code) . '_' . strtoupper($lang->language_code);
        \Configuration::updateValue($hashidKey, $data['hashid'], false, $shopGroupId, $shopId);

        return $data['hashid'];
    }
}
