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

class DoofinderInstallation
{
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey, $region)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = UrlManager::getRegionalUrl(DoofinderConstants::DOOPLUGINS_REGION_URL, $region);
    }

    /**
     * Make a request to the plugins API to to check that the update on save is valid
     *
     * @param string $installation_id
     * @param string $callback_url
     */
    public function isValidUpdateOnSave($installationId)
    {
        $apiEndpoint = $this->apiUrl . '/' . $installationId . '/validate-update-on-save';

        return $this->_get($apiEndpoint);
    }

    private function _get($url)
    {
        $client = new EasyREST();

        $response = $client->get(
            $url,
            null,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $this->apiKey]
        );

        return json_decode($response->response, true);
    }

    /**
     * Install the module database tables
     *
     * @return bool
     */
    public static function installDb()
    {
        return \Db::getInstance()->execute(
            '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doofinder_updates` (
                `id_doofinder_update` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL,
                `object` varchar(45) NOT NULL,
                `id_object` INT(10) UNSIGNED NOT NULL,
                `action` VARCHAR(45) NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_doofinder_update`),
                CONSTRAINT uc_shop_update UNIQUE KEY (id_shop,object,id_object)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
        )
            && \Db::getInstance()->execute(
                '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doofinder_landing` (
                `name` VARCHAR(45) NOT NULL,
                `hashid` VARCHAR(45) NOT NULL,
                `data` TEXT NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`name`, `hashid`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
            );
    }

    /**
     * Start the installation process
     * If shopId is null, installation is exceuted in all shops where the module is enabled
     *
     * @param int $shopId
     *
     * @return void
     */
    public static function autoinstaller($shopId = null)
    {
        if (!empty($shopId)) {
            $shop = \Shop::getShop($shopId);
            self::_createStore($shop);
            DoofinderConfig::setSharedGlobalDefaultConfig();

            return;
        }

        $shops = \Shop::getShops();
        foreach ($shops as $shop) {
            if (!DfTools::isModuleEnabledInShop($shop['id_shop'])) {
                continue;
            }
            self::_createStore($shop);
        }
        DoofinderConfig::setSharedGlobalDefaultConfig();
    }

    /**
     * Create a store in Doofinder based on the Prestashop shop
     *
     * @param array $shop
     *
     * @return void
     */
    private static function _createStore($shop)
    {
        $client = new EasyREST();
        $apiKey = DfTools::getFormattedApiKey();
        $languages = \Language::getLanguages(true, $shop['id_shop']);
        $currencies = \Currency::getCurrenciesByIdShop($shop['id_shop']);
        $shopId = $shop['id_shop'];
        $shopGroupId = $shop['id_shop_group'];
        $primaryLangId = (int)\Configuration::get('PS_LANG_DEFAULT', null, $shopGroupId, $shopId);
        $primaryLang = new \Language($primaryLangId);
        $primaryCurrencyId = (int)\Configuration::get('PS_CURRENCY_DEFAULT', null, $shopGroupId, $shopId);
        $primaryCurrency = new \Currency($primaryCurrencyId);
        $installationID = null;

        DoofinderConfig::setDefaultShopConfig($shopGroupId, $shopId);

        $shopUrl = UrlManager::getShopURL($shopId);
        $storeData = [
            'name' => $shop['name'],
            'platform' => 'prestashop',
            'primary_language' => $primaryLang->language_code,
            'site_url' => $shopUrl,
            'search_engines' => [],
            'plugin_version' => DoofinderConstants::VERSION,
        ];

        foreach ($languages as $lang) {
            if ($lang['active'] == 0) {
                continue;
            }
            $langCode = $lang['language_code'];
            $feedUrl = UrlManager::getFeedUrl($shopId, $lang['iso_code']);
            $storeData['search_engines'][] = [
                'language' => $langCode,
                'currency' => $primaryCurrency->iso_code,
                'feed_url' => $feedUrl,
                'callback_url' => UrlManager::getProcessCallbackUrl($shopId),
            ];
        }

        $currencyCodes = [];

        foreach ($currencies as $cur) {
            if ($cur['deleted'] == 1 || $cur['active'] == 0) {
                continue;
            }
            $currencyCodes[] = $cur['iso_code'];
        }

        $createStoreRequest = ['store_data' => $storeData, 'prices' => $currencyCodes];

        $jsonCreateStoreRequest = json_encode($createStoreRequest);
        DoofinderConfig::debug("Create Store Start for shop with id: $shopId , and group: $shopGroupId.");
        DoofinderConfig::debug(print_r($createStoreRequest, true));

        $response = $client->post(
            UrlManager::getInstallUrl(\Configuration::get('DF_REGION')),
            $jsonCreateStoreRequest,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $apiKey]
        );

        if ($response->getResponseCode() === 200) {
            $response = json_decode($response->response, true);
            $installationID = @$response['installation_id'];
            DoofinderConfig::debug('Create Store response:');
            DoofinderConfig::debug(print_r($response, true));

            if ($installationID) {
                DoofinderConfig::debug("Set installation ID: $installationID");
                \Configuration::updateValue('DF_INSTALLATION_ID', $installationID, false, $shopGroupId, $shopId);
                \Configuration::updateValue('DF_SHOW_LAYER', true, false, $shopGroupId, $shopId);
                SearchEngine::setSearchEnginesByConfig();
            } else {
                DoofinderConfig::debug('Invalid installation ID');
                exit('ko');
            }
        } else {
            $errorMsg = "Create Store failed with code {$response->getResponseCode()} and message '{$response->getResponseMessage()}'";
            $responseMsg = 'Response: ' . print_r($response->response, true);
            DoofinderConfig::debug($errorMsg);
            DoofinderConfig::debug($responseMsg);
            echo $response->response;
            exit;
        }
    }

    /**
     * Sends a request to the plugins API to update feed URLs to the new format
     *
     * @return void
     *
     * @throws \Exception If the request fails
     */
    public static function updateFeedUrls()
    {
        $shops = \Shop::getShops();

        DoofinderConfig::debug('Update Feed urls for the following SHOPS:');
        DoofinderConfig::debug(print_r($shops, true));
        $apiKey = DfTools::getFormattedApiKey();

        if (empty($apiKey)) {
            $errorMsg = 'Unable to update feed urls: Missing API KEY';
            DoofinderConfig::debug($errorMsg);
            throw new \Exception($errorMsg);
        }

        foreach ($shops as $shop) {
            $feedUrls = [];
            $client = new EasyREST();
            $languages = \Language::getLanguages(true, $shop['id_shop']);
            $currencies = \Currency::getCurrenciesByIdShop($shop['id_shop']);
            $shopId = $shop['id_shop'];
            $shopGroupId = $shop['id_shop_group'];
            $installationID = \Configuration::get('DF_INSTALLATION_ID', null, $shopGroupId, $shopId);

            if (empty($installationID)) {
                continue;
            }

            DoofinderConfig::debug("Updating feed urls for shop: {$shopId} and group: {$shopGroupId}");

            SearchEngine::setSearchEnginesByConfig($shopGroupId, $shopId);

            $multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED', null, $shopGroupId, $shopId);

            foreach ($languages as $lang) {
                if ($lang['active'] == 0) {
                    continue;
                }
                foreach ($currencies as $cur) {
                    if ($cur['deleted'] == 1 || $cur['active'] == 0) {
                        continue;
                    }
                    $ciso = $cur['iso_code'];
                    $langFullIso = !empty($lang['language_code']) ? $lang['language_code'] : $lang['iso_code'];

                    $currencyForUrl = $ciso;
                    if ($multipriceEnabled) {
                        $currencyForUrl = null;
                    }
                    $feedUrl = UrlManager::getFeedUrl($shopId, $lang['iso_code'], $currencyForUrl);

                    $hashid = SearchEngine::getHashId($lang['id_lang'], $cur['id_currency'], $shopGroupId, $shopId);

                    DoofinderConfig::debug("Hashid for lang $langFullIso and currency $ciso :  $hashid");

                    $feedUrls[$hashid] = $feedUrl;
                }
            }

            $jsonFeedUrls = json_encode([
                'installation_id' => $installationID,
                'urls' => $feedUrls,
            ]);

            DoofinderConfig::debug('Update feed urls Start');
            DoofinderConfig::debug(print_r($feedUrls, true));

            $response = $client->post(
                UrlManager::getUpdateFeedUrl(\Configuration::get('DF_REGION')),
                $jsonFeedUrls,
                false,
                false,
                'application/json',
                ['Authorization: Token ' . $apiKey]
            );

            if ($response->getResponseCode() === 200) {
                $response = json_decode($response->response, true);
                DoofinderConfig::debug('Update feed urls response:');
                DoofinderConfig::debug(print_r($response, true));
            } else {
                $errorMsg = sprintf('Update feed urls failed with code %1$s and message "%2$s"', $response->getResponseCode(), $response->getResponseMessage());
                $decodedResponse = json_decode($response->response);
                DoofinderConfig::debug($errorMsg);
                DoofinderConfig::debug($decodedResponse);
                error_log('[Doofinder] An error occurred when updating feed urls.');

                throw new \Exception('An error occurred when updating feed urls.', $response->getResponseCode());
            }
        }
    }

    public static function installTabs()
    {
        $tab = new \Tab();
        $tab->active = false;
        $tab->class_name = 'DoofinderAdmin';
        $tab->name = [];
        foreach (\Language::getLanguages() as $lang) {
            $tab->name[$lang['id_lang']] = 'Doofinder admin controller';
        }
        $tab->id_parent = 0;
        $tab->module = DoofinderConstants::NAME;

        return $tab->save();
    }

    public static function uninstallTabs()
    {
        $tabId = (int) \Tab::getIdFromClassName('DoofinderAdmin');
        if (!$tabId) {
            return true;
        }

        // Using Tab constructor and delete method instead of deprecated method
        $tab = new \Tab($tabId);

        return $tab->delete();
    }

    /**
     * Remove module-dependent configuration variables
     *
     * @return bool
     */
    public static function deleteConfigVars()
    {
        $configVars = [
            'DF_AI_ADMIN_ENDPOINT',
            'DF_AI_API_ENDPOINT',
            'DF_AI_APIKEY',
            'DF_API_KEY',
            'DF_API_LAYER_DESCRIPTION',
            'DF_CSS_VS',
            'DF_CUSTOMEXPLODEATTR',
            'DF_DEBUG',
            'DF_DEBUG_CURL',
            'DF_DSBL_AJAX_TKN',
            'DF_DSBL_DFCKIE_JS',
            'DF_DSBL_DFFAC_JS',
            'DF_DSBL_DFLINK_JS',
            'DF_DSBL_DFPAG_JS',
            'DF_DSBL_FAC_CACHE',
            'DF_DSBL_HTTPS_CURL',
            'DF_EB_LAYER_DESCRIPTION',
            'DF_ENABLED_V9',
            'DF_ENABLE_HASH',
            'DF_EXTRA_CSS',
            'DF_FACETS_TOKEN',
            'DF_FEATURES_SHOWN',
            'DF_FEED_FULL_PATH',
            'DF_FEED_INDEXED',
            'DF_FEED_MAINCATEGORY_PATH',
            'DF_GROUP_ATTRIBUTES_SHOWN',
            'DF_GS_DESCRIPTION_TYPE',
            'DF_GS_DISPLAY_PRICES',
            'DF_GS_IMAGE_SIZE',
            'DF_GS_PRICES_USE_TAX',
            'DF_INSTALLATION_ID',
            'DF_SHOW_LAYER',
            'DF_SHOW_LAYER_MOBILE',
            'DF_REGION',
            'DF_RESTART_OV',
            'DF_SHOW_PRODUCT_FEATURES',
            'DF_SHOW_PRODUCT_VARIATIONS',
            'DF_UPDATE_ON_SAVE_DELAY',
            'DF_UPDATE_ON_SAVE_LAST_EXEC',
            'DF_FEED_INDEXED',
            'DF_MULTIPRICE_ENABLED',
        ];

        $rows = \Db::getInstance()->executeS('SELECT name FROM ' . _DB_PREFIX_ . "configuration WHERE name LIKE 'DF_HASHID_%'");
        $hashidVars = array_map(function ($row) {
            return $row['name'];
        }, $rows);

        $configVars = array_merge($configVars, $hashidVars);

        foreach ($configVars as $var) {
            \Configuration::deleteByName($var);
        }

        return true;
    }

    /**
     * Removes the database tables from the module
     *
     * @return bool
     */
    public static function uninstallDb()
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'doofinder_updates`')
            && \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'doofinder_landing`');
    }
}
