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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'src/autoloader.php';

/*
We cannot use the `use` statement in the main module file due to a eval function
included in PrestaShop 1.6.X or lower. That's why several classes are defined with
the namespace prepended directly:
https://github.com/gskema/prestashop-1.6-module-boilerplate/blob/master/docs/namespaces.md#how-to-use-php-namespaces-in-prestashop-modules
*/

/**
 * Doofinder module main class.
 *
 * Responsible for module lifecycle (install/uninstall), configuration screen rendering,
 * hook registration/handling, and integration with Doofinder's frontend layer and APIs.
 *
 * @since 1.0.0
 */
class Doofinder extends Module
{
    /**
     * HTML buffer used to accumulate configuration output in the back office.
     *
     * @var string
     */
    protected $html = '';
    /**
     * Collection of form validation errors to be displayed to the merchant.
     *
     * @var array<int, string>
     */
    protected $postErrors = [];
    /**
     * Map of index name to product URL pattern used by the JS layer.
     *
     * @var array<string, string>
     */
    protected $productLinks = [];
    /**
     * Compatibility flag with PrestaShop layered navigation.
     *
     * @var bool
     */
    public $ps_layered_full_tree = true;
    /**
     * Whether to display the Doofinder search banner.
     * More info at: https://support.doofinder.com/search/promotional-tools/banners
     *
     * @var bool
     */
    public $searchBanner = false;
    /**
     * Relative path to the admin templates directory used in configuration screens.
     *
     * @var string
     */
    public $admin_template_dir = '';
    /**
     * Hook manager coordinating hook registration and shared hook logic.
     *
     * @var PrestaShop\Module\Doofinder\Src\Entity\HookManager
     */
    public $hookManager;

    /**
     * Module constructor.
     *
     * Initializes module metadata, declares compatibility and bootstraps the hook manager.
     */
    public function __construct()
    {
        $this->name = 'doofinder';
        $this->tab = 'search_filter';
        $this->version = '5.2.0';
        $this->author = 'Doofinder (http://www.doofinder.com)';
        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => '9.0.0'];
        $this->module_key = 'd1504fe6432199c7f56829be4bd16347';
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Doofinder');
        $this->description = $this->l('Install Doofinder in your shop with no effort');

        $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service');
        $this->admin_template_dir = '../../../../modules/' . $this->name . '/views/templates/admin/';
        $this->hookManager = new PrestaShop\Module\Doofinder\Src\Entity\HookManager($this);
    }

    /**
     * Install the module and register all required dependencies.
     *
     * Creates database schema, back office tabs and registers hooks.
     *
     * @return bool True on success, false otherwise
     */
    public function install()
    {
        return parent::install()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::installDb()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::installTabs()
            && $this->hookManager->registerHooks();
    }

    /**
     * Uninstall the module and its dependencies.
     *
     * Removes database schema, back office tabs and configuration entries.
     *
     * @return bool True on success, false otherwise
     */
    public function uninstall()
    {
        return parent::uninstall()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::uninstallTabs()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::deleteConfigVars()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::uninstallDb();
    }

    /**
     * Sets the product links.
     *
     * @param array<string, string> $productLinks Map of index name => product URL
     *
     * @return void
     */
    public function setProductLinks($productLinks)
    {
        $this->productLinks = $productLinks;
    }

    /**
     * Sets a specific product link by index name.
     *
     * @param string $indexName Identifier of the Doofinder index
     * @param string $productLink URL template or absolute link for the index
     *
     * @return void
     */
    public function setProductLinkByIndexName($indexName, $productLink)
    {
        $this->productLinks[$indexName] = $productLink;
    }

    /**
     * Gets the product links.
     *
     * @return array<string, string> Map of index name => product URL
     */
    public function getProductLinks()
    {
        return $this->productLinks;
    }

    /**
     * Add controller routes.
     *
     * Allows the module to declare custom front controllers and their routes.
     *
     * @return array Module routes definition understood by PrestaShop
     */
    public function hookModuleRoutes()
    {
        return PrestaShop\Module\Doofinder\Src\Entity\HookManager::getHookModuleRoutes();
    }

    /**
     * Update the hashid of the search engines of the store in the configuration.
     * It must be declared here too to be used by upgrade 4.5.0.
     *
     * @return bool True on success
     */
    public function setSearchEnginesByConfig()
    {
        return PrestaShop\Module\Doofinder\Src\Entity\SearchEngine::setSearchEnginesByConfig();
    }

    /**
     * Handles the module's configuration page.
     *
     * Builds and returns the admin configuration UI for the module.
     *
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $adminPanelView = new PrestaShop\Module\Doofinder\Src\Entity\DoofinderAdminPanelView($this);

        return $adminPanelView->getContent();
    }

    /**
     * Returns the module path.
     *
     * @return string Base path for this module
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Returns the name of the main table used for modules installed.
     *
     * @return string Table name
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns the identifier of the main table.
     *
     * @return string Table identifier field
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @hook displayHeader FrontControllerCore
     *
     * Injects Doofinder's single script on the front office when the search layer
     * must be initialized.
     *
     * @param array $params Hook parameters provided by PrestaShop
     *
     * @return string Rendered template or empty string when not applicable
     */
    public function hookDisplayHeader($params)
    {
        if (!PrestaShop\Module\Doofinder\Src\Entity\DoofinderScript::searchLayerMustBeInitialized()) {
            return '';
        }

        $this->configureHookCommon($params);

        return $this->displaySingleScript();
    }

    /**
     * Gets the Doofinder single script path according to the PrestaShop version
     * and assigns the corresponding template.
     *
     * @return string Rendered script template
     */
    public function displaySingleScript()
    {
        $this->context->controller->addJS(PrestaShop\Module\Doofinder\Src\Entity\DoofinderScript::getSingleScriptPath($this->_path));

        return $this->display(__FILE__, 'views/templates/front/scriptV9.tpl');
    }

    /**
     * @hook actionProductSave ProductCore
     *
     * Sends product changes to Doofinder when a product is saved.
     *
     * @param array $params Must contain keys 'product' (Product) and 'id_product' (int)
     *
     * @return void
     */
    public function hookActionProductSave($params)
    {
        $action = $params['product']->active ? 'update' : 'delete';
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('product', $params['id_product'], $this->context->shop->id, $action);
    }

    /**
     * @hook actionProductDelete ProductCore
     *
     * Notifies Doofinder when a product is deleted.
     *
     * @param array $params Must contain key 'id_product' (int)
     *
     * @return void
     */
    public function hookActionProductDelete($params)
    {
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('product', $params['id_product'], $this->context->shop->id, 'delete');
    }

    /**
     * @hook actionObjectCmsAddAfter ObjectModelCore
     *
     * Sends newly created CMS pages to Doofinder when active.
     *
     * @param array $params Must contain key 'object' (CMS)
     *
     * @return void
     */
    public function hookActionObjectCmsAddAfter($params)
    {
        if ($params['object']->active) {
            PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, 'update');
        }
    }

    /**
     * @hook actionObjectCmsUpdateAfter ObjectModelCore
     *
     * Updates or removes CMS pages from Doofinder depending on their active state.
     *
     * @param array $params Must contain key 'object' (CMS)
     *
     * @return void
     */
    public function hookActionObjectCmsUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, $action);
    }

    /**
     * @hook actionObjectCmsDeleteAfter ObjectModelCore
     *
     * Notifies Doofinder when a CMS page is deleted.
     *
     * @param array $params Must contain key 'object' (CMS)
     *
     * @return void
     */
    public function hookActionObjectCmsDeleteAfter($params)
    {
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, 'delete');
    }

    /**
     * @hook actionObjectCategoryAddAfter ObjectModelCore
     *
     * Sends newly created categories to Doofinder when active.
     *
     * @param array $params Must contain key 'object' (Category)
     *
     * @return void
     */
    public function hookActionObjectCategoryAddAfter($params)
    {
        if ($params['object']->active) {
            PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, 'update');
        }
    }

    /**
     * @hook actionObjectCategoryUpdateAfter ObjectModelCore
     *
     * Updates or removes categories from Doofinder depending on their active state.
     *
     * @param array $params Must contain key 'object' (Category)
     *
     * @return void
     */
    public function hookActionObjectCategoryUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, $action);
    }

    /**
     * @hook actionObjectCategoryDeleteAfter ObjectModelCore
     *
     * Notifies Doofinder when a category is deleted.
     *
     * @param array $params Must contain key 'object' (Category)
     *
     * @return void
     */
    public function hookActionObjectCategoryDeleteAfter($params)
    {
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, 'delete');
    }

    /**
     * Sets the variables to assign to the template.
     *
     * Prepares common Smarty assignments used by several hooks (language, currency,
     * product links and request context).
     *
     * @param array $params Hook parameters provided by PrestaShop
     *
     * @return void
     */
    private function configureHookCommon($params)
    {
        $this->smarty->assign(
            PrestaShop\Module\Doofinder\Src\Entity\HookManager::getHookCommonSmartyAssigns(
                $this->context->language->language_code,
                $this->context->currency->iso_code,
                $this->productLinks,
                $params
            )
        );
    }
}
