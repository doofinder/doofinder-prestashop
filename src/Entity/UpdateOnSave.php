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

class UpdateOnSave
{
    /**
     * Check if the necessary time has passed to run the update on save again.
     *
     * @return bool
     */
    public static function allowProcessItemsQueue()
    {
        if (\Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
            $lastExec = \Configuration::get('DF_UPDATE_ON_SAVE_LAST_EXEC', null, null, null, 0);
            $delay = (int) \Configuration::get('DF_UPDATE_ON_SAVE_DELAY', null, null, null, 30);

            if (is_int($delay)) {
                $lastExecTs = strtotime($lastExec);

                $diffMin = (time() - $lastExecTs) / 60;

                if ($diffMin > $delay) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add a item to the update on save queue.
     *
     * @param string $object
     * @param int $idObject
     * @param int $shopId
     * @param string $action
     *
     * @return void
     */
    public static function addItemQueue($object, $idObject, $shopId, $action)
    {
        \Db::getInstance()->insert(
            'doofinder_updates',
            [
                'id_shop' => $shopId,
                'object' => $object,
                'id_object' => $idObject,
                'action' => $action,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            false,
            true,
            \Db::REPLACE
        );
    }

    /**
     * Updates the execution date of the update on save
     *
     * @return void
     */
    public static function setExecUpdateOnSave()
    {
        \Configuration::updateValue('DF_UPDATE_ON_SAVE_LAST_EXEC', date('Y-m-d H:i:s'));
    }

    /**
     * Process queued items from update on save to send to API.
     *
     * @param int $shopId
     *
     * @return void
     */
    public static function processItemQueue($shopId)
    {
        self::setExecUpdateOnSave();

        $languages = \Language::getLanguages(true, $shopId);
        $currencies = \Currency::getCurrenciesByIdShop($shopId);
        $defaultCurrency = new \Currency(\Configuration::get('PS_CURRENCY_DEFAULT', null, null, $shopId));
        $multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');

        foreach (['product', 'cms', 'category'] as $type) {
            $itemsUpdate = self::getItemsQueue($shopId, $type, 'update');
            $itemsDelete = self::getItemsQueue($shopId, $type, 'delete');

            foreach ($languages as $language) {
                /*
                 * For Stores with Multiprice SE, we only have one SE per language.
                 * In these cases, we can just use the default currency as all
                 * Language-Currency will point to the same SE HashId
                 */
                if ($multipriceEnabled) {
                    self::{'send' . $type . 'Api'}($itemsUpdate, $shopId, $language['id_lang'], $defaultCurrency->id);
                    self::{'send' . $type . 'Api'}($itemsDelete, $shopId, $language['id_lang'], $defaultCurrency->id, 'delete');
                } else {
                    foreach ($currencies as $currency) {
                        self::{'send' . $type . 'Api'}($itemsUpdate, $shopId, $language['id_lang'], $currency['id_currency']);
                        self::{'send' . $type . 'Api'}($itemsDelete, $shopId, $language['id_lang'], $currency['id_currency'], 'delete');
                    }
                }
            }
        }

        self::deleteItemsQueue($shopId);
    }

    /**
     * Get queued items from update on save
     *
     * @param int $shopId
     * @param string $action
     *
     * @return array
     */
    public static function getItemsQueue($shopId, $type, $action = 'update')
    {
        $items = \Db::getInstance()->executeS(
            '
            SELECT id_object FROM ' . _DB_PREFIX_ . "doofinder_updates
            WHERE object = '" . pSQL($type) . "' AND action = '" . pSQL($action) . "' AND id_shop = " . (int) $shopId
        );

        return array_column($items, 'id_object');
    }

    /**
     * Remove queued items from update on save
     *
     * @param int $shopId
     *
     * @return void
     */
    public static function deleteItemsQueue($shopId)
    {
        \Db::getInstance()->execute('DELETE from ' . _DB_PREFIX_ . 'doofinder_updates WHERE id_shop = ' . (int) $shopId);
    }

    /**
     * Update products in Doofinder using the API
     *
     * @param array $products
     * @param int $shopId
     * @param int $idLang
     * @param int $idCurrency
     * @param string $action
     *
     * @return void
     */
    public static function sendProductApi($products, $shopId, $idLang, $idCurrency, $action = 'update')
    {
        if (empty($products)) {
            return;
        }

        $hashid = SearchEngine::getHashId($idLang, $idCurrency);

        if (!$hashid) {
            return;
        }

        if ('update' === $action) {
            $chunks = array_chunk($products, 100);
            $builder = new DfProductBuild($shopId, $idLang, $idCurrency);

            foreach ($chunks as $chunk) {
                $builder->setProducts($chunk);
                $payload = $builder->build();

                self::updateItemsApi($hashid, 'product', $payload);
            }
        } elseif ('delete' === $action) {
            self::deleteItemsApi($hashid, 'product', $products);
        }
    }

    /**
     * Update cms pages in Doofinder using the API
     *
     * @param array $cmsPages
     * @param int $shopId
     * @param int $idLang
     * @param int $idCurrency
     * @param string $action
     *
     * @return void
     */
    public static function sendCmsApi($cmsPages, $shopId, $idLang, $idCurrency, $action = 'update')
    {
        if (empty($cmsPages)) {
            return;
        }

        $hashid = SearchEngine::getHashId($idLang, $idCurrency);

        if ($hashid) {
            if ('update' === $action) {
                $builder = new DfCmsBuild($shopId, $idLang);
                $builder->setCmsPages($cmsPages);
                $payload = $builder->build();

                self::updateItemsApi($hashid, 'page', $payload);
            } elseif ('delete' === $action) {
                self::deleteItemsApi($hashid, 'page', $cmsPages);
            }
        }
    }

    /**
     * Update categories in Doofinder using the API
     *
     * @param array $categories
     * @param int $shopId
     * @param int $idLang
     * @param int $idCurrency
     * @param string $action
     *
     * @return void
     */
    public static function sendCategoryApi($categories, $shopId, $idLang, $idCurrency, $action = 'update')
    {
        if (empty($categories)) {
            return;
        }

        $hashid = SearchEngine::getHashId($idLang, $idCurrency);

        if ($hashid) {
            if ('update' === $action) {
                $builder = new DfCategoryBuild($shopId, $idLang);
                $builder->setCategories($categories);
                $payload = $builder->build();

                self::updateItemsApi($hashid, 'category', $payload);
            } elseif ('delete' === $action) {
                self::deleteItemsApi($hashid, 'category', $categories);
            }
        }
    }

    /**
     * Updates items in Doofinder API.
     *
     * This function sends a bulk update request to the Doofinder API for the specified hashid, item type, and payload.
     * It requires the API key and region configuration to initialize the API client and handle the response.
     *
     * @param string $hashid the Doofinder search engine hashid
     * @param string $type The type of items to update (e.g., 'product', 'category', 'cms').
     * @param array $payload The data payload containing the items to update. This is an associative array.
     *
     * @return void
     */
    private static function updateItemsApi($hashid, $type, $payload)
    {
        if (empty($payload)) {
            return;
        }

        $apiKey = explode('-', \Configuration::get('DF_API_KEY'))[1];
        $region = \Configuration::get('DF_REGION');

        $api = new DoofinderApiItems($hashid, $apiKey, $region, $type);
        $response = $api->updateBulk($payload);

        if (isset($response['error']) && !empty($response['error'])) {
            DoofinderConfig::debug(json_encode($response['error']));
        }
    }

    /**
     * Deletes items in Doofinder API.
     *
     * This function sends a bulk delete request to the Doofinder API for the specified hashid, item type, and payload.
     * It requires the API key and region configuration to initialize the API client and handle the response.
     *
     * @param string $hashid the Doofinder search engine hashid
     * @param string $type The type of items to delete (e.g., 'product', 'category', 'cms').
     * @param array $payload The data payload containing the items to delete. This is an associative array.
     *
     * @return void
     */
    private static function deleteItemsApi($hashid, $type, $payload)
    {
        if (empty($payload)) {
            return;
        }

        $apiKey = explode('-', \Configuration::get('DF_API_KEY'))[1];
        $region = \Configuration::get('DF_REGION');

        $api = new DoofinderApiItems($hashid, $apiKey, $region, $type);
        $response = $api->deleteBulk(json_encode($payload));

        if (isset($response['error']) && !empty($response['error'])) {
            DoofinderConfig::debug(json_encode($response['error']));
        }
    }

    /**
     * Invokes the reindexing process via Doofinder API.
     *
     * This function triggers a reindexing process for the current Doofinder installation by sending a request to
     * Doofinder API. It retrieves the API key and region from the configuration, initializes the API client, and
     * sends the reindexing request.
     *
     * @return void
     */
    public static function indexApiInvokeReindexing()
    {
        $region = \Configuration::get('DF_REGION');
        $apiKey = \Configuration::get('DF_API_KEY');
        $api = new DoofinderApiIndex($apiKey, $region);
        $response = $api->invokeReindexing(\Configuration::get('DF_INSTALLATION_ID'), UrlManager::getProcessCallbackUrl());
        if (empty($response) || 200 !== $response['status']) {
            DoofinderConfig::debug('Error while invoking reindexing: ' . json_encode($response));

            return;
        }

        \Configuration::updateValue('DF_FEED_INDEXED', false);
    }

    /**
     * Validates the Doofinder Update on Save.
     *
     * This function checks if the Update on Save is valid by sending a request to the Doofinder API.
     * If the response is invalid or empty, it logs an error and updates the configuration to prevent further update delays.
     *
     * @return bool returns `true` if the installation is valid, otherwise `false`
     */
    public static function isValid()
    {
        $region = \Configuration::get('DF_REGION');
        $apiKey = \Configuration::get('DF_API_KEY');
        $api = new DoofinderInstallation($apiKey, $region);
        $decodeResponse = $api->isValidUpdateOnSave(\Configuration::get('DF_INSTALLATION_ID'));

        if (empty($decodeResponse['valid?'])) {
            DoofinderConfig::debug('Error checking search engines: ' . json_encode($decodeResponse));

            \Configuration::updateValue('DF_UPDATE_ON_SAVE_DELAY', 0);

            return false;
        }

        return $decodeResponse['valid?'];
    }
}
