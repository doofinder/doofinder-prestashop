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

require_once 'autoloader.php';

/*
We cannot use the `use` statement in the main module file due to a eval function
included in PrestaShop 1.6.X or lower. That's why several classes are defined with
the namespace prepended directly:
https://github.com/gskema/prestashop-1.6-module-boilerplate/blob/master/docs/namespaces.md#how-to-use-php-namespaces-in-prestashop-modules
*/

class Doofinder extends Module
{
    protected $html = '';
    protected $postErrors = [];
    protected $productLinks = [];
    public $ps_layered_full_tree = true;
    public $searchBanner = false;
    public $admin_template_dir = '';
    public $hookManager;

    public function __construct()
    {
        $this->name = 'doofinder';
        $this->tab = 'search_filter';
        $this->version = '4.11.0';
        $this->author = 'Doofinder (http://www.doofinder.com)';
        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];
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
     * Install the module
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::installDb()
            && PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation::installTabs()
            && $this->hookManager->registerHooks();
    }

    /**
     * Uninstall the module and its dependencies
     *
     * @return bool
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
     * @param array $productLinks
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
     * @param string $indexName identifier of the link
     * @param string $productLink
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
     * @return array
     */
    public function getProductLinks()
    {
        return $this->productLinks;
    }

    /**
     * Add controller routes
     *
     * @return array
     */
    public function hookModuleRoutes()
    {
        return PrestaShop\Module\Doofinder\Src\Entity\HookManager::getHookModuleRoutes();
    }

    /**
     * Update the hashid of the search engines of the store in the configuration.
     * It must be declared here too to be used by upgrade 4.5.0.
     *
     * @return true
     */
    public function setSearchEnginesByConfig()
    {
        return PrestaShop\Module\Doofinder\Src\Entity\SearchEngine::setSearchEnginesByConfig();
    }

    /**
     * Handles the module's configuration page
     *
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $adminPanelView = new PrestaShop\Module\Doofinder\Src\Entity\DoofinderAdminPanelView($this);

        return $adminPanelView->getContent();
    }

    /**
     * Returns the module path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Returns the name of the main table used for modules installed
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns the identifier of the main table
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @hook displayHeader FrontControllerCore
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
     * Gets the Doofinder single script path according to the PrestaShop version.
     *
     * @return string
     */
    public function displaySingleScript()
    {
        $this->context->controller->addJS(PrestaShop\Module\Doofinder\Src\Entity\DoofinderScript::getSingleScriptPath($this->_path));

        return $this->display(__FILE__, 'views/templates/front/scriptV9.tpl');
    }

    /**
     * @hook actionProductSave ProductCore
     */
    public function hookActionProductSave($params)
    {
        $action = $params['product']->active ? 'update' : 'delete';
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('product', $params['id_product'], $this->context->shop->id, $action);
    }

    /**
     * @hook actionProductDelete ProductCore
     */
    public function hookActionProductDelete($params)
    {
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('product', $params['id_product'], $this->context->shop->id, 'delete');
    }

    /**
     * @hook actionObjectCmsAddAfter ObjectModelCore
     */
    public function hookActionObjectCmsAddAfter($params)
    {
        if ($params['object']->active) {
            PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, 'update');
        }
    }

    /**
     * @hook actionObjectCmsUpdateAfter ObjectModelCore
     */
    public function hookActionObjectCmsUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, $action);
    }

    /**
     * @hook actionObjectCmsDeleteAfter ObjectModelCore
     */
    public function hookActionObjectCmsDeleteAfter($params)
    {
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, 'delete');
    }

    /**
     * @hook actionObjectCategoryAddAfter ObjectModelCore
     */
    public function hookActionObjectCategoryAddAfter($params)
    {
        if ($params['object']->active) {
            PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, 'update');
        }
    }

    /**
     * @hook actionObjectCategoryUpdateAfter ObjectModelCore
     */
    public function hookActionObjectCategoryUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, $action);
    }

    /**
     * @hook actionObjectCategoryDeleteAfter ObjectModelCore
     */
    public function hookActionObjectCategoryDeleteAfter($params)
    {
        PrestaShop\Module\Doofinder\Src\Entity\HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, 'delete');
    }

    /**
     * Sets the variables to assign to the template
     *
     * @param array $params
     *
     * @return void
     */
    private function configureHookCommon($params = false)
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
