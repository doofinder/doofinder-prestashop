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

use PrestaShop\Module\Doofinder\Lib\DfTools;
use PrestaShop\Module\Doofinder\Lib\DoofinderAdminPanelView;
use PrestaShop\Module\Doofinder\Lib\DoofinderApi;
use PrestaShop\Module\Doofinder\Lib\DoofinderConstants;
use PrestaShop\Module\Doofinder\Lib\DoofinderInstallation;
use PrestaShop\Module\Doofinder\Lib\DoofinderScript;
use PrestaShop\Module\Doofinder\Lib\EasyREST;
use PrestaShop\Module\Doofinder\Lib\HookManager;
use PrestaShop\Module\Doofinder\Lib\SearchEngine;
use PrestaShop\Module\Doofinder\Lib\UpdateOnSave;
use PrestaShop\Module\Doofinder\Lib\UrlManager;

class Doofinder extends Module
{
    protected $html = '';
    protected $postErrors = [];
    protected $productLinks = [];
    public $ps_layered_full_tree = true;
    public $searchBanner = false;
    public $admin_template_dir = '';
    public $hookManager;

    // TODO (davidmolinacano): To be deleted after complete refactor.
    const GS_SHORT_DESCRIPTION = DoofinderConstants::GS_SHORT_DESCRIPTION;
    const GS_LONG_DESCRIPTION = DoofinderConstants::GS_LONG_DESCRIPTION;
    const VERSION = DoofinderConstants::VERSION;
    const NAME = DoofinderConstants::NAME;
    const YES = DoofinderConstants::YES;
    const NO = DoofinderConstants::NO;

    public function __construct()
    {
        $this->name = 'doofinder';
        $this->tab = 'search_filter';
        $this->version = '4.8.9';
        $this->author = 'Doofinder (http://www.doofinder.com)';
        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];
        $this->module_key = 'd1504fe6432199c7f56829be4bd16347';
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Doofinder');
        $this->description = $this->l('Install Doofinder in your shop with no effort');

        $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service');
        $this->admin_template_dir = '../../../../modules/' . $this->name . '/views/templates/admin/';
        $this->hookManager = new HookManager($this);
    }

    /**
     * Install the module
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && DoofinderInstallation::installDb()
            && DoofinderInstallation::installTabs()
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
            && DoofinderInstallation::uninstallTabs()
            && DoofinderInstallation::deleteConfigVars()
            && DoofinderInstallation::uninstallDb();
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
        return HookManager::getHookModuleRoutes();
    }

    /**
     * Update the hashid of the search engines of the store in the configuration.
     * It must be declared here too to be used by upgrade 4.5.0.
     *
     * @return true
     */
    public function setSearchEnginesByConfig()
    {
        return SearchEngine::setSearchEnginesByConfig();
    }

    /**
     * Handles the module's configuration page
     *
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $adminPanelView = new DoofinderAdminPanelView($this);
        $stop = $adminPanelView->getWarningMultishopHtml();
        if ($stop) {
            return $stop;
        }
        $adv = Tools::getValue('adv', 0);

        $this->context->smarty->assign('adv', $adv);

        $msg = $this->postProcess();

        $output = $msg;
        $oldPS = false;
        $this->context->controller->addJS($this->_path . 'views/js/admin-panel.js');

        if (_PS_VERSION_ < 1.6) {
            $oldPS = true;
            $this->context->controller->addJS($this->_path . 'views/js/plugins/bootstrap.min.js');
            $this->context->controller->addCSS($this->_path . 'views/css/admin-theme_15.css');
        }
        $configured = $this->isConfigured();
        $is_new_shop = $this->showNewShopForm(Context::getContext()->shop);
        $shop_id = null;
        if ($is_new_shop) {
            $shop_id = Context::getContext()->shop->id;
        }

        $skip_url_params = [
            'skip' => 1,
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ];
        $skipurl = $this->context->link->getAdminLink('AdminModules', true);
        $separator = strpos($skipurl, '?') === false ? '?' : '&';
        $skipurl .= $separator . http_build_query($skip_url_params);

        $redirect = $this->context->shop->getBaseURL(true, false) . $this->_path . 'config.php';
        $token = Tools::encrypt($redirect);
        $paramsPopup = 'email=' . $this->context->employee->email . '&token=' . $token;
        $dfEnabledV9 = Configuration::get('DF_ENABLED_V9');

        $this->context->smarty->assign('oldPS', $oldPS);
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('configured', $configured);
        $this->context->smarty->assign('is_new_shop', $is_new_shop);
        $this->context->smarty->assign('shop_id', $shop_id);
        $this->context->smarty->assign('checkConnection', $this->checkOutsideConnection());
        $this->context->smarty->assign('tokenAjax', Tools::encrypt('doofinder-ajax'));
        $this->context->smarty->assign('skipurl', $skipurl);
        $this->context->smarty->assign('paramsPopup', $paramsPopup);
        $this->context->smarty->assign('dfEnabledV9', $dfEnabledV9);

        $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        if ($configured) {
            $feed_indexed = Configuration::get('DF_FEED_INDEXED', false);
            if (empty($feed_indexed)) {
                $controller_url = $this->context->link->getAdminLink('DoofinderAdmin', true) . '&ajax=1';
                $this->context->smarty->assign('update_feed_url', $controller_url . '&action=UpdateConfigurationField');
                $this->context->smarty->assign('check_feed_url', $controller_url . '&action=CheckConfigurationField');
                $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/indexation_status.tpl');
            }

            $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure_administration_panel.tpl');
            $output .= $this->renderFormDataFeed($adv);
            if ($adv) {
                $output .= $this->renderFormAdvanced();
            }
            $adv_url = $this->context->link->getAdminLink('AdminModules', true) . '&adv=1'
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
            $this->context->smarty->assign('adv_url', $adv_url);
        }

        $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure_footer.tpl');

        return $output;
    }

    /**
     * Check if the module has already been configured
     *
     * @return bool
     */
    protected function isConfigured()
    {
        $skip = Tools::getValue('skip');
        if ($skip) {
            Configuration::updateValue('DF_ENABLE_HASH', 0);
            Configuration::updateValue('DF_ENABLED_V9', true);
        }
        $sql = 'SELECT id_configuration FROM ' . _DB_PREFIX_ . 'configuration WHERE name = \'DF_ENABLE_HASH\'';

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Check if the form to create a store installation has to be displayed
     *
     * @param Shop $shop
     *
     * @return bool
     */
    protected function showNewShopForm($shop)
    {
        $installationId = Configuration::get('DF_INSTALLATION_ID', null, (int) $shop->id_shop_group, (int) $shop->id);
        $multishopEnable = Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE');
        $apiKey = Configuration::get('DF_AI_APIKEY');

        return !$installationId && $multishopEnable && $apiKey;
    }

    /**
     * Get the fields of the search layer configuration form
     *
     * @return array
     */
    protected function getConfigFormSearchLayer()
    {
        $inputs = [
            [
                'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                'label' => $this->l('Doofinder search layer'),
                'name' => 'DF_SHOW_LAYER',
                'is_bool' => true,
                'values' => $this->getBooleanFormValue(),
            ],
            [
                'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                'label' => $this->l('Doofinder search layer in mobile version'),
                'name' => 'DF_SHOW_LAYER_MOBILE',
                'is_bool' => true,
                'values' => $this->getBooleanFormValue(),
            ],
            [
                'type' => 'text',
                'label' => $this->l('Doofinder Store ID'),
                'name' => 'DF_INSTALLATION_ID',
                'desc' => $this->l('INSTALLATION_ID_EXPLANATION'),
                'lang' => false,
            ],
        ];

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Search Layer'),
                ],
                'input' => $inputs,
                'submit' => [
                    'title' => $this->l('Save Layer Widget Options'),
                    'name' => 'submitDoofinderModuleSearchLayer',
                ],
            ],
        ];
    }

    /**
     * Get the values for the search layer configuration form
     *
     * @return array
     */
    protected function getConfigFormValuesSearchLayer()
    {
        $fields = [];
        $fields['DF_INSTALLATION_ID'] = Configuration::get('DF_INSTALLATION_ID');
        $fields['DF_SHOW_LAYER'] = Configuration::get('DF_SHOW_LAYER', null, null, null, true);
        $fields['DF_SHOW_LAYER_MOBILE'] = Configuration::get('DF_SHOW_LAYER_MOBILE', null, null, null, true);

        return $fields;
    }

    /**
     * Render the data feed configuration form
     *
     * @param bool $adv
     *
     * @return string
     */
    protected function renderFormDataFeed($adv = false)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        // $helper->submit_action = 'submitDoofinderModuleDataFeed';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '')
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign('id_tab', 'data_feed_tab');
        $html = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/pre_tab.tpl');
        // Data feed form
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesDataFeed(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        if (!$this->showNewShopForm(Context::getContext()->shop)) {
            $valid_update_on_save = UpdateOnSave::isValid();
            $html .= $helper->generateForm([$this->getConfigFormDataFeed($valid_update_on_save)]);
            // Search layer form
            $helper->tpl_vars['fields_value'] = $this->getConfigFormValuesSearchLayer();
            $html .= $helper->generateForm([$this->getConfigFormSearchLayer()]);
        } else {
            $this->context->controller->warnings[] = $this->l("This shop is new and it hasn't been synchronized with Doofinder yet.");
        }
        $html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
    }

    /**
     * Get the fields of the data feed configuration form
     *
     * @return array
     */
    protected function getConfigFormDataFeed($valid_update_on_save = false)
    {
        if ($valid_update_on_save) {
            $disabled = false;
            $query = [
                5 => ['id' => 5, 'name' => sprintf($this->l('Each %s minutes'), '5')],
                15 => ['id' => 15, 'name' => sprintf($this->l('Each %s minutes'), '15')],
                30 => ['id' => 30, 'name' => sprintf($this->l('Each %s minutes'), '30')],
                60 => ['id' => 60, 'name' => $this->l('Each hour')],
                120 => ['id' => 120, 'name' => sprintf($this->l('Each %s hours'), '2')],
                360 => ['id' => 360, 'name' => sprintf($this->l('Each %s hours'), '6')],
                720 => ['id' => 720, 'name' => sprintf($this->l('Each %s hours'), '12')],
                1440 => ['id' => 1440, 'name' => $this->l('Once a day')],
                0 => ['id' => 0, 'name' => $this->l('Disabled')],
            ];
        } else {
            $disabled = true;
            $query = [
                0 => ['id' => 0, 'name' => $this->l('Disabled')],
            ];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Data Feed'),
                ],
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Index product prices'),
                        'desc' => $this->l('If you activate this option you will be able to show the prices of each product in the search results.'),
                        'name' => 'DF_GS_DISPLAY_PRICES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Show product prices including taxes'),
                        'desc' => $this->l('If you activate this option, the price of the products that will be displayed will be inclusive of taxes.'),
                        'name' => 'DF_GS_PRICES_USE_TAX',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Index the full path of the product category'),
                        'name' => 'DF_FEED_FULL_PATH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Index product attribute combinations'),
                        'name' => 'DF_SHOW_PRODUCT_VARIATIONS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Define which combinations of product attributes you want to index for'),
                        'name' => 'DF_GROUP_ATTRIBUTES_SHOWN',
                        'multiple' => true,
                        'options' => [
                            'query' => AttributeGroup::getAttributesGroups(Context::getContext()->language->id),
                            'id' => 'id_attribute_group',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Index customized product features'),
                        'name' => 'DF_SHOW_PRODUCT_FEATURES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Select features will be shown in feed'),
                        'name' => 'DF_FEATURES_SHOWN',
                        'multiple' => true,
                        'options' => [
                            'query' => Feature::getFeatures(
                                Context::getContext()->language->id,
                                $this->context->shop->id
                            ),
                            'id' => 'id_feature',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Product Image Size'),
                        'name' => 'DF_GS_IMAGE_SIZE',
                        'options' => [
                            'query' => DfTools::getAvailableImageSizes(),
                            'id' => 'DF_GS_IMAGE_SIZE',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Automatically process modified products'),
                        'desc' => $this->l('This action will only be executed if there are changes. If you see the field disabled, it is because you are making a usage in the indexes that is not supported by the automatic processing of modified products.'),
                        'name' => 'DF_UPDATE_ON_SAVE_DELAY',
                        'disabled' => $disabled,
                        'options' => [
                            'query' => $query,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Data Feed Options'),
                    'name' => 'submitDoofinderModuleDataFeed',
                ],
            ],
        ];
    }

    /**
     * Get the values for the data feed configuration form
     *
     * @return array
     */
    protected function getConfigFormValuesDataFeed()
    {
        return [
            'DF_GS_DISPLAY_PRICES' => Configuration::get('DF_GS_DISPLAY_PRICES'),
            'DF_GS_PRICES_USE_TAX' => Configuration::get('DF_GS_PRICES_USE_TAX'),
            'DF_FEED_FULL_PATH' => Configuration::get('DF_FEED_FULL_PATH'),
            'DF_SHOW_PRODUCT_VARIATIONS' => Configuration::get('DF_SHOW_PRODUCT_VARIATIONS'),
            'DF_GROUP_ATTRIBUTES_SHOWN[]' => explode(',', Configuration::get('DF_GROUP_ATTRIBUTES_SHOWN')),
            'DF_SHOW_PRODUCT_FEATURES' => Configuration::get('DF_SHOW_PRODUCT_FEATURES'),
            'DF_FEATURES_SHOWN[]' => explode(',', Configuration::get('DF_FEATURES_SHOWN')),
            'DF_GS_IMAGE_SIZE' => Configuration::get('DF_GS_IMAGE_SIZE'),
            'DF_UPDATE_ON_SAVE_DELAY' => Configuration::get('DF_UPDATE_ON_SAVE_DELAY'),
        ];
    }

    /**
     * Render the advanced configuration form
     *
     * @return string
     */
    protected function renderFormAdvanced()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        // helper->submit_action = 'submitDoofinderModuleAdvanced';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&adv=1&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesAdvanced(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];
        $this->context->smarty->assign('id_tab', 'advanced_tab');
        $html = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/pre_tab.tpl');
        $html .= $this->renderFeedURLs();
        $html .= $helper->generateForm([$this->getConfigFormAdvanced()]);
        $html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
    }

    /**
     * Render the feed url block
     *
     * @return string
     */
    protected function renderFeedURLs()
    {
        $urls = [];
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            foreach (Currency::getCurrencies() as $cur) {
                $currencyIso = Tools::strtoupper($cur['iso_code']);
                $langIso = Tools::strtoupper($lang['iso_code']);
                $urls[] = [
                    'url' => UrlManager::getFeedUrl($this->context->shop->id, $langIso, $currencyIso),
                    'lang' => $langIso,
                    'currency' => $currencyIso,
                ];
            }
        }
        $this->context->smarty->assign('df_feed_urls', $urls);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/feed_url_partial_tab.tpl');
    }

    /**
     * Get the fields of the advanced configuration form
     *
     * @return array
     */
    protected function getConfigFormAdvanced()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Advanced Options'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Doofinder Api Key'),
                        'name' => 'DF_API_KEY',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Region'),
                        'name' => 'DF_REGION',
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Enable v9 layer (Livelayer)'),
                        'name' => 'DF_ENABLED_V9',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Debug Mode. Write info logs in doofinder.log file'),
                        'name' => 'DF_DEBUG',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('CURL disable HTTPS check'),
                        'name' => 'DF_DSBL_HTTPS_CURL',
                        'desc' => $this->l('CURL_DISABLE_HTTPS_EXPLANATION'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Debug CURL error response'),
                        'name' => 'DF_DEBUG_CURL',
                        'desc' => $this->l('To debug if your server has symptoms of connection problems'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Internal Search Options'),
                    'name' => 'submitDoofinderModuleAdvanced',
                ],
            ],
        ];
    }

    /**
     * Get the values for the advanced configuration form
     *
     * @return array
     */
    protected function getConfigFormValuesAdvanced()
    {
        return [
            'DF_API_KEY' => Configuration::get('DF_API_KEY'),
            'DF_REGION' => Configuration::get('DF_REGION'),
            'DF_ENABLED_V9' => Configuration::get('DF_ENABLED_V9'),
            'DF_DEBUG' => Configuration::get('DF_DEBUG'),
            'DF_DSBL_HTTPS_CURL' => Configuration::get('DF_DSBL_HTTPS_CURL'),
            'DF_DEBUG_CURL' => Configuration::get('DF_DEBUG_CURL'),
        ];
    }

    /**
     * Process the backoffice configuration form
     *
     * @return string
     */
    protected function postProcess()
    {
        $form_values = [];
        $formUpdated = '';
        $messages = '';

        if ((bool) Tools::isSubmit('submitDoofinderModuleLaunchReindexing')) {
            UpdateOnSave::indexApiInvokeReindexing();
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleDataFeed')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesDataFeed());
            $formUpdated = 'data_feed_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleSearchLayer')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesSearchLayer());
            $formUpdated = 'search_layer_tab';
        }

        if (((bool) Tools::isSubmit('submitDoofinderModuleAdvanced')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesAdvanced());
            $formUpdated = 'advanced_tab';
            $hashid = SearchEngine::getHashId(Context::getContext()->language->id, Context::getContext()->currency->id);
            $apiKey = Configuration::get('DF_API_KEY');
            $dfApi = new DoofinderApi($hashid, $apiKey, false, ['apiVersion' => '5']);
            $messages .= $dfApi->test([$this, 'l']);
            $this->context->smarty->assign('adv', 1);
        }

        $adminPanelView = new DoofinderAdminPanelView($this);

        foreach (array_keys($form_values) as $key) {
            $postKey = str_replace(['[', ']'], '', $key);
            $value = Tools::getValue($postKey);

            if (isset($form_values[$key]['real_config'])) {
                $postKey = $form_values[$key]['real_config'];
            }
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if ($postKey === 'DF_FEED_FULL_PATH') {
                Configuration::updateValue('DF_FEED_MAINCATEGORY_PATH', 0);
            }
            $value = trim($value);
            Configuration::updateValue($postKey, $value);
        }

        if ($formUpdated == 'data_feed_tab') {
            if ((bool) Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
                SearchEngine::setSearchEnginesByConfig();
            }
            if (Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') && (int) Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') < 5) {
                Configuration::updateValue('DF_UPDATE_ON_SAVE_DELAY', 5);
            }

            $this->context->smarty->assign('text_data_changed', $this->l('You\'ve just changed a data feed option. It may be necessary to reprocess the index to apply these changes effectively.'));
            $this->context->smarty->assign('text_reindex', $this->l('Launch reindexing'));
            $msg = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/reindex.tpl');
            $messages .= $adminPanelView->displayWarningCtm($msg, false, true);
        }

        if (!empty($formUpdated)) {
            $messages .= $adminPanelView->displayConfirmationCtm($this->l('Settings updated!'));
            $this->context->smarty->assign('formUpdatedToClick', $formUpdated);
        }

        return $messages;
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
            HookManager::getHookCommonSmartyAssigns(
                $this->context->language->language_code,
                $this->context->currency->iso_code,
                $this->productLinks,
                $params
            )
        );
    }

    /**
     * @hook displayHeader FrontControllerCore
     */
    public function hookHeader($params)
    {
        if (!DoofinderScript::searchLayerMustBeInitialized()) {
            return '';
        }

        $this->configureHookCommon($params);

        return $this->displayScriptLiveLayer();
    }

    /**
     * Render the script for the Livelayer search layer
     *
     * @return string
     */
    public function displayScriptLiveLayer()
    {
        $this->context->controller->addJS(DoofinderScript::getScriptLiveLayerPath($this->_path));

        return $this->display(__FILE__, 'views/templates/front/scriptV9.tpl');
    }

    /**
     * @hook actionProductSave ProductCore
     */
    public function hookActionProductSave($params)
    {
        $action = $params['product']->active ? 'update' : 'delete';
        HookManager::proccessHookUpdateOnSave('product', $params['id_product'], $this->context->shop->id, $action);
    }

    /**
     * @hook actionProductDelete ProductCore
     */
    public function hookActionProductDelete($params)
    {
        HookManager::proccessHookUpdateOnSave('product', $params['id_product'], $this->context->shop->id, 'delete');
    }

    /**
     * @hook actionObjectCmsAddAfter ObjectModelCore
     */
    public function hookActionObjectCmsAddAfter($params)
    {
        if ($params['object']->active) {
            HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, 'update');
        }
    }

    /**
     * @hook actionObjectCmsUpdateAfter ObjectModelCore
     */
    public function hookActionObjectCmsUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, $action);
    }

    /**
     * @hook actionObjectCmsDeleteAfter ObjectModelCore
     */
    public function hookActionObjectCmsDeleteAfter($params)
    {
        HookManager::proccessHookUpdateOnSave('cms', $params['object']->id, $this->context->shop->id, 'delete');
    }

    /**
     * @hook actionObjectCategoryAddAfter ObjectModelCore
     */
    public function hookActionObjectCategoryAddAfter($params)
    {
        if ($params['object']->active) {
            HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, 'update');
        }
    }

    /**
     * @hook actionObjectCategoryUpdateAfter ObjectModelCore
     */
    public function hookActionObjectCategoryUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, $action);
    }

    /**
     * @hook actionObjectCategoryDeleteAfter ObjectModelCore
     */
    public function hookActionObjectCategoryDeleteAfter($params)
    {
        HookManager::proccessHookUpdateOnSave('category', $params['object']->id, $this->context->shop->id, 'delete');
    }

    /**
     * Checks the connection with DooManager
     *
     * @return bool
     */
    public function checkOutsideConnection()
    {
        $client = new EasyREST(true, 3);
        $doomanangerRegionlessUrl = sprintf(DoofinderConstants::DOOMANAGER_REGION_URL, '');
        $result = $client->get(sprintf('%s/auth/login', $doomanangerRegionlessUrl));

        return $result && $result->originalResponse && isset($result->headers['code'])
            && (strpos($result->originalResponse, 'HTTP/2 200') || $result->headers['code'] == 200);
    }

    /**
     * Get the language associated with a search engine
     *
     * @param bool $hashid
     *
     * @return bool|int
     */
    public function getLanguageByHashid($hashid)
    {
        $result = Db::getInstance()->getValue('
            SELECT name
            FROM ' . _DB_PREFIX_ . 'configuration
            WHERE name like "DF_HASHID_%" and value = "' . pSQL($hashid) . '";
        ');

        if ($result) {
            $key = str_replace('DF_HASHID_', '', $result);
            $iso_code_parts = explode('_', $key);
            $iso_code = end($iso_code_parts);

            return (int) $this->getLanguageIdByLocale($iso_code);
        } else {
            return false;
        }
    }

    /**
     * Returns language id from locale
     *
     * @param string $locale Locale IETF language tag
     *
     * @return int|false|null
     */
    public function getLanguageIdByLocale($locale)
    {
        $sanitized_locale = pSQL(strtolower($locale));

        return Db::getInstance()
            ->getValue(
                'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang`
                WHERE `language_code` = \'' . $sanitized_locale . '\'
                OR `iso_code` = \'' . $sanitized_locale . '\''
            );
    }

    private function getBooleanFormValue()
    {
        $option = [
            [
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Enabled'),
            ],
            [
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Disabled'),
            ],
        ];

        return $option;
    }
}
