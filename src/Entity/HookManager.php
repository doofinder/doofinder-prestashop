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

class HookManager
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Registers the hooks when the plugin is installed
     *
     * @return true
     */
    public function registerHooks()
    {
        return $this->module->registerHook('displayHeader')
        && $this->module->registerHook('moduleRoutes')
        && $this->module->registerHook('actionProductSave')
        && $this->module->registerHook('actionProductDelete')
        && $this->module->registerHook('actionObjectCmsAddAfter')
        && $this->module->registerHook('actionObjectCmsUpdateAfter')
        && $this->module->registerHook('actionObjectCmsDeleteAfter')
        && $this->module->registerHook('actionObjectCategoryAddAfter')
        && $this->module->registerHook('actionObjectCategoryUpdateAfter')
        && $this->module->registerHook('actionObjectCategoryDeleteAfter');
    }

    /**
     * Retrieves common variables for assigning to Smarty templates in a hook context.
     *
     * This function prepares a set of key variables needed for rendering Doofinder-related data in a Smarty template.
     * These include language and currency codes, search engine ID, region, script and CSS configurations, product links, etc.
     *
     * @param string $languageCode The language code (e.g., 'en-us', 'fr-fr') to be used in the Smarty template.
     * @param string $currencyCode The currency code (e.g., 'USD', 'EUR') to be used in the Smarty template.
     * @param array $productLinks array of product-related links to be used in the template
     * @param mixed $extraParams optional additional parameters that may be assigned to the template
     *
     * @return array returns an associative array of variables to be assigned to the Smarty template,
     *               including language, script, product links, search engine ID, region, and more
     */
    public static function getHookCommonSmartyAssigns($languageCode, $currencyCode, $productLinks, $extraParams = false)
    {
        $lang = \Tools::strtoupper($languageCode);
        $currency = \Tools::strtoupper($currencyCode);
        $searchEngineId = \Configuration::get('DF_HASHID_' . $currency . '_' . $lang);
        $dfRegion = \Configuration::get('DF_REGION');
        $script = \Configuration::get('DOOFINDER_SCRIPT_' . $lang);
        $extraCss = \Configuration::get('DF_EXTRA_CSS');
        $installationID = \Configuration::get('DF_INSTALLATION_ID');
        $selfPath = dirname($_SERVER['SCRIPT_FILENAME']);
        if (!is_dir($selfPath)) {
            $selfPath = dirname(__FILE__);
        }

        return [
            'ENT_QUOTES' => ENT_QUOTES,
            'lang' => $languageCode,
            'script_html' => DfTools::fixScriptTag($script),
            'extra_css_html' => DfTools::fixStyleTag($extraCss),
            'productLinks' => $productLinks,
            'search_engine_id' => $searchEngineId,
            'df_region' => $dfRegion,
            'self' => $selfPath,
            'df_another_params' => $extraParams,
            'installation_ID' => $installationID,
            'currency' => $currency,
        ];
    }

    /**
     * Add controller routes
     *
     * @return array
     */
    public static function getHookModuleRoutes()
    {
        return [
            'module-doofinder-landing' => [
                'controller' => 'landing',
                'rule' => 'df/{landing_name}',
                'keywords' => [
                    'landing_name' => ['regexp' => '[_a-zA-Z0-9_-]+', 'param' => 'landing_name'],
                ],
                'params' => [
                    'fc' => 'module',
                    'module' => 'doofinder',
                    'controller' => 'landing',
                ],
            ],
        ];
    }

    /**
     * Queue the item for update on save
     *
     * @param string $object
     * @param int $idObject
     * @param int $shopId
     * @param string $action
     *
     * @return void
     */
    public static function proccessHookUpdateOnSave($object, $idObject, $shopId, $action)
    {
        if (\Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
            UpdateOnSave::addItemQueue($object, $idObject, $shopId, $action);

            if (UpdateOnSave::allowProcessItemsQueue()) {
                UpdateOnSave::processItemQueue($shopId);
            }
        }
    }
}
