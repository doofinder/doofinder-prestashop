<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licensed under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the license agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 */

namespace PrestaShop\Module\Doofinder\Manager;

use PrestaShop\Module\Doofinder\Api\DoofinderApi;
use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Core\SearchEngine;
use PrestaShop\Module\Doofinder\Core\UpdateOnSave;
use PrestaShop\Module\Doofinder\Utils\DfTools;
use PrestaShop\Module\Doofinder\View\DoofinderAdminPanelView;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * FormManager class
 *
 * Handles the processing of backoffice configuration forms for the Doofinder module.
 * Manages form submissions, validation, and configuration updates across different tabs
 * including store info, data feed, and advanced settings.
 */
class FormManager
{
    /**
     * Doofinder main module class object
     *
     * @var \Doofinder
     */
    private $module;

    /**
     * Constructor
     *
     * @param \Doofinder $module The main Doofinder module instance
     */
    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Process the backoffice configuration form
     *
     * Handles form submissions from different configuration tabs:
     * - Store information (installation ID, API key)
     * - Data feed settings
     * - Advanced parameters
     * - Reindexing requests
     *
     * Validates input data, updates configuration values, and provides user feedback.
     *
     * @return string HTML messages for user feedback (errors, warnings, confirmations)
     */
    public function postProcess()
    {
        $formValues = [];
        $formUpdated = '';
        $messages = '';
        $context = \Context::getContext();
        $idShop = $context->shop->id;

        $isFirstTime = (bool) \Tools::getValue('first_time', 0);
        $isAdvParamPresent = (bool) \Tools::getValue('adv', 0);

        if ($isFirstTime) {
            $shops = \Shop::getShops();
            foreach ($shops as $shop) {
                $shopGroupId = $shop['id_shop_group'];
                $shopId = $shop['id_shop'];
                DoofinderConfig::setSharedDefaultConfig($shopGroupId, $shopId);
            }
            \Configuration::updateGlobalValue('DF_FEED_INDEXED', true);
            DoofinderConfig::setSharedGlobalDefaultConfig();
        }

        $multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');

        if ((bool) \Tools::isSubmit('submitDoofinderModuleLaunchReindexing')) {
            UpdateOnSave::indexApiInvokeReindexing();
        }
        if (((bool) \Tools::isSubmit('submitDoofinderModuleDataFeed')) == true) {
            $formValues = array_merge($formValues, DoofinderConfig::getConfigFormValuesDataFeed($idShop));
            $formUpdated = 'data_feed_tab';
        }

        if (((bool) \Tools::isSubmit('submitDoofinderModuleAdvanced')) == true) {
            $formValues = array_merge($formValues, DoofinderConfig::getConfigFormValuesAdvanced($idShop));
            $formUpdated = 'advanced_tab';
            $context->smarty->assign('adv', 1);
        }

        $adminPanelView = new DoofinderAdminPanelView($this->module);
        if (((bool) \Tools::isSubmit('submitDoofinderModuleStoreInfo')) == true) {
            $storeSubmissionErrors = false;

            $installationId = trim(\Tools::getValue('DF_INSTALLATION_ID'));
            if (DfTools::validateInstallationId($installationId)) {
                $formValues['DF_INSTALLATION_ID'] = $installationId;
            } else {
                $messages .= $adminPanelView->displayErrorCtm($this->module->l('The Store ID must be in UUID format, for example: 3c49f881-5988-4e32-a581-1d577d7d55c0', 'formmanager'));
                $storeSubmissionErrors = true;
            }

            $apiKey = trim(\Tools::getValue('DF_API_KEY'));
            if (DfTools::validateApiKey($apiKey)) {
                $formValues['DF_API_KEY'] = $apiKey;
            } else {
                $messages .= $adminPanelView->displayErrorCtm($this->module->l('The API Key must be in the correct format, for example: eu1-29c05dbdab18cbbb65c95305bb767bab1240a528', 'formmanager'));
                $storeSubmissionErrors = true;
            }

            if (!$storeSubmissionErrors) {
                $formValues = array_merge($formValues, DoofinderConfig::getConfigFormValuesStoreInfo($idShop));
                $formUpdated = 'store_info_tab';
            }
        }

        foreach (array_keys($formValues) as $key) {
            $postKey = str_replace(['[', ']'], '', $key);
            $value = \Tools::getValue($postKey);

            if (isset($formValues[$key]['real_config'])) {
                $postKey = $formValues[$key]['real_config'];
            }
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if ($postKey === 'DF_FEED_FULL_PATH') {
                \Configuration::updateValue('DF_FEED_MAINCATEGORY_PATH', 0);
            }
            $value = trim($value);
            // Special case for Hashids due to the Multiprice
            if ($isAdvParamPresent && $multipriceEnabled && DfTools::str_contains($postKey, 'DF_HASHID')) {
                self::updateHashIds($postKey, $value);
                continue;
            }
            \Configuration::updateValue($postKey, $value);
        }

        if ($formUpdated == 'data_feed_tab') {
            if ((bool) \Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
                SearchEngine::setSearchEnginesByConfig();
            }
            if (\Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') && (int) \Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') < 5) {
                \Configuration::updateValue('DF_UPDATE_ON_SAVE_DELAY', 5);
            }

            $context->smarty->assign('text_data_changed', $this->module->l('You\'ve just changed a data feed option. It may be necessary to reprocess the index to apply these changes effectively.', 'formmanager'));
            $context->smarty->assign('text_reindex', $this->module->l('Launch reindexing', 'formmanager'));
            $msg = $context->smarty->fetch(DoofinderAdminPanelView::getLocalPath() . 'views/templates/admin/reindex.tpl');
            $messages .= $adminPanelView->displayWarningCtm($msg, false, true);
        }

        // Check connection
        if ($formUpdated == 'store_info_tab') {
            $hashid = SearchEngine::getHashId($context->language->id, $context->currency->id);
            $apiKey = \Configuration::get('DF_API_KEY');
            $dfApi = new DoofinderApi($hashid, $apiKey);
            $messages .= $dfApi->checkConnection($this->module);
        }

        if (!empty($formUpdated)) {
            $messages .= $adminPanelView->displayConfirmationCtm($this->module->l('Settings updated!', 'formmanager'));
            $context->smarty->assign('formUpdatedToClick', $formUpdated);
        }

        return $messages;
    }

    /**
     * Update Hashids configuration for multiprice scenarios
     *
     * When advanced parameters are present and multiprice is enabled,
     * this method updates all related Hashids configuration keys.
     *
     * @param string $postKey The configuration key being updated
     * @param string $value The new value to set
     *
     * @return void
     */
    private static function updateHashIds($postKey, $value)
    {
        $hashidKeys = DfTools::getHashidKeys();
        $hashidKeys = array_filter($hashidKeys, function ($hashidKey) use ($postKey) {
            return $hashidKey['keyMultiprice'] === $postKey;
        });
        foreach ($hashidKeys as $hashidKey) {
            \Configuration::updateValue($hashidKey['key'], $value);
        }
    }
}
