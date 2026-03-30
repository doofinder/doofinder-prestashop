<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * @see       https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Manager;

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;
use PrestaShop\Module\Doofinder\Core\UpdateOnSave;
use PrestaShop\Module\Doofinder\Utils\DfTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class HookManager
 *
 * Handles registration of module hooks, preparation of Smarty variables for hooks,
 * and updates related to specific PrestaShop events (product, CMS, and category changes).
 */
class HookManager
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
     * Registers the hooks when the plugin is installed
     *
     * @return bool
     */
    public function registerHooks()
    {
        $result = $this->module->registerHook('displayHeader')
            && $this->module->registerHook('moduleRoutes')
            && $this->module->registerHook('actionProductSave')
            && $this->module->registerHook('actionProductDelete')
            && $this->module->registerHook('actionObjectCmsAddAfter')
            && $this->module->registerHook('actionObjectCmsUpdateAfter')
            && $this->module->registerHook('actionObjectCmsDeleteAfter')
            && $this->module->registerHook('actionObjectCategoryAddAfter')
            && $this->module->registerHook('actionObjectCategoryUpdateAfter')
            && $this->module->registerHook('actionObjectCategoryDeleteAfter');

        return $result;
    }

    /**
     * Retrieves common variables for assigning to Smarty templates in a hook context.
     *
     * This function prepares a set of key variables needed for rendering Doofinder-related data in a Smarty template.
     * These include language and currency codes, search engine hashid, region, script and CSS configurations, product links, etc.
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
        $context = \Context::getContext();
        $idShopGroup = $context->shop->id_shop_group;
        $idShop = $context->shop->id;

        $lang = \Tools::strtoupper($languageCode);
        $currency = \Tools::strtoupper($currencyCode);
        $searchEngineId = \Configuration::get('DF_HASHID_' . $currency . '_' . $lang, null, $idShopGroup, $idShop);
        $dfRegion = \Configuration::get('DF_REGION');
        $script = \Configuration::get('DOOFINDER_SCRIPT_' . $lang, null, $idShopGroup, $idShop);
        $extraCss = \Configuration::get('DF_EXTRA_CSS', null, $idShopGroup, $idShop);
        $installationID = \Configuration::get('DF_INSTALLATION_ID', null, $idShopGroup, $idShop);
        $selfPath = dirname($_SERVER['SCRIPT_FILENAME']);
        if (!is_dir($selfPath)) {
            $selfPath = dirname(__FILE__);
        }

        $configScriptBaseUrl = sprintf(DoofinderConstants::CONFIG_REGION_URL, $dfRegion) . '/2.x';
        $templateVars = [
            'ENT_QUOTES' => ENT_QUOTES,
            'lang' => $languageCode,
            'script_html' => DfTools::fixScriptTag($script),
            'extra_css_html' => DfTools::fixStyleTag($extraCss),
            'productLinks' => $productLinks,
            'search_engine_id' => $searchEngineId,
            'self' => $selfPath,
            'df_another_params' => $extraParams,
            'installation_ID' => $installationID,
            'currency' => $currency,
            'config_script_base_url' => $configScriptBaseUrl,
            'customer' => (array) $context->customer,
            'is_customer_logged' => $context->customer->isLogged(),
            'is_customer_group_feature_active' => \Group::isFeatureActive(),
            'customer_group_hide_prices' => 'false',
        ];

        if ($context->customer->isLogged()) {
            $templateVars['customer_group_hide_prices'] = (DfTools::getCustomerGroupPriceVisibility($context->customer->id_default_group)) ? 'false' : 'true';
        }

        $templateVars['df_page_type'] = self::getPageType();
        $templateVars['df_product_id'] = self::getProductId();
        $templateVars['df_category_name'] = self::getCategoryName();

        return $templateVars;
    }

    /**
     * Resolve the current page type from the front controller.
     *
     * Maps PrestaShop's controller php_self value to a canonical page type
     * string consumed by the Doofinder layer.
     *
     * @return string One of: home, product, category, search, cart, checkout, other
     */
    private static function getPageType()
    {
        $context = \Context::getContext();
        $phpSelf = isset($context->controller->php_self) ? $context->controller->php_self : '';

        switch ($phpSelf) {
            case 'index':
                return 'home';
            case 'product':
            case 'category':
            case 'search':
            case 'cart':
                return $phpSelf;
            case 'order':
            case 'order-opc':
                return 'checkout';
            default:
                return 'other';
        }
    }

    /**
     * Get the current product ID when on a product page.
     *
     * @return string Product ID as string, or empty string when not on a product page
     */
    private static function getProductId()
    {
        $context = \Context::getContext();
        $phpSelf = isset($context->controller->php_self) ? $context->controller->php_self : '';

        if ($phpSelf !== 'product') {
            return '';
        }

        $idProduct = (int) \Tools::getValue('id_product');

        return $idProduct > 0 ? (string) $idProduct : '';
    }

    /**
     * Get the category name (full tree path) for the current page.
     *
     * On a product page returns the path of the product's default category.
     * On a category page returns the path of the viewed category.
     * Uses DfTools::getCategoryPath with the DF_FEED_FULL_PATH setting so the
     * value matches what is indexed in the product feed.
     *
     * @return string Category path using ">" as separator, or empty string
     */
    private static function getCategoryName()
    {
        $context = \Context::getContext();
        $phpSelf = isset($context->controller->php_self) ? $context->controller->php_self : '';
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $useFullPath = (bool) DfTools::cfg($idShop, 'DF_FEED_FULL_PATH', DoofinderConstants::YES);

        if ($phpSelf === 'product') {
            $idProduct = (int) \Tools::getValue('id_product');
            if ($idProduct > 0) {
                $product = new \Product($idProduct, false, $idLang, $idShop);
                if (\Validate::isLoadedObject($product) && $product->id_category_default) {
                    return DfTools::getCategoryPath((int) $product->id_category_default, $idLang, $idShop, $useFullPath);
                }
            }
        } elseif ($phpSelf === 'category') {
            $idCategory = (int) \Tools::getValue('id_category');
            if ($idCategory > 0) {
                return DfTools::getCategoryPath($idCategory, $idLang, $idShop, $useFullPath);
            }
        }

        return '';
    }

    /**
     * Returns an array of routes handled by this module.
     *
     * Defines custom URLs that map to specific controllers within the module.
     *
     * @return array module routes and their associated controllers, rules, keywords, and parameters
     */
    public static function getHookModuleRoutes()
    {
        return [
            'module-doofinder-config' => [
                'controller' => 'config',
                'rule' => 'module/doofinder/config',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'doofinder',
                    'controller' => 'config',
                ],
            ],
            'module-doofinder-ajax' => [
                'controller' => 'ajax',
                'rule' => 'module/doofinder/ajax',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'doofinder',
                    'controller' => 'ajax',
                ],
            ],
            'module-doofinder-feed' => [
                'controller' => 'feed',
                'rule' => 'module/doofinder/feed',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'doofinder',
                    'controller' => 'feed',
                ],
            ],
            'module-doofinder-callback' => [
                'controller' => 'callback',
                'rule' => 'module/doofinder/callback',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'doofinder',
                    'controller' => 'callback',
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
