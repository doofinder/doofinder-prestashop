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
if (!class_exists('dfTools')) {
    require_once dirname(__FILE__) . '/lib/dfTools.class.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

class Doofinder extends Module
{
    protected $html = '';
    protected $postErrors = [];
    protected $productLinks = [];
    public $ps_layered_full_tree = true;
    public $searchBanner = false;

    // Feel free to change this value to your own local env or ngrok
    const DOOMANAGER_URL = 'https://admin.doofinder.com';
    const GS_SHORT_DESCRIPTION = 1;
    const GS_LONG_DESCRIPTION = 2;
    const VERSION = '4.7.8';
    const YES = 1;
    const NO = 0;

    public function __construct()
    {
        $this->name = 'doofinder';
        $this->tab = 'search_filter';
        $this->version = '4.7.8';
        $this->author = 'Doofinder (http://www.doofinder.com)';
        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];
        $this->module_key = 'd1504fe6432199c7f56829be4bd16347';
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Doofinder');
        $this->description = $this->l('Install Doofinder in your shop with no effort');

        $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service');
        $this->admin_template_dir = '../../../../modules/' . $this->name . '/views/templates/admin/';

        $olderPS17 = (version_compare(_PS_VERSION_, '1.7', '<') === true);
    }

    /**
     * Install the module
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTabs()
            && $this->registerHook('displayHeader')
            && $this->registerHook('moduleRoutes')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionProductDelete')
            && $this->registerHook('actionObjectCmsAddAfter')
            && $this->registerHook('actionObjectCmsUpdateAfter')
            && $this->registerHook('actionObjectCmsDeleteAfter')
            && $this->registerHook('actionObjectCategoryAddAfter')
            && $this->registerHook('actionObjectCategoryUpdateAfter')
            && $this->registerHook('actionObjectCategoryDeleteAfter');
    }

    /**
     * Install the module database tables
     *
     * @return bool
     */
    public function installDb()
    {
        return Db::getInstance()->execute(
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
            && Db::getInstance()->execute(
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
     * Uninstall the module and its dependencies
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTabs()
            && $this->deleteConfigVars()
            && $this->uninstallDb();
    }

    /**
     * Remove module-dependent configuration variables
     *
     * @return bool
     */
    public function deleteConfigVars()
    {
        $config_vars = [
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
        ];

        $hashid_vars = array_column(
            Db::getInstance()->executeS('
            SELECT name FROM ' . _DB_PREFIX_ . "configuration where name like 'DF_HASHID_%'"),
            'name'
        );

        $config_vars = array_merge($config_vars, $hashid_vars);

        foreach ($config_vars as $var) {
            Configuration::deleteByName($var);
        }

        return true;
    }

    /**
     * Removes the database tables from the module
     *
     * @return bool
     */
    public function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'doofinder_updates`')
            && Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'doofinder_landing`');
    }

    /**
     * Add controller routes
     *
     * @return array
     */
    public function hookModuleRoutes()
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
     * Handles the module's configuration page
     *
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $stop = $this->getWarningMultishopHtml();
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
     * Build feed urls
     *
     * @param int $shop_id
     * @param int $language
     * @param int $currency
     *
     * @return string
     */
    protected function buildFeedUrl($shop_id, $language, $currency)
    {
        $shop_url = $this->getShopURL($shop_id);

        return $shop_url . ltrim('modules/' . $this->name, DIRECTORY_SEPARATOR)
            . '/feed.php?'
            . 'currency=' . Tools::strtoupper($currency)
            . '&language=' . Tools::strtoupper($language)
            . '&dfsec_hash=' . Configuration::get('DF_API_KEY');
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
        $installation_id = Configuration::get('DF_INSTALLATION_ID', null, (int) $shop->id_shop_group, (int) $shop->id);
        $multishop_enable = Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE');
        $apikey = Configuration::get('DF_AI_APIKEY');

        return !$installation_id && $multishop_enable && $apikey;
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
            ], [
                'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                'label' => $this->l('Doofinder search layer in mobile version'),
                'name' => 'DF_SHOW_LAYER_MOBILE',
                'is_bool' => true,
                'values' => $this->getBooleanFormValue(),
            ], [
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
            $html .= $helper->generateForm([$this->getConfigFormDataFeed()]);
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
    protected function getConfigFormDataFeed()
    {
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
                            'query' => dfTools::getAvailableImageSizes(),
                            'id' => 'DF_GS_IMAGE_SIZE',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Automatically process product changes'),
                        'desc' => $this->l('Configure when registered product changes are sent to Doofinder'),
                        'name' => 'DF_UPDATE_ON_SAVE_DELAY',
                        'options' => [
                            'query' => [
                                0 => ['id' => 0, 'name' => $this->l('Every day')],
                                90 => ['id' => 90, 'name' => sprintf($this->l('Every %s minutes'), '90')],
                                60 => ['id' => 60, 'name' => sprintf($this->l('Every %s minutes'), '60')],
                                30 => ['id' => 30, 'name' => sprintf($this->l('Every %s minutes'), '30')],
                                15 => ['id' => 15, 'name' => sprintf($this->l('Every %s minutes'), '15')],
                            ],
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
                    'url' => $this->buildFeedUrl($this->context->shop->id, $langIso, $currencyIso),
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
            $this->indexApiInvokeReindexing();
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
            $messages .= $this->testDoofinderApi();
            $this->context->smarty->assign('adv', 1);
        }

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
                $this->setSearchEnginesByConfig();
            }
            if (Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') && (int) Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') < 15) {
                Configuration::updateValue('DF_UPDATE_ON_SAVE_DELAY', 15);
            }

            $msg = sprintf('<p>%1$s</p><p><form method="post" action=""><button type="submit" class="btn btn-primary" name="submitDoofinderModuleLaunchReindexing">%2$s</button></form></p>',
                $this->l('You\'ve just changed a data feed option. It may be necessary to reprocess the index to apply these changes effectively.'),
                $this->l('Launch reindexing'));
            $messages .= $this->displayWarningCtm($msg, false, true);
        }

        if (!empty($formUpdated)) {
            $messages .= $this->displayConfirmationCtm($this->l('Settings updated!'));
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
        $lang = Tools::strtoupper($this->context->language->language_code);
        $currency = Tools::strtoupper($this->context->currency->iso_code);
        $search_engine_id = Configuration::get('DF_HASHID_' . $currency . '_' . $lang);
        $df_region = Configuration::get('DF_REGION');
        $script = Configuration::get('DOOFINDER_SCRIPT_' . $lang);
        $extra_css = Configuration::get('DF_EXTRA_CSS');
        $installation_ID = Configuration::get('DF_INSTALLATION_ID');
        if (empty($df_querySelector)) {
            $df_querySelector = '#search_query_top';
        }
        $this->smarty->assign([
            'ENT_QUOTES' => ENT_QUOTES,
            'lang' => Tools::strtolower($lang),
            'script_html' => dfTools::fixScriptTag($script),
            'extra_css_html' => dfTools::fixStyleTag($extra_css),
            'productLinks' => $this->productLinks,
            'search_engine_id' => $search_engine_id,
            'df_region' => $df_region,
            'self' => dirname(__FILE__),
            'df_another_params' => $params,
            'installation_ID' => $installation_ID,
            'currency' => $currency,
        ]);
    }

    /**
     * @hook displayHeader FrontControllerCore
     */
    public function hookHeader($params)
    {
        if ($this->searchLayerMustBeInitialized()) {
            $this->configureHookCommon($params);
            if (Configuration::get('DF_ENABLED_V9')) {
                return $this->displayScriptLiveLayer();
            } else {
                return $this->displayScriptV7();
            }
        }
    }

    /**
     * Render the script for the Livelayer search layer
     *
     * @return string
     */
    public function displayScriptLiveLayer()
    {
        /*
         * loads different cart handling assets depending on the version of prestashop used
         * (uses different javascript implementations for this purpose in prestashop 1.6.x and 1.7.x)
         */
        if (version_compare(_PS_VERSION_, '1.7', '<') === true) {
            $this->context->controller->addJS(
                $this->_path . 'views/js/add-to-cart/doofinder-add_to_cart_ps16.js'
            );
        } else {
            $this->context->controller->addJS(
                $this->_path . 'views/js/add-to-cart/doofinder-add_to_cart_ps17.js'
            );
        }

        return $this->display(__FILE__, 'views/templates/front/scriptV9.tpl');
    }

    /**
     * Render the script for the V7 search layer
     *
     * @return string
     */
    public function displayScriptV7()
    {
        $extraCSS = Configuration::get('DF_EXTRA_CSS');
        $cssVS = (int) Configuration::get('DF_CSS_VS');
        $file = 'doofinder_custom_' . $this->context->shop->id . '_vs_' . $cssVS . '.css';
        if ($extraCSS) {
            if (file_exists(dirname(__FILE__) . '/views/css/' . $file)) {
                $this->context->controller->addCSS(
                    $this->_path . 'views/css/' . $file,
                    'all'
                );
            }
        }

        return $this->display(__FILE__, 'views/templates/front/script.tpl');
    }

    /**
     * @hook actionProductSave ProductCore
     */
    public function hookActionProductSave($params)
    {
        $action = $params['product']->active ? 'update' : 'delete';
        $this->proccessHookUpdateOnSave('product', $params['id_product'], $action);
    }

    /**
     * @hook actionProductDelete ProductCore
     */
    public function hookActionProductDelete($params)
    {
        $this->proccessHookUpdateOnSave('product', $params['id_product'], 'delete');
    }

    /**
     * @hook actionObjectCmsAddAfter ObjectModelCore
     */
    public function hookActionObjectCmsAddAfter($params)
    {
        if ($params['object']->active) {
            $this->proccessHookUpdateOnSave('cms', $params['object']->id, 'update');
        }
    }

    /**
     * @hook actionObjectCmsUpdateAfter ObjectModelCore
     */
    public function hookActionObjectCmsUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        $this->proccessHookUpdateOnSave('cms', $params['object']->id, $action);
    }

    /**
     * @hook actionObjectCmsDeleteAfter ObjectModelCore
     */
    public function hookActionObjectCmsDeleteAfter($params)
    {
        $this->proccessHookUpdateOnSave('cms', $params['object']->id, 'delete');
    }

    /**
     * @hook actionObjectCategoryAddAfter ObjectModelCore
     */
    public function hookActionObjectCategoryAddAfter($params)
    {
        if ($params['object']->active) {
            $this->proccessHookUpdateOnSave('category', $params['object']->id, 'update');
        }
    }

    /**
     * @hook actionObjectCategoryUpdateAfter ObjectModelCore
     */
    public function hookActionObjectCategoryUpdateAfter($params)
    {
        $action = $params['object']->active ? 'update' : 'delete';
        $this->proccessHookUpdateOnSave('category', $params['object']->id, $action);
    }

    /**
     * @hook actionObjectCategoryDeleteAfter ObjectModelCore
     */
    public function hookActionObjectCategoryDeleteAfter($params)
    {
        $this->proccessHookUpdateOnSave('cms', $params['object']->id, 'delete');
    }

    /**
     * Queue the item for update on save
     *
     * @param string $object
     * @param int $id_object
     * @param string $action
     *
     * @return void
     */
    public function proccessHookUpdateOnSave($object, $id_object, $action)
    {
        if (Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
            $id_shop = $this->context->shop->id;

            $this->addItemQueue($object, $id_object, $id_shop, $action);

            if ($this->allowProcessItemsQueue()) {
                $this->processItemQueue($id_shop);
            }
        }
    }

    /**
     * Check if the necessary time has passed to run the update on save again
     *
     * @return bool
     */
    public function allowProcessItemsQueue()
    {
        if (Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
            $last_exec = Configuration::get('DF_UPDATE_ON_SAVE_LAST_EXEC', null, null, null, 0);
            $delay = (int) Configuration::get('DF_UPDATE_ON_SAVE_DELAY', null, null, null, 30);

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
     * Updates the execution date of the update on save
     *
     * @return void
     */
    public function setExecUpdateOnSave()
    {
        Configuration::updateValue('DF_UPDATE_ON_SAVE_LAST_EXEC', date('Y-m-d H:i:s'));
    }

    /**
     * Add a item to the update on save queue
     *
     * @param string $object
     * @param int $id_object
     * @param int $id_shop
     * @param string $action
     *
     * @return void
     */
    public function addItemQueue($object, $id_object, $id_shop, $action)
    {
        Db::getInstance()->insert(
            'doofinder_updates',
            [
                'id_shop' => $id_shop,
                'object' => $object,
                'id_object' => $id_object,
                'action' => $action,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            false,
            true,
            Db::REPLACE
        );
    }

    /**
     * Process queued items from update on save to send to API
     *
     * @param int $id_shop
     *
     * @return void
     */
    public function processItemQueue($id_shop)
    {
        $this->setExecUpdateOnSave();

        $languages = Language::getLanguages(true, $id_shop);
        $currencies = Currency::getCurrenciesByIdShop($id_shop);

        foreach (['product', 'cms', 'category'] as $type) {
            $items_update = $this->getItemsQueue($id_shop, $type, 'update');
            $items_delete = $this->getItemsQueue($id_shop, $type, 'delete');

            foreach ($languages as $language) {
                foreach ($currencies as $currency) {
                    $this->{'send' . $type . 'Api'}($items_update, $id_shop, $language['id_lang'], $currency['id_currency']);
                    $this->{'send' . $type . 'Api'}($items_delete, $id_shop, $language['id_lang'], $currency['id_currency'], 'delete');
                }
            }
        }

        $this->deleteItemsQueue($id_shop);
    }

    /**
     * Get queued items from update on save
     *
     * @param int $id_shop
     * @param string $action
     *
     * @return array
     */
    public function getItemsQueue($id_shop, $type, $action = 'update')
    {
        $items = Db::getInstance()->executeS(
            '
            SELECT id_object FROM ' . _DB_PREFIX_ . "doofinder_updates
            WHERE object = '" . pSQL($type) . "' AND action = '" . pSQL($action) . "' AND id_shop = " . (int) $id_shop
        );

        return array_column($items, 'id_object');
    }

    /**
     * Remove queued items from update on save
     *
     * @param int $id_shop
     *
     * @return void
     */
    public function deleteItemsQueue($id_shop)
    {
        Db::getInstance()->execute('DELETE from ' . _DB_PREFIX_ . 'doofinder_updates WHERE id_shop = ' . (int) $id_shop);
    }

    /**
     * Update products in doofinder using the API
     *
     * @param array $products
     * @param int $id_shop
     * @param int $id_lang
     * @param int $id_currency
     * @param string $action
     *
     * @return void
     */
    public function sendProductApi($products, $id_shop, $id_lang, $id_currency, $action = 'update')
    {
        if (empty($products)) {
            return;
        }

        $hashid = $this->getHashId($id_lang, $id_currency);

        if ($hashid) {
            if ($action == 'update') {
                require_once dirname(__FILE__) . '/lib/dfProduct_build.php';
                $builder = new DfProductBuild($id_shop, $id_lang, $id_currency);
                $builder->setProducts($products);
                $payload = $builder->build();

                $this->updateItemsApi($hashid, 'product', $payload);
            } elseif ($action == 'delete') {
                $this->deleteItemsApi($hashid, 'product', $products);
            }
        }
    }

    /**
     * Update cms pages in doofinder using the API
     *
     * @param array $cms_pages
     * @param int $id_shop
     * @param int $id_lang
     * @param int $id_currency
     * @param string $action
     *
     * @return void
     */
    public function sendCmsApi($cms_pages, $id_shop, $id_lang, $id_currency, $action = 'update')
    {
        if (empty($cms_pages)) {
            return;
        }

        $hashid = $this->getHashId($id_lang, $id_currency);

        if ($hashid) {
            if ($action == 'update') {
                require_once dirname(__FILE__) . '/lib/dfCms_build.php';

                $builder = new DfCmsBuild($id_shop, $id_lang);
                $builder->setCmsPages($cms_pages);
                $payload = $builder->build();

                $this->updateItemsApi($hashid, 'page', $payload);
            } elseif ($action == 'delete') {
                $this->deleteItemsApi($hashid, 'page', $cms_pages);
            }
        }
    }

    /**
     * Update categores in doofinder using the API
     *
     * @param array $categories
     * @param int $id_shop
     * @param int $id_lang
     * @param int $id_currency
     * @param string $action
     *
     * @return void
     */
    public function sendCategoryApi($categories, $id_shop, $id_lang, $id_currency, $action = 'update')
    {
        if (empty($categories)) {
            return;
        }

        $hashid = $this->getHashId($id_lang, $id_currency);

        if ($hashid) {
            if ($action == 'update') {
                require_once dirname(__FILE__) . '/lib/dfCategory_build.php';

                $builder = new DfCategoryBuild($id_shop, $id_lang);
                $builder->setCategories($categories);
                $payload = $builder->build();

                $this->updateItemsApi($hashid, 'category', $payload);
            } elseif ($action == 'delete') {
                $this->deleteItemsApi($hashid, 'category', $categories);
            }
        }
    }

    private function updateItemsApi($hashid, $type, $payload)
    {
        if (empty($payload)) {
            return;
        }

        require_once dirname(__FILE__) . '/lib/doofinder_api_items.php';

        $apikey = explode('-', Configuration::get('DF_API_KEY'))[1];
        $region = Configuration::get('DF_REGION');

        $api = new DoofinderApiItems($hashid, $apikey, $region, $type);
        $response = $api->updateBulk($payload);

        if (isset($response['error']) && !empty($response['error'])) {
            $this->debug(json_encode($response['error']));
        }
    }

    private function deleteItemsApi($hashid, $type, $payload)
    {
        if (empty($payload)) {
            return;
        }

        require_once dirname(__FILE__) . '/lib/doofinder_api_items.php';

        $apikey = explode('-', Configuration::get('DF_API_KEY'))[1];
        $region = Configuration::get('DF_REGION');

        $api = new DoofinderApiItems($hashid, $apikey, $region, $type);
        $response = $api->deleteBulk(json_encode($payload));

        if (isset($response['error']) && !empty($response['error'])) {
            $this->debug(json_encode($response['error']));
        }
    }

    private function indexApiInvokeReindexing()
    {
        require_once dirname(__FILE__) . '/lib/doofinder_api_index.php';

        $region = Configuration::get('DF_REGION');
        $api_key = Configuration::get('DF_API_KEY');
        $api = new DoofinderApiIndex($api_key, $region);
        $response = $api->invokeReindexing(Configuration::get('DF_INSTALLATION_ID'), $this->getProcessCallbackUrl());
        if (empty($response)) {
            $this->debug('Empty response from invoke reindexing');

            return;
        }
        if (!empty($response['errors'])) {
            $this->debug(json_encode($response['errors']));

            return;
        }

        Configuration::updateValue('DF_FEED_INDEXED', false);
    }

    /**
     * Perform an API connection test
     *
     * @param bool $onlyOneLang
     *
     * @return bool|string
     */
    public function testDoofinderApi($onlyOneLang = false)
    {
        if (!class_exists('DoofinderApi')) {
            include_once dirname(__FILE__) . '/lib/doofinder_api.php';
        }
        $result = false;
        $messages = '';
        $currency = Tools::strtoupper(Context::getContext()->currency->iso_code);
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            if (!$onlyOneLang || ($onlyOneLang && $lang['iso_code'])) {
                $lang_iso = Tools::strtoupper($lang['iso_code']);
                $hash_id = Configuration::get('DF_HASHID_' . $currency . '_' . $lang_iso);
                $api_key = Configuration::get('DF_API_KEY');
                if ($hash_id && $api_key) {
                    try {
                        $df = new DoofinderApi($hash_id, $api_key, false, ['apiVersion' => '5']);
                        $dfOptions = $df->getOptions();
                        if ($dfOptions) {
                            $opt = json_decode($dfOptions, true);
                            if ($opt['query_limit_reached']) {
                                $msg = 'Error: Credentials OK but limit query reached for Search Engine - ';
                                $messages .= $this->displayErrorCtm($this->l($msg) . $lang_iso);
                            } else {
                                $result = true;
                                $msg = 'Connection succesful for Search Engine - ';
                                $messages .= $this->displayConfirmationCtm($this->l($msg) . $lang_iso);
                            }
                        } else {
                            $msg = 'Error: no connection for Search Engine - ';
                            $messages .= $this->displayErrorCtm($this->l($msg) . $lang_iso);
                        }
                    } catch (DoofinderException $e) {
                        $messages .= $this->displayErrorCtm($e->getMessage() . ' - Search Engine ' . $lang_iso);
                    } catch (Exception $e) {
                        $msg = $e->getMessage() . ' - Search Engine ';
                        $messages .= $this->displayErrorCtm($msg . $lang_iso);
                    }
                } else {
                    $msg = 'Empty Api Key or empty Search Engine - ';
                    $messages .= $this->displayWarningCtm($this->l($msg) . $lang_iso);
                }
            }
        }
        if ($onlyOneLang) {
            return $result;
        } else {
            return $messages;
        }
    }

    /**
     * Search Doofinder using the API
     *
     * @param string $string
     * @param int $page
     * @param int $page_size
     * @param int $timeout
     * @param array $filters
     * @param bool $return_facets
     *
     * @return array
     */
    public function searchOnApi(
        $string,
        $page = 1,
        $page_size = 12,
        $timeout = 8000,
        $filters = null,
        $return_facets = false
    ) {
        $page_size = (int) $page_size;
        if (!$page_size) {
            $page_size = Configuration::get('PS_PRODUCTS_PER_PAGE');
        }
        $page = (int) $page;
        if (!$page) {
            $page = 1;
        }
        $query_name = Tools::getValue('df_query_name', false);
        $debug = Configuration::get('DF_DEBUG');
        if (isset($debug) && $debug) {
            $this->debug('Search On API Start');
        }
        $hash_id = $this->getHashId(Context::getContext()->language->id, Context::getContext()->currency->id);
        $api_key = Configuration::get('DF_API_KEY');
        $show_variations = Configuration::get('DF_SHOW_PRODUCT_VARIATIONS');
        if ((int) $show_variations !== 1) {
            $show_variations = false;
        }

        if ($hash_id && $api_key) {
            $fail = false;
            try {
                if (!class_exists('DoofinderApi')) {
                    include_once dirname(__FILE__) . '/lib/doofinder_api.php';
                }
                $df = new DoofinderApi($hash_id, $api_key, false, ['apiVersion' => '5']);
                $queryParams = [
                    'rpp' => $page_size, // results per page
                    'timeout' => $timeout,
                    'types' => [
                        'product',
                    ], 'transformer' => 'basic',
                ];
                if ($query_name) {
                    $queryParams['query_name'] = $query_name;
                }
                if (!empty($filters)) {
                    $queryParams['filter'] = $filters;
                }
                $dfResults = $df->query($string, $page, $queryParams);
            } catch (Exception $e) {
                $fail = true;
            }

            if ($fail || !$dfResults->isOk()) {
                return false;
            }

            $dfResultsArray = $dfResults->getResults();
            $product_pool_attributes = [];
            $product_pool_ids = [];
            foreach ($dfResultsArray as $entry) {
                if ($entry['type'] == 'product') {
                    if (strpos($entry['id'], 'VAR-') === false) {
                        $product_pool_ids[] = (int) pSQL($entry['id']);
                    } else {
                        $id_product_attribute = str_replace('VAR-', '', $entry['id']);
                        if (!in_array($id_product_attribute, $product_pool_attributes)) {
                            $product_pool_attributes[] = (int) pSQL($id_product_attribute);
                        }
                        $id_product = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                            'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute'
                                . ' WHERE id_product_attribute = ' . (int) pSQL($id_product_attribute)
                        );
                        $product_pool_ids[] = ((!empty($id_product)) ? (int) pSQL($id_product) : 0);
                    }
                }
            }
            $product_pool = implode(', ', $product_pool_ids);

            // To avoid SQL errors.
            if ($product_pool == '') {
                $product_pool = '0';
            }

            if (isset($debug) && $debug) {
                $this->debug("Product Pool: $product_pool");
            }

            $product_pool_attributes = implode(',', $product_pool_attributes);

            $context = Context::getContext();
            // Avoids SQL Error
            if ($product_pool_attributes == '') {
                $product_pool_attributes = '0';
            }

            if (isset($debug) && $debug) {
                $this->debug("Product Pool Attributes: $product_pool_attributes");
            }
            $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
            $id_lang = $context->language->id;
            $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock,
                IFNULL(stock.quantity, 0) as quantity,
                pl.`description_short`, pl.`available_now`,
                pl.`available_later`, pl.`link_rewrite`, pl.`name`,
                ' . (Combination::isFeatureActive() && $show_variations ?
                ' IF(ipa.`id_image` IS NULL OR ipa.`id_image` = 0, MAX(image_shop.`id_image`),ipa.`id_image`)'
                . ' id_image, ' : 'i.id_image, ') . '
                il.`legend`, m.`name` manufacturer_name '
                . (Combination::isFeatureActive() ? (($show_variations) ?
                    ', MAX(product_attribute_shop.`id_product_attribute`) id_product_attribute' :
                    ', product_attribute_shop.`id_product_attribute` id_product_attribute') : '') . ',
                DATEDIFF(
                    p.`date_add`,
                    DATE_SUB(
                        NOW(),
                        INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT'))
                    ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                    )
                ) > 0 new' . (Combination::isFeatureActive() ?
                    ', MAX(product_attribute_shop.minimal_quantity) AS product_attribute_minimal_quantity' : '') . '
                FROM ' . _DB_PREFIX_ . 'product p
                ' . Shop::addSqlAssociation('product', 'p') . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                    p.`id_product` = pl.`id_product`
                    AND pl.`id_lang` = ' . (int) pSQL($id_lang) . Shop::addSqlRestrictionOnLang('pl') . ') '
                . (Combination::isFeatureActive() ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                    ON (p.`id_product` = pa.`id_product`)
                    ' . Shop::addSqlAssociation('product_attribute', 'pa', false, ($show_variations) ? '' :
                    ' product_attribute_shop.default_on = 1') . '
                    ' . Product::sqlStock('p', 'product_attribute_shop', false, $context->shop) :
                    Product::sqlStock('p', 'product', false, Context::getContext()->shop)) . '
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
                LEFT JOIN `' . _DB_PREFIX_ . 'image` i ON (i.`id_product` = p.`id_product` '
                . ((Combination::isFeatureActive() && $show_variations) ? '' : 'AND i.cover=1') . ') '
                . ((Combination::isFeatureActive() && $show_variations) ?
                    ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_image` pai'
                    . ' ON (pai.`id_product_attribute` = product_attribute_shop.`id_product_attribute`) ' : ' ')
                . Shop::addSqlAssociation('image', 'i', false, 'i.cover=1') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il'
                . ' ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) pSQL($id_lang) . ') '
                . (Combination::isFeatureActive() && $show_variations ?
                    'LEFT JOIN (
                        SELECT i.id_image, P.id_product, P.id_product_attribute
                            from
                            (
                            select
                                pa.id_product,
                                pa.id_product_attribute,
                                paic.id_attribute,min(i.position)
                                as min_position
                            from ' . _DB_PREFIX_ . 'product_attribute pa
                             inner join ' . _DB_PREFIX_ . 'product_attribute_image pai
                               on pai.id_product_attribute = pa.id_product_attribute
                             inner join  ' . _DB_PREFIX_ . 'product_attribute_combination paic
                               on pai.id_product_attribute = paic.id_product_attribute
                             inner join ' . _DB_PREFIX_ . 'image i
                               on pai.id_image = i.id_image
                            group by pa.id_product, pa.id_product_attribute,paic.id_attribute
                            ) as P
                            inner join ' . _DB_PREFIX_ . 'image i
                             on i.id_product = P.id_product and i.position =  P.min_position
                    )
                    AS ipa ON p.`id_product` = ipa.`id_product`
                    AND pai.`id_product_attribute` = ipa.`id_product_attribute`' : '')
                . ' WHERE p.`id_product` IN (' . pSQL($product_pool) . ') ' .
                (($show_variations) ? ' AND (product_attribute_shop.`id_product_attribute` IS NULL'
                    . ' OR product_attribute_shop.`id_product_attribute`'
                    . ' IN (' . pSQL($product_pool_attributes) . ')) ' : '') .
                ' GROUP BY product_shop.id_product '
                . (($show_variations) ? ' ,  product_attribute_shop.`id_product_attribute` ' : '') .
                ' ORDER BY FIELD (p.`id_product`,' . pSQL($product_pool) . ') '
                . (($show_variations) ? ' , FIELD (product_attribute_shop.`id_product_attribute`,'
                    . pSQL($product_pool_attributes) . ')' : '');
            if (isset($debug) && $debug) {
                $this->debug("SQL: $sql");
            }

            $result = $db->executeS($sql);

            if (!$result) {
                return false;
            } else {
                if (version_compare(_PS_VERSION_, '1.7', '<') === true) {
                    $result_properties = Product::getProductsProperties((int) $id_lang, $result);
                    // To print the id and links in the javascript so I can register the clicks
                    $this->productLinks = [];

                    foreach ($result_properties as $rp) {
                        $this->productLinks[$rp['link']] = $rp['id_product'];
                    }
                } else {
                    $result_properties = $result;
                }
            }
            $this->searchBanner = $dfResults->getBanner();

            if ($return_facets) {
                return [
                    'doofinder_results' => $dfResultsArray,
                    'total' => $dfResults->getProperty('total'),
                    'result' => $result_properties,
                    'facets' => $dfResults->getFacets(),
                    'filters' => $df->getFilters(),
                    'df_query_name' => $dfResults->getProperty('query_name'),
                ];
            }

            return [
                'doofinder_results' => $dfResultsArray,
                'total' => $dfResults->getProperty('total'),
                'result' => $result_properties,
                'df_query_name' => $dfResults->getProperty('query_name'),
            ];
        } else {
            return false;
        }
    }

    /**
     * Update the hashid of the search engines of the store in the configuration
     *
     * @return true
     */
    public function setSearchEnginesByConfig()
    {
        require_once _PS_MODULE_DIR_ . 'doofinder/lib/doofinder_layer_api.php';
        $installationID = Configuration::get('DF_INSTALLATION_ID');
        $api_key = Configuration::get('DF_API_KEY');
        $region = Configuration::get('DF_REGION');

        $data = DoofinderLayerApi::getInstallationData($installationID, $api_key, $region);

        foreach ($data['config']['search_engines'] as $lang => $currencies) {
            foreach ($currencies as $currency => $hashid) {
                Configuration::updateValue('DF_HASHID_' . strtoupper($currency) . '_' . strtoupper($lang), $hashid);
            }
        }

        return true;
    }

    /**
     * Checks the connection with DooManager
     *
     * @return bool
     */
    public function checkOutsideConnection()
    {
        // Require only on this function to not overload memory with not needed classes
        require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';
        $client = new EasyREST(true, 3);
        $result = $client->get(sprintf('%s/auth/login', self::DOOMANAGER_URL));

        return $result && $result->originalResponse && isset($result->headers['code'])
            && (strpos($result->originalResponse, 'HTTP/2 200') || $result->headers['code'] == 200);
    }

    /**
     * Save the information that Doofinder returns after login
     *
     * @param string $apikey
     * @param string $api_endpoint
     * @param string $admin_endpoint
     *
     * @return void
     */
    public function saveApiData($apikey, $api_endpoint, $admin_endpoint)
    {
        Configuration::updateGlobalValue('DF_AI_APIKEY', $apikey);
        Configuration::updateGlobalValue('DF_AI_ADMIN_ENDPOINT', $admin_endpoint);
        Configuration::updateGlobalValue('DF_AI_API_ENDPOINT', $api_endpoint);

        $api_endpoint_array = explode('-', $api_endpoint);
        $region = $api_endpoint_array[0];
        $shops = Shop::getShops();
        foreach ($shops as $shop) {
            $sid = $shop['id_shop'];
            $sgid = $shop['id_shop_group'];

            Configuration::updateValue('DF_API_KEY', $region . '-' . $apikey, false, $sgid, $sid);
        }
    }

    /**
     * Start the installation process
     * If shop_id is null, install all shops
     *
     * @param int $shop_id
     *
     * @return void
     */
    public function autoinstaller($shop_id = null)
    {
        if (!empty($shop_id)) {
            $shop = Shop::getShop($shop_id);
            $this->createStore($shop);

            return;
        }

        $shops = Shop::getShops();
        foreach ($shops as $shop) {
            $this->createStore($shop);
        }
    }

    /**
     * Create a store in Doofinder based on the Prestashop shop
     *
     * @param array $shop
     *
     * @return void
     */
    public function createStore($shop)
    {
        // Require only on this function to not overload memory with unneeded classes
        require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';
        $client = new EasyREST();
        $apikey = Configuration::getGlobalValue('DF_AI_APIKEY');
        $admin_endpoint = Configuration::getGlobalValue('DF_AI_ADMIN_ENDPOINT');
        $languages = Language::getLanguages(true, $shop['id_shop']);
        $currencies = Currency::getCurrenciesByIdShop($shop['id_shop']);
        $shopId = $shop['id_shop'];
        $shopGroupId = $shop['id_shop_group'];
        $primary_lang = new Language(Configuration::get('PS_LANG_DEFAULT', null, $shopGroupId, $shopId));
        $installationID = null;
        $callbacksUrls = [];

        $this->setDefaultShopConfig($shopGroupId, $shopId);

        $shop_url = $this->getShopURL($shopId);
        $store_data = [
            'name' => $shop['name'],
            'platform' => 'prestashop',
            'primary_language' => $primary_lang->language_code,
            'search_engines' => [],
            'sector' => '',
        ];

        foreach ($languages as $lang) {
            foreach ($currencies as $cur) {
                if ($cur['deleted'] == 1) {
                    continue;
                }
                $ciso = $cur['iso_code'];
                $lang_code = $lang['language_code'];
                $feed_url = $this->buildFeedUrl($shopId, $lang['iso_code'], $ciso);
                $store_data['search_engines'][] = [
                    'name' => $shop['name'] . ' | Lang:' . $lang['iso_code'] . ' Currency:' . strtoupper($ciso),
                    'language' => $lang_code,
                    'currency' => $ciso,
                    'site_url' => $shop_url,
                    'stopwords' => false,
                    'datatypes' => [
                        [
                            'name' => 'product',
                            'preset' => 'product',
                            'datasources' => [
                                [
                                    'options' => [
                                        'url' => $feed_url,
                                    ],
                                    'type' => 'file',
                                ],
                            ],
                            'options' => [
                                'exclude_out_of_stock_items' => false,
                                'group_variants' => false,
                            ],
                        ],
                    ],
                ];
                $callbacksUrls[$lang_code][$ciso] = $this->getProcessCallbackUrl();
            }
        }
        $store_data['callback_urls'] = $callbacksUrls;

        $json_store_data = json_encode($store_data);
        $this->debug('Create Store Start');
        $this->debug(print_r($store_data, true));

        $response = $client->post(
            'https://' . $admin_endpoint . '/plugins/create-store',
            $json_store_data,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $apikey]
        );

        if ($response->getResponseCode() == 200) {
            $response = json_decode($response->response, true);
            $installationID = @$response['installation_id'];
            $this->debug('Create Store response:');
            $this->debug(print_r($response, true));

            if ($installationID) {
                $this->debug("Set installation ID: $installationID");
                Configuration::updateValue('DF_INSTALLATION_ID', $installationID, false, $shopGroupId, $shopId);
                Configuration::updateValue('DF_ENABLED_V9', true, false, $shopGroupId, $shopId);
                $this->setSearchEnginesByConfig();
            } else {
                $this->debug('Invalid installation ID');
                exit('ko');
            }
        } else {
            $error_msg = "Create Store failed with code {$response->getResponseCode()} and message '{$response->getResponseMessage()}'";
            $response_msg = 'Response: ' . print_r($response->response, true);
            $this->debug($error_msg);
            $this->debug($response_msg);
            echo $response->response;
            exit;
        }
    }

    /**
     * Get Process Callback URL
     *
     * @return string
     */
    private function getProcessCallbackUrl()
    {
        return Context::getContext()->link->getModuleLink('doofinder', 'callback', []);
    }

    /**
     * Set the default values in the configuration
     *
     * @param int $shopGroupId
     * @param int $shopId
     *
     * @return void
     */
    public function setDefaultShopConfig($shopGroupId, $shopId)
    {
        $apikey = Configuration::getGlobalValue('DF_AI_APIKEY');
        $api_endpoint = Configuration::getGlobalValue('DF_AI_API_ENDPOINT');
        $api_endpoint_array = explode('-', $api_endpoint);
        $region = $api_endpoint_array[0];

        Configuration::updateValue('DF_ENABLE_HASH', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_DISPLAY_PRICES', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_PRICES_USE_TAX', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_FEED_FULL_PATH', true, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_SHOW_PRODUCT_VARIATIONS', 0, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_REGION', $region, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_API_KEY', $region . '-' . $apikey, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_DESCRIPTION_TYPE', self::GS_SHORT_DESCRIPTION, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_FEED_MAINCATEGORY_PATH', false, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_IMAGE_SIZE', key(dfTools::getAvailableImageSizes()), false, $shopGroupId, $shopId);
    }

    /**
     * Get store URL
     *
     * @param int $shop_id
     *
     * @return string
     */
    public function getShopURL($shop_id)
    {
        $shop = new Shop($shop_id);
        $force_ssl = (Configuration::get('PS_SSL_ENABLED')
            && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
        $url = ($force_ssl) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain;

        return $url . $this->getShopBaseURI($shop);
    }

    /**
     * Check the connection to the API using the saved API KEY
     *
     * @param bool $text If the response is received as a string
     *
     * @return bool|string
     */
    public function checkApiKey($text = false)
    {
        $result = Db::getInstance()->getValue('SELECT id_configuration FROM ' . _DB_PREFIX_
            . 'configuration WHERE name = "DF_API_KEY" AND (value IS NOT NULL OR value <> "")');
        $return = (($result) ? 'OK' : 'KO');

        return ($text) ? $return : $result;
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
            $iso_code = explode('_', $key)[1];

            return (int) $this->getIdByLocale($iso_code);
        } else {
            return false;
        }
    }

    /**
     * Returns language id from locale
     *
     * @param string $locale Locale IETF language tag
     * @param bool $noCache
     *
     * @return int|false|null
     */
    public function getIdByLocale($locale, $noCache = false)
    {
        $key = 'Language::getIdByLocale_' . $locale;
        if ($noCache || !Cache::isStored($key)) {
            $idLang = Db::getInstance()
                ->getValue(
                    'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang`
                    WHERE `locale` = \'' . pSQL(strtolower($locale)) . '\'
                    OR `language_code` = \'' . pSQL(strtolower($locale)) . '\''
                );

            Cache::store($key, $idLang);

            return $idLang;
        }

        return Cache::retrieve($key);
    }

    /**
     * Get the ISO of a currency
     *
     * @param int $id currency ID
     *
     * @return string
     */
    protected function getIsoCodeById($id)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
            SELECT `iso_code` FROM ' . _DB_PREFIX_ . 'currency WHERE `id_currency` = ' . (int) $id
        );
    }

    /**
     * Gets the ISO code of a language code
     *
     * @param string $code 3-letter Month abbreviation
     *
     * @return string
     */
    protected function getLanguageCode($code)
    {
        // $code is in the form of 'xx-YY' where xx is the language code
        // and 'YY' a country code identifying a variant of the language.
        $lang_country = explode('-', $code);

        return $lang_country[0];
    }

    /**
     * Get the configuration key for the language and currency corresponding to the hashid
     *
     * @param int $id_lang
     * @param int $id_currency
     *
     * @return string
     */
    public function getHashId($id_lang, $id_currency)
    {
        $curr_iso = strtoupper($this->getIsoCodeById($id_currency));
        $lang = new Language($id_lang);

        $hashid_key = 'DF_HASHID_' . $curr_iso . '_' . strtoupper($lang->language_code);
        $hashid = Configuration::get($hashid_key);

        if (!$hashid) {
            $hashid_key = 'DF_HASHID_' . $curr_iso . '_' . strtoupper($this->getLanguageCode($lang->language_code));
            $hashid = Configuration::get($hashid_key);
        }

        return $hashid;
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

    private function getWarningMultishopHtml()
    {
        $stop = false;
        if (Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_ALL) {
            $stopMsg = 'You cannot manage Doofinder from a "All Shops"'
                . ' or a "Group Shop" context, select directly the shop you want to edit';
            $stop = '<p class="alert alert-warning">' .
                $this->l($stopMsg) .
                '</p>';
        }

        return $stop;
    }

    private function getShopBaseURI($shop)
    {
        return $shop->physical_uri . $shop->virtual_uri;
    }

    private function debug($message)
    {
        $debug = Configuration::get('DF_DEBUG', null);
        if (isset($debug) && $debug) {
            error_log("$message\n", 3, dirname(__FILE__) . '/doofinder.log');
        }
    }

    private function displayErrorCtm($error, $link = false, $raw = false)
    {
        return $this->displayGeneralMsg($error, 'error', 'danger', $link, $raw);
    }

    private function displayWarningCtm($warning, $link = false, $raw = false)
    {
        return $this->displayGeneralMsg($warning, 'warning', 'warning', $link, $raw);
    }

    private function displayConfirmationCtm($string, $link = false, $raw = false)
    {
        return $this->displayGeneralMsg($string, 'confirmation', 'success', $link, $raw);
    }

    private function displayGeneralMsg($string, $type, $alert, $link = false, $raw = false)
    {
        $this->context->smarty->assign(
            [
                'd_type_message' => $type,
                'd_type_alert' => $alert,
                'd_message' => $string,
                'd_link' => $link,
                'd_raw' => $raw,
            ]
        );

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/display_msg.tpl');
    }

    private function searchLayerMustBeInitialized()
    {
        $displayMobile = Configuration::get('DF_SHOW_LAYER_MOBILE', null, null, null, true);
        $displayDesktop = Configuration::get('DF_SHOW_LAYER', null, null, null, true);
        $isMobile = Context::getContext()->isMobile();

        return ($isMobile && $displayMobile) || (!$isMobile && $displayDesktop);
    }

    private function installTabs()
    {
        $tab = new Tab();
        $tab->active = 0;
        $tab->class_name = 'DoofinderAdmin';
        $tab->name = [];
        foreach (Language::getLanguages() as $lang) {
            $tab->name[$lang['id_lang']] = 'Doofinder admin controller';
        }
        $tab->id_parent = 0;
        $tab->module = $this->name;

        return $tab->save();
    }

    private function uninstallTabs()
    {
        $tabId = (int) Tab::getIdFromClassName('DoofinderAdmin');
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);

        return $tab->delete();
    }
}
