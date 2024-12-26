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
     * If shop_id is null, install all shops
     *
     * @param int $shop_id
     *
     * @return void
     */
    public static function autoinstaller($shop_id = null)
    {
        if (!empty($shop_id)) {
            $shop = \Shop::getShop($shop_id);
            self::_createStore($shop);

            return;
        }

        $shops = \Shop::getShops();
        foreach ($shops as $shop) {
            self::_createStore($shop);
        }
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
        $apiKey = \Configuration::getGlobalValue('DF_AI_APIKEY');
        $languages = \Language::getLanguages(true, $shop['id_shop']);
        $currencies = \Currency::getCurrenciesByIdShop($shop['id_shop']);
        $shopId = $shop['id_shop'];
        $shopGroupId = $shop['id_shop_group'];
        $primaryLang = new \Language(\Configuration::get('PS_LANG_DEFAULT', null, $shopGroupId, $shopId));
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
            foreach ($currencies as $cur) {
                if ($cur['deleted'] == 1 || $cur['active'] == 0) {
                    continue;
                }
                $ciso = $cur['iso_code'];
                $langCode = $lang['language_code'];
                $feedUrl = UrlManager::getFeedUrl($shopId, $lang['iso_code'], $ciso);
                $storeData['search_engines'][] = [
                    'language' => $langCode,
                    'currency' => $ciso,
                    'feed_url' => $feedUrl,
                    'callback_url' => UrlManager::getProcessCallbackUrl($shopId),
                ];
            }
        }

        $jsonStoreData = json_encode($storeData);
        DoofinderConfig::debug("Create Store Start for shop with id: $shopId , and group: $shopGroupId.");
        DoofinderConfig::debug(print_r($storeData, true));

        $response = $client->post(
            UrlManager::getInstallUrl(\Configuration::get('DF_REGION')),
            $jsonStoreData,
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
     * This function sends a request to the plugins API to update feed urls to the new format.
     *
     * @throws \Exception
     *
     * @return void
     */
    public static function updateFeedUrls()
    {
        $shops = \Shop::getShops();

        DoofinderConfig::debug('SHOPS:');
        DoofinderConfig::debug(print_r($shops, true));

        foreach ($shops as $shop) {
            $feed_urls = [];
            $client = new EasyREST();
            $apiKey = \Configuration::getGlobalValue('DF_AI_APIKEY');
            $languages = \Language::getLanguages(true, $shop['id_shop']);
            $currencies = \Currency::getCurrenciesByIdShop($shop['id_shop']);
            $shopId = $shop['id_shop'];
            $shopGroupId = $shop['id_shop_group'];
            $installationID = \Configuration::get('DF_INSTALLATION_ID', null, $shopGroupId, $shopId);
            DoofinderConfig::debug("Updating feed urls for shop: {$shopId} and group: {$shopGroupId}");

            foreach ($languages as $lang) {
                if ($lang['active'] == 0) {
                    continue;
                }
                foreach ($currencies as $cur) {
                    if ($cur['deleted'] == 1 || $cur['active'] == 0) {
                        continue;
                    }
                    $ciso = $cur['iso_code'];
                    $langFullIso = $lang['iso_code'];
                    $feedUrl = UrlManager::getFeedUrl($shopId, $langFullIso, $ciso);
                    $hashidKey = 'DF_HASHID_' . strtoupper($ciso) . '_' . strtoupper($langFullIso);
                    $hashid = \Configuration::get($hashidKey, null, $shopGroupId, $shopId);

                    DoofinderConfig::debug("Hashid for lang $langFullIso and currency $ciso :  $hashid");

                    $feed_urls[$hashid] = $feedUrl;
                }
            }

            $json_feed_urls = json_encode([
                'installation_id' => $installationID,
                'urls' => $feed_urls,
            ]);

            DoofinderConfig::debug('Update feed urls Start');
            DoofinderConfig::debug(print_r($json_feed_urls, true));

            $response = $client->post(
                UrlManager::getUpdateFeedUrl(\Configuration::get('DF_REGION')),
                $json_feed_urls,
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
                $errorMsg = "Update feed urls failed with code {$response->getResponseCode()} and message '{$response->getResponseMessage()}'";
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
        $tab->active = 0;
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
            'DF_REGION',
            'DF_RESTART_OV',
            'DF_SHOW_PRODUCT_FEATURES',
            'DF_SHOW_PRODUCT_VARIATIONS',
            'DF_UPDATE_ON_SAVE_DELAY',
            'DF_UPDATE_ON_SAVE_LAST_EXEC',
            'DF_FEED_INDEXED',
        ];

        $hashidVars = array_column(
            \Db::getInstance()->executeS('
            SELECT name FROM ' . _DB_PREFIX_ . "configuration where name like 'DF_HASHID_%'"),
            'name'
        );

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
