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

namespace PrestaShop\Module\Doofinder\Lib;

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
            $last_exec = \Configuration::get('DF_UPDATE_ON_SAVE_LAST_EXEC', null, null, null, 0);
            $delay = (int) \Configuration::get('DF_UPDATE_ON_SAVE_DELAY', null, null, null, 30);

            if (is_int($delay)) {
                $last_exec_ts = strtotime($last_exec);

                $diff_min = (time() - $last_exec_ts) / 60;

                if ($diff_min > $delay) {
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

        foreach (['product', 'cms', 'category'] as $type) {
            $itemsUpdate = self::getItemsQueue($shopId, $type, 'update');
            $itemsDelete = self::getItemsQueue($shopId, $type, 'delete');

            foreach ($languages as $language) {
                foreach ($currencies as $currency) {
                    self::{'send' . $type . 'Api'}($itemsUpdate, $shopId, $language['id_lang'], $currency['id_currency']);
                    self::{'send' . $type . 'Api'}($itemsDelete, $shopId, $language['id_lang'], $currency['id_currency'], 'delete');
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
            require_once 'dfProduct_build.php';
            $builder = new \DfProductBuild($shopId, $idLang, $idCurrency);
            $builder->setProducts($products);
            $payload = $builder->build();

            self::updateItemsApi($hashid, 'product', $payload);
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
                require_once 'dfCms_build.php';

                $builder = new \DfCmsBuild($shopId, $idLang);
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
                require_once 'dfCategory_build.php';

                $builder = new \DfCategoryBuild($shopId, $idLang);
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

        require_once 'doofinder_api_items.php';

        $apikey = explode('-', \Configuration::get('DF_API_KEY'))[1];
        $region = \Configuration::get('DF_REGION');

        $api = new \DoofinderApiItems($hashid, $apikey, $region, $type);
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

        require_once 'doofinder_api_items.php';

        $apikey = explode('-', \Configuration::get('DF_API_KEY'))[1];
        $region = \Configuration::get('DF_REGION');

        $api = new \DoofinderApiItems($hashid, $apikey, $region, $type);
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
        require_once 'doofinder_api_index.php';

        $region = \Configuration::get('DF_REGION');
        $api_key = \Configuration::get('DF_API_KEY');
        $api = new \DoofinderApiIndex($api_key, $region);
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
        $api_key = \Configuration::get('DF_API_KEY');
        $api = new DoofinderInstallation($api_key, $region);
        $decode_response = $api->isValidUpdateOnSave(\Configuration::get('DF_INSTALLATION_ID'));

        if (empty($decode_response['valid?'])) {
            DoofinderConfig::debug('Error checking search engines: ' . json_encode($decode_response));

            \Configuration::updateValue('DF_UPDATE_ON_SAVE_DELAY', 0);

            return false;
        }

        return $decode_response['valid?'];
    }
}
