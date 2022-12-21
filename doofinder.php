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

if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
    require_once implode(DIRECTORY_SEPARATOR, [
        dirname(__FILE__), 'src', 'DoofinderProductSearchProvider.php',
    ]);

    require_once implode(DIRECTORY_SEPARATOR, [
        dirname(__FILE__), 'src', 'DoofinderRangeAggregator.php',
    ]);
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

    const GS_SHORT_DESCRIPTION = 1;
    const GS_LONG_DESCRIPTION = 2;
    const VERSION = '4.3.13';
    const YES = 1;
    const NO = 0;

    public function __construct()
    {
        $this->name = 'doofinder';
        $this->tab = 'search_filter';
        $this->version = '4.3.13';
        $this->author = 'Doofinder (http://www.doofinder.com)';
        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => '1.7'];
        $this->module_key = 'd1504fe6432199c7f56829be4bd16347';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Doofinder');
        $this->description = $this->l('Install Doofinder in your shop with no effort');

        $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service');
        $this->admin_template_dir = '../../../../modules/' . $this->name . '/views/templates/admin/';

        $olderPS17 = (version_compare(_PS_VERSION_, '1.7', '<') === true);
        $this->ovFile = '/override/controllers/front/' . (!$olderPS17 ? 'listing/' : '') . 'SearchController.php';
    }

    public function manualOverride($restart = false)
    {
        if ($restart) {
            if (file_exists(dirname(__FILE__) . $this->ovFile)) {
                unlink(dirname(__FILE__) . $this->ovFile);
            }
        }
        $msg = $this->displayConfirmationCtm($this->l('Override installed sucessfully!'));
        $originFile = dirname(__FILE__) . '/lib/SearchController.php';
        $destFile = dirname(__FILE__) . $this->ovFile;

        $olderPS17 = (version_compare(_PS_VERSION_, '1.7', '<') === true);
        if (
            !$olderPS17
            && !file_exists(_PS_ROOT_DIR_ . '/override/controllers/front/listing')
        ) {
            mkdir(_PS_ROOT_DIR_ . '/override/controllers/front/listing', 0755, true);
        }

        if (file_exists($originFile)) {
            if (!file_exists($destFile)) {
                copy($originFile, $destFile);
                // Install overrides
                try {
                    $this->uninstallOverrides();
                    $this->installOverrides();
                } catch (Exception $e) {
                    $msg = sprintf($this->displayErrorCtm('Unable to install override: %s'), $e->getMessage());
                }
            } else {
                $msg = $this->displayWarningCtm($this->l('We think that you must already yet search overrided.'));
            }
        }

        return $msg;
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionProductDelete');
    }

    public function installDb()
    {
        return Db::getInstance()->execute(
            '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doofinder_product` (
                `id_doofinder_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL,
                `id_product` INT(10) UNSIGNED NOT NULL,
                `action` VARCHAR(45) NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_doofinder_product`),
                CONSTRAINT uc_shop_product UNIQUE KEY (id_shop,id_product)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
        );
    }

    public function manualInstallation()
    {
        if (file_exists(dirname(__FILE__) . $this->ovFile)) {
            unlink(dirname(__FILE__) . $this->ovFile);
        }

        $msgErrorColumn = 'This module need to be hooked in a column and your '
            . 'theme does not implement one if you want Search Facets via API';
        if (
            version_compare(_PS_VERSION_, '1.6.0', '>=') === true &&
            version_compare(_PS_VERSION_, '1.7', '<') === true
        ) {
            // Hook the module either on the left or right column
            $theme = new Theme(Context::getContext()->shop->id_theme);
            if ((!$theme->default_left_column || !$this->registerHook('displayLeftColumn')) &&
                (!$theme->default_right_column || !$this->registerHook('displayRightColumn'))
            ) {
                $this->_errors[] = $this->l($msgErrorColumn);
            }
        } elseif (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
            if ((!$this->registerHook('displayLeftColumn')) && (!$this->registerHook('displayRightColumn'))) {
                $this->_errors[] = $this->l($msgErrorColumn);
            }
        } else {
            $this->registerHook('displayLeftColumn');
        }

        if (
            !$this->registerHook('header') ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('displayFooter') ||
            !$this->registerHook('displayMobileTopSiteMap')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->deleteConfigVars()
            && $this->uninstallDb();
    }

    public function deleteConfigVars()
    {
        $config_vars = [
            'DF_AI_ADMIN_ENDPOINT',
            'DF_AI_API_ENDPOINT',
            'DF_AI_APIKEY',
            'DF_API_KEY',
            'DF_API_LAYER_DESCRIPTION',
            'DF_APPEND_BANNER',
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
            'DF_FEED_MAINCATEGORY_PATH',
            'DF_GROUP_ATTRIBUTES_SHOWN',
            'DF_GS_DESCRIPTION_TYPE',
            'DF_GS_DISPLAY_PRICES',
            'DF_GS_IMAGE_SIZE',
            'DF_GS_MPN_FIELD',
            'DF_GS_PRICES_USE_TAX',
            'DF_INSTALLATION_ID',
            'DF_OWSEARCH',
            'DF_OWSEARCHEB',
            'DF_OWSEARCHFAC',
            'DF_REGION',
            'DF_RESTART_OV',
            'DF_SEARCH_SELECTOR',
            'DF_SHOW_PRODUCT_FEATURES',
            'DF_SHOW_PRODUCT_VARIATIONS',
            'DF_UPDATE_ON_SAVE_DELAY',
            'DF_UPDATE_ON_SAVE_LAST_EXEC',
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

    public function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'doofinder_product`');
    }

    protected function getWarningMultishopHtml()
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

    public function getContent()
    {
        $stop = $this->getWarningMultishopHtml();
        if ($stop) {
            return $stop;
        }
        $this->migrateOldConfigHashIDs();
        $adv = Tools::getValue('adv', 0);
        $this->context->smarty->assign('adv', $adv);

        $msg = $this->postProcess();

        $output = $msg;
        $oldPS = false;
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
        $skipurl = $this->context->link->getAdminLink('AdminModules', true) . '?' . http_build_query($skip_url_params);
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
            $output .= $this->renderFormDataFeed($adv);
            if (!$dfEnabledV9) {
                $output .= $this->renderFormInternalSearch($adv);
                $output .= $this->renderFormCustomCSS($adv);
            }
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

    protected function isConfigured()
    {
        $skip = Tools::getValue('skip');
        if ($skip) {
            Configuration::updateValue('DF_ENABLE_HASH', 0);
            Configuration::updateValue('DF_ENABLED_V9', true);
            $this->manualInstallation();
        }
        $sql = 'SELECT id_configuration FROM ' . _DB_PREFIX_ . 'configuration WHERE name = \'DF_ENABLE_HASH\'';

        return Db::getInstance()->getValue($sql);
    }

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

    protected function buildFeedUrl($shop_id, $language, $currency)
    {
        $shop_url = $this->getShopURL($shop_id);

        return $shop_url . ltrim($this->_path, DIRECTORY_SEPARATOR)
            . 'feed.php?'
            . 'currency=' . Tools::strtoupper($currency)
            . '&language=' . Tools::strtoupper($language)
            . '&dfsec_hash=' . Configuration::get('DF_API_KEY');
    }

    protected function renderFormCustomCSS($adv = false)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        // $helper->submit_action = 'submitDoofinderModuleCustomCSS';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '')
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesCustomCSS(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];
        $this->context->smarty->assign('id_tab', 'custom_css_tab');
        $html = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/pre_tab.tpl');
        $html .= $helper->generateForm([$this->getConfigFormCustomCSS()]);
        $html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
    }

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

    protected function renderFormChangeVersion($adv = false)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '')
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesChangeVersion(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigFormChangeVersion()]);
    }

    protected function renderFormInternalSearch($adv = false)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        // $helper->submit_action = 'submitDoofinderModuleInternalSearch';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '')
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesInternalSearch(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $id_shop = 1;
        $errorsMsg = '';
        if (method_exists($this->context->shop, 'getContextShopID')) {
            $id_shop = $this->context->shop->getContextShopID();
        }
        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            $facets_enabled = Configuration::get('DF_OWSEARCHFAC');
            if ($facets_enabled) {
                if (
                    !$this->isRegisteredInHookInShop('displayLeftColumn', $id_shop) &&
                    !$this->isRegisteredInHookInShop('displayRightColumn', $id_shop)
                ) {
                    $link = $this->context->link->getAdminLink('AdminModulesPositions');
                    $msg = $this->l('You must hook Doofinder on displayLeftColumn or displayRightColumn');
                    $errorsMsg .= $this->displayErrorCtm($msg, $link);
                }
            }
        } else {
            $overwrite_search = Configuration::get('DF_OWSEARCH', null);
            if ($overwrite_search && !$this->isRegisteredInHookInShop('productSearchProvider', $id_shop)) {
                $link = $this->context->link->getAdminLink('AdminModulesPositions');
                $msg = $this->l('You must hook Doofinder on productSearchProvider');
                $errorsMsg .= $this->displayErrorCtm($msg, $link);
            }
        }

        $this->context->smarty->assign('id_tab', 'internal_search_tab');
        $html = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/pre_tab.tpl');
        $html .= $errorsMsg;
        $html .= $this->renderFormDataEmbeddedSearch($adv);
        $html .= $helper->generateForm([$this->getConfigFormInternalSearch()]);
        $html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
    }

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

            $usingV9 = (bool) Configuration::get('DF_ENABLED_V9');
            if (!$usingV9) {
                $html .= $this->renderFormchangeVersion($adv);
                $html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/change_version.tpl');
            }
        } else {
            $this->context->controller->warnings[] = $this->l("This shop is new and it hasn't been synchronized with Doofinder yet.");
        }
        $html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
    }

    protected function showNewShopForm($shop)
    {
        $installation_id = Configuration::get('DF_INSTALLATION_ID', null, (int) $shop->id_shop_group, (int) $shop->id);
        $multishop_enable = Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE');
        $apikey = Configuration::get('DF_AI_APIKEY');

        if (!$installation_id && $multishop_enable && $apikey) {
            return true;
        }

        return false;
    }

    protected function renderFormDataEmbeddedSearch($adv = false)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '')
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesEmbeddedSearch(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigFormEmbeddedSearch()]);
    }

    protected function getBooleanFormValue()
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

    protected function getConfigFormCreateShop()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Create Shop'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [],
                'submit' => [
                    'title' => $this->l('Create New Shop'),
                    'name' => 'submitDoofinderModuleCreateShop',
                ],
            ],
        ];
    }

    protected function getConfigFormCustomCSS()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Custom CSS'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Extra CSS'),
                        'name' => 'DF_EXTRA_CSS',
                        'desc' => $this->l('Extra CSS to adjust Doofinder to your template'),
                        'cols' => 100,
                        'rows' => 10,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Custom CSS Options'),
                    'name' => 'submitDoofinderModuleCustomCSS',
                ],
            ],
        ];
    }

    protected function getConfigFormSearchLayer()
    {
        $currencies = Currency::getCurrencies();

        $inputs = [];
        if (!$this->haveHashId() || Configuration::get('DF_ENABLED_V9')) {
            $inputs[] = [
                'type' => 'text',
                'label' => $this->l('Doofinder Installation ID'),
                'name' => 'DF_INSTALLATION_ID',
                'desc' => $this->l('INSTALLATION_ID_EXPLANATION'),
                'lang' => false,
            ];
        } else {
            foreach ($currencies as $cur) {
                $currency_iso = Tools::strtoupper($cur['iso_code']);
                $label = $this->l('Doofinder Search Engine ID');
                $label .= ' ' . $this->l(sprintf('for currency %s', $currency_iso));
                $inputs[] = [
                    'type' => 'text',
                    'label' => $label,
                    'name' => 'DF_HASHID_' . $currency_iso,
                    'desc' => $this->l('SEARCH_ENGINE_ID_EXPLANATION'),
                    'lang' => true,
                ];
            }
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Search Layer'),
                    'icon' => 'icon-cogs',
                ],
                'input' => $inputs,
                'submit' => [
                    'title' => $this->l('Save Layer Widget Options'),
                    'name' => 'submitDoofinderModuleSearchLayer',
                ],
            ],
        ];
    }

    protected function getConfigFormChangeVersion()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Live Layer'),
                    'icon' => 'icon-cogs',
                ],
                'description' => $this->l('Activate this option to update Doofinder layer to the Live Layer version'),
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Activate Live Layer?'),
                        'name' => 'DF_ENABLED_V9',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (Configuration::get('DF_API_KEY') ? 'hidden' : 'text'),
                        'label' => $this->l('Doofinder Api Key'),
                        'name' => 'DF_API_KEY',
                        'desc' => sprintf(
                            $this->l('Click %s to access your API key'),
                            '<a href="https://admin.doofinder.com/en/admin/api/" target="_blank">' . $this->l('here') . '</a>'
                        ),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Change version'),
                    'name' => 'submitDoofinderModuleChangeVersion',
                ],
            ],
        ];
    }

    protected function getConfigFormEmbeddedSearch()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Embedded Search'),
                    'icon' => 'icon-cogs',
                ],
                'description' => $this->l('DF_EB_LAYER_DESCRIPTION'),
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Override Search Page & Enable embedded layer'),
                        'name' => 'DF_OWSEARCHEB',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                        'desc' => $this->l('It will enable a empty div to print your embedded layer'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Embedded Search Options'),
                    'name' => 'submitDoofinderModuleEmbeddedSearch',
                ],
            ],
        ];
    }

    protected function getConfigFormInternalSearch()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('API Internal Search'),
                    'icon' => 'icon-cogs',
                ],
                'description' => $this->l('DF_API_LAYER_DESCRIPTION'),
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Overwrite Search page with Doofinder results'),
                        'name' => 'DF_OWSEARCH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Enable facets on Overwrite Search Page'),
                        'desc' => $this->l('To enable this you must enable also Overwrite Search page'),
                        'name' => 'DF_OWSEARCHFAC',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Doofinder Api Key'),
                        'name' => 'DF_API_KEY',
                        'desc' => $this->l('Api Key, needed to overwrite Search page'),
                    ],
                    [
                        'col' => 9,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-code"></i>',
                        'desc' => $this->l('BANNER_CSS_SELECTOR_EXPLAINING') . ': "#content-wrapper #main h2"',
                        'name' => 'DF_APPEND_BANNER',
                        'label' => $this->l('"Append after" banner on Overwrite Search Page'),
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-code"></i>',
                        'desc' => $this->l('Optional. Default empty. Only if you have another "query input"'),
                        'name' => 'DF_SEARCH_SELECTOR',
                        'label' => $this->l('Query Input Selector'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Custom attribute separator'),
                        'name' => 'DF_CUSTOMEXPLODEATTR',
                        'desc' => $this->l('Optional. Used if you have a custom data feed'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Internal Search Options'),
                    'name' => 'submitDoofinderModuleInternalSearch',
                ],
            ],
        ];
    }

    protected function getConfigFormDataFeed()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Data Feed'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Display Prices in Data Feed'),
                        'name' => 'DF_GS_DISPLAY_PRICES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Display Prices With Taxes'),
                        'name' => 'DF_GS_PRICES_USE_TAX',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Export full categories path in the feed'),
                        'name' => 'DF_FEED_FULL_PATH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Include product variations in feed'),
                        'name' => 'DF_SHOW_PRODUCT_VARIATIONS',
                        'options' => [
                            'query' => [
                                [
                                    'id' => '0',
                                    'name' => $this->l('No, only product'),
                                ],
                                [
                                    'id' => '1',
                                    'name' => $this->l('Yes, Include each variations'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Attribute Groups'),
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
                        'label' => $this->l('Include product features in feed'),
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
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Overwrite Search page with Doofinder results'),
                        'name' => 'DF_OWSEARCH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Process changed products'),
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
                        'label' => $this->l('Debug Mode. Write info logs in doofinder.log file'),
                        'name' => 'DF_DEBUG',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable doofinder-links.js'),
                        'name' => 'DF_DSBL_DFLINK_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable doofinder-pagination(_15).js'),
                        'name' => 'DF_DSBL_DFPAG_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable doofinder_facets.js'),
                        'name' => 'DF_DSBL_DFFAC_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable js.cookie.js'),
                        'name' => 'DF_DSBL_DFCKIE_JS',
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
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable facets cache'),
                        'name' => 'DF_DSBL_FAC_CACHE',
                        'desc' => $this->l('Caution. This increment API requests'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable Ajax Token'),
                        'name' => 'DF_DSBL_AJAX_TKN',
                        'desc' => $this->l('Caution. Using this mean that you have a problem on hookFooter'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Restart manual override'),
                        'name' => 'DF_RESTART_OV',
                        'desc' => $this->l('This will try to remove & reinstall the SearchController'),
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

    protected function getConfigFormValuesAdvanced()
    {
        return [
            'DF_API_KEY' => Configuration::get('DF_API_KEY'),
            'DF_REGION' => Configuration::get('DF_REGION'),
            'DF_DEBUG' => Configuration::get('DF_DEBUG'),
            'DF_DSBL_DFLINK_JS' => Configuration::get('DF_DSBL_DFLINK_JS'),
            'DF_DSBL_DFPAG_JS' => Configuration::get('DF_DSBL_DFPAG_JS'),
            'DF_DSBL_DFFAC_JS' => Configuration::get('DF_DSBL_DFFAC_JS'),
            'DF_DSBL_DFCKIE_JS' => Configuration::get('DF_DSBL_DFCKIE_JS'),
            'DF_DSBL_HTTPS_CURL' => Configuration::get('DF_DSBL_HTTPS_CURL'),
            'DF_DEBUG_CURL' => Configuration::get('DF_DEBUG_CURL'),
            'DF_DSBL_FAC_CACHE' => Configuration::get('DF_DSBL_FAC_CACHE'),
            'DF_DSBL_AJAX_TKN' => Configuration::get('DF_DSBL_AJAX_TKN'),
            'DF_RESTART_OV' => false,
        ];
    }

    protected function getConfigFormValuesCustomCSS()
    {
        return [
            'DF_EXTRA_CSS' => Configuration::get('DF_EXTRA_CSS'),
        ];
    }

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
            'DF_OWSEARCH' => Configuration::get('DF_OWSEARCH'),
            'DF_UPDATE_ON_SAVE_DELAY' => Configuration::get('DF_UPDATE_ON_SAVE_DELAY'),
        ];
    }

    protected function getConfigFormValuesSearchLayer($update = false)
    {
        $fields = [];

        if (!$this->haveHashId() || Configuration::get('DF_ENABLED_V9')) {
            $fields['DF_INSTALLATION_ID'] = Configuration::get('DF_INSTALLATION_ID');

            Configuration::updateValue('DF_ENABLED_V9', true);
        } else {
            // Language on this must be "false" to get inactive also and can save. The problem is that the default
            // multilang selector on PrestaShop inputs get disabled langs on the shop and rear to the admin to think
            // cannot save correctly on submit, when really not save the langs that are not enable on that shop :/
            // The same problem trying to filter with the shop :/ So false,null on this case
            foreach (Language::getLanguages(false, null) as $lang) {
                $currencies = Currency::getCurrencies();
                foreach ($currencies as $cur) {
                    $currency_iso = Tools::strtoupper($cur['iso_code']);
                    $field_name = 'DF_HASHID_' . $currency_iso . '_' . $lang['id_lang'];
                    $field_name_iso = 'DF_HASHID_' . $currency_iso . '_' . Tools::strtoupper($lang['iso_code']);
                    if ($update) {
                        $fields[$field_name] = [
                            'real_config' => $field_name_iso,
                            'value' => Configuration::get($field_name),
                        ];
                    } else {
                        $fields['DF_HASHID_' . $currency_iso][$lang['id_lang']] = Configuration::get($field_name_iso);
                    }
                }
            }
        }

        return $fields;
    }

    protected function getConfigFormValuesInternalSearch()
    {
        $fields = [
            'DF_OWSEARCH' => Configuration::get('DF_OWSEARCH'),
            'DF_OWSEARCHFAC' => Configuration::get('DF_OWSEARCHFAC'),
            'DF_API_KEY' => Configuration::get('DF_API_KEY'),
            'DF_APPEND_BANNER' => Configuration::get('DF_APPEND_BANNER'),
            'DF_SEARCH_SELECTOR' => Configuration::get('DF_SEARCH_SELECTOR'),
            'DF_CUSTOMEXPLODEATTR' => Configuration::get('DF_CUSTOMEXPLODEATTR'),
        ];

        return $fields;
    }

    protected function getConfigFormValuesChangeVersion()
    {
        $fields = [
            'DF_ENABLED_V9' => Configuration::get('DF_ENABLED_V9'),
            'DF_API_KEY' => Configuration::get('DF_API_KEY'),
        ];

        return $fields;
    }

    protected function getConfigFormValuesEmbeddedSearch()
    {
        $fields = [
            'DF_OWSEARCHEB' => Configuration::get('DF_OWSEARCHEB'),
        ];

        return $fields;
    }

    protected function postProcess()
    {
        $form_values = [];
        $formUpdated = '';
        $messages = '';
        if (Tools::isSubmit('submitDoofinderModuleChangeVersion')) {
            if (Tools::getValue('DF_ENABLED_V9')) {
                Configuration::updateValue('DF_API_KEY', Tools::getValue('DF_API_KEY'));

                if (!Configuration::get('DF_INSTALLATION_ID')) {
                    $shopHashes = [];
                    $defaultHash = [];

                    foreach (Language::getLanguages(false, null) as $lang) {
                        $currencies = Currency::getCurrencies();
                        foreach ($currencies as $cur) {
                            $currency_iso = Tools::strtoupper($cur['iso_code']);
                            $field_name_iso = 'DF_HASHID_' . $currency_iso . '_' . Tools::strtoupper($lang['iso_code']);
                            $hashId = Configuration::get($field_name_iso);
                            if ($hashId && $hashId != null && $hashId != '') {
                                $shopHashes[$lang['iso_code']][$currency_iso] = $hashId;
                                if (empty($defaultHash)) {
                                    $defaultHash = [
                                        'currency' => $currency_iso,
                                        'language' => $lang['iso_code'],
                                        'hashid' => $hashId,
                                    ];
                                }
                            }
                        }
                    }

                    $installationID = $this->createInstallationID($shopHashes, $defaultHash);
                    Configuration::updateValue('DF_INSTALLATION_ID', $installationID);
                } else {
                    $hashIdDefault = $this->haveHashId(true);
                    $this->alternateLayoutStateInDoofinder($hashIdDefault, true);
                }

                Configuration::updateValue('DF_ENABLED_V9', true);
            } else {
                $hashIdDefault = $this->haveHashId(true);
                $this->alternateLayoutStateInDoofinder($hashIdDefault, false);
                Configuration::updateValue('DF_ENABLED_V9', false);
            }
            $formUpdated = 'search_layer_tab';
        }

        if (((bool) Tools::isSubmit('submitDoofinderModuleDataFeed')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesDataFeed());
            $formUpdated = 'data_feed_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleSearchLayer')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesSearchLayer(true));
            $formUpdated = 'search_layer_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleInternalSearch')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesInternalSearch());
            $formUpdated = 'internal_search_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleEmbeddedSearch')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesEmbeddedSearch());
            $formUpdated = 'internal_search_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleCustomCSS')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesCustomCSS());
            $formUpdated = 'custom_css_tab';
        }

        if (((bool) Tools::isSubmit('submitDoofinderModuleAdvanced')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesAdvanced());
            $formUpdated = 'advanced_tab';
            $messages .= $this->testDoofinderApi();
            $this->context->smarty->assign('adv', 1);
            $restartOV = Tools::getValue('DF_RESTART_OV');
            if ($restartOV) {
                $messages .= $this->manualOverride(true);
            }
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
            $html = false;
            if (strpos($postKey, 'DOOFINDER_SCRIPT_') !== false) {
                $html = true;
                $value = str_replace('type="text/javascript"', '', $value);
            }
            Configuration::updateValue($postKey, $value, $html);
        }

        $ovrSearch = Configuration::get('DF_OWSEARCH');
        $embeddedSearch = Configuration::get('DF_OWSEARCHEB');
        if (($ovrSearch || $embeddedSearch) && $formUpdated == 'internal_search_tab') {
            $messages .= $this->manualOverride();
            if ($ovrSearch) {
                $this->registerHook('productSearchProvider');
            }
        }

        if (!$ovrSearch && $formUpdated == 'internal_search_tab') {
            $this->unregisterHook('productSearchProvider');
        }

        $ovrSearchFac = Configuration::get('DF_OWSEARCHFAC');
        if ($ovrSearchFac && $formUpdated == 'internal_search_tab') {
            $doofinder_hash = Tools::encrypt('PrestaShop_Doofinder_Facets' . date('YmdHis'));
            Configuration::updateValue('DF_FACETS_TOKEN', $doofinder_hash);
        }

        if ($formUpdated == 'data_feed_tab') {
            if (((bool) Configuration::get('DF_ENABLED_V9') && (bool) Configuration::get('DF_OWSEARCH')) ||
                (bool) Configuration::get('DF_UPDATE_ON_SAVE_DELAY')
            ) {
                $this->setSearchEnginesByConfig();
            }
            if (Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') && (int) Tools::getValue('DF_UPDATE_ON_SAVE_DELAY') < 15) {
                Configuration::updateValue('DF_UPDATE_ON_SAVE_DELAY', 15);
            }

            $msg = $this->l('IF YOU HAVE CHANGED ANYTHING IN YOUR DATA FEED SETTINGS, REMEMBER YOU MUST REPROCESS.');
            $messages .= $this->displayWarningCtm($msg);
        }

        if ($formUpdated == 'custom_css_tab') {
            try {
                $extraCSS = Configuration::get('DF_EXTRA_CSS');
                $cssVS = (int) Configuration::get('DF_CSS_VS');
                $file = 'doofinder_custom_' . $this->context->shop->id . '_vs_' . $cssVS . '.css';
                if (file_exists(dirname(__FILE__) . '/views/css/' . $file)) {
                    unlink(dirname(__FILE__) . '/views/css/' . $file);
                }
                ++$cssVS;
                Configuration::updateValue('DF_CSS_VS', $cssVS);
                $file = 'doofinder_custom_' . $this->context->shop->id . '_vs_' . $cssVS . '.css';
                $result_write = file_put_contents(dirname(__FILE__) . '/views/css/' . $file, $extraCSS);
                $is_writable = is_writable(dirname(__FILE__) . '/views/css/');
                if ($result_write === false || !$is_writable) {
                    $msg = 'Cannot save css file on ' . dirname(__FILE__) . '/views/css/ folder. '
                        . 'Please be sure this folder have writing permissions. '
                        . 'Folder Writable? ' . (($is_writable) ? 'Yes!' : 'Nope :(');
                    $messages .= $this->displayErrorCtm($this->l($msg));
                }
            } catch (Exception $e) {
                trigger_error('Doofinder Captured exception:' . $e->getMessage(), E_USER_WARNING);
            }
        }

        if (!empty($formUpdated)) {
            $messages .= $this->displayConfirmationCtm($this->l('Settings updated!'));
            $this->context->smarty->assign('formUpdatedToClick', $formUpdated);
        }

        return $messages;
    }

    private function configureHookCommon($params = false)
    {
        $lang = Tools::strtoupper($this->context->language->language_code);
        $currency = Tools::strtoupper($this->context->currency->iso_code);
        $search_engine_id = Configuration::get('DF_HASHID_' . $currency . '_' . $lang);
        $df_region = Configuration::get('DF_REGION');
        $script = Configuration::get('DOOFINDER_SCRIPT_' . $lang);
        $extra_css = Configuration::get('DF_EXTRA_CSS');
        $df_querySelector = Configuration::get('DF_SEARCH_SELECTOR');
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
            'doofinder_search_selector' => $df_querySelector,
            'installation_ID' => $installation_ID,
            'currency' => $currency,
        ]);
        $appendTo = Configuration::get('DF_APPEND_BANNER');
        if (empty($appendTo)) {
            $appendTo = 'none';
        }
        $this->smarty->assign('doofinder_banner_append', $appendTo);

        return true;
    }

    public function hookHeader($params)
    {
        $this->configureHookCommon($params);
        if (Configuration::get('DF_ENABLED_V9')) {
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
        } else {
            if (
                isset($this->context->controller->php_self) &&
                $this->context->controller->php_self == 'search'
            ) {
                $noCookieJS = Configuration::get('DF_DSBL_DFCKIE_JS');
                $noFacetsJS = Configuration::get('DF_DSBL_DFFAC_JS');
                $noPaginaJS = Configuration::get('DF_DSBL_DFPAG_JS');
                $noLinksJS = Configuration::get('DF_DSBL_DFLINK_JS');

                $overwrite_search = Configuration::get('DF_OWSEARCH', null);
                $overwrite_facets = Configuration::get('DF_OWSEARCHFAC', null);
                if (version_compare(_PS_VERSION_, '1.7', '<')) {
                    if ($overwrite_search) {
                        if ($overwrite_facets) {
                            $css_path = str_replace('doofinder', 'blocklayered', $this->_path);
                            if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
                                if (!$noPaginaJS) {
                                    $this->context->controller->addJS(
                                        $this->_path . 'views/js/doofinder-pagination.js'
                                    );
                                }
                                if (file_exists(_PS_MODULE_DIR_ . 'blocklayered/blocklayered.css')) {
                                    $this->context->controller->addCSS(
                                        $css_path . 'blocklayered.css',
                                        'all'
                                    );
                                } else {
                                    $this->context->controller->addCSS(
                                        $this->_path . 'views/css/doofinder-filters.css',
                                        'all'
                                    );
                                }
                            } else {
                                if (!$noPaginaJS) {
                                    $this->context->controller->addJS(
                                        $this->_path . 'views/js/doofinder-pagination_15.js'
                                    );
                                }
                                if (file_exists(_PS_MODULE_DIR_ . 'blocklayered/blocklayered-15.css')) {
                                    $this->context->controller->addCSS($css_path . 'blocklayered-15.css', 'all');
                                } else {
                                    $this->context->controller->addCSS(
                                        $this->_path . 'views/css/doofinder-filters-15.css',
                                        'all'
                                    );
                                }
                            }
                            if (!$noFacetsJS) {
                                $this->context->controller->addJS($this->_path . 'views/js/doofinder_facets.js');
                            }
                        }
                    }
                    if (!$noCookieJS) {
                        $this->context->controller->addJS($this->_path . 'views/js/js.cookie.js');
                    }
                    $this->context->controller->addJQueryUI('ui.slider');
                    $this->context->controller->addJQueryUI('ui.accordion');
                    $this->context->controller->addJqueryPlugin('multiaccordion');
                    $this->context->controller->addJQueryUI('ui.sortable');
                    $this->context->controller->addJqueryPlugin('jscrollpane');
                    $this->context->controller->addJQueryPlugin('scrollTo');
                }
                if (!$noLinksJS) {
                    $this->context->controller->addJS($this->_path . 'views/js/doofinder-links.js');
                }
                $appendTo = Configuration::get('DF_APPEND_BANNER');
                if ($appendTo) {
                    $this->context->controller->addJS($this->_path . 'views/js/doofinder-banner.js');
                }
            }
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
    }

    public function hookDisplayFooter($params)
    {
        if (
            isset($this->context->controller->php_self) &&
            $this->context->controller->php_self == 'search'
        ) {
            $appendTo = Configuration::get('DF_APPEND_BANNER');
            $savedToken = Configuration::get('DF_FACETS_TOKEN');
            $this->context->smarty->assign([
                'doofinder_token' => $savedToken,
            ]);
            if (!empty($this->searchBanner) && !empty($appendTo)) {
                $this->context->smarty->assign([
                    'doofinder_banner_image' => $this->searchBanner['image'],
                    'doofinder_banner_blank' => $this->searchBanner['blank'],
                    'doofinder_banner_id' => $this->searchBanner['id'],
                    'doofinder_banner_link' => $this->searchBanner['link'],
                ]);
            }

            return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
        }

        return false;
    }

    public function hookDisplayLeftColumn($params)
    {
        if (
            isset($this->context->controller->php_self) &&
            $this->context->controller->php_self == 'search'
        ) {
            return $this->generateSearch();
        }

        return false;
    }

    public function hookDisplayRightColumn($params)
    {
        return $this->hookDisplayLeftColumn($params);
    }

    public function generateSearch($returnToSearchController = false)
    {
        $overwrite_search = Configuration::get('DF_OWSEARCH');
        $overwrite_facets = Configuration::get('DF_OWSEARCHFAC');
        if ($overwrite_search && ($overwrite_facets || $returnToSearchController)) {
            $query = Tools::getValue('search_query', Tools::getValue('ref'));
            $p = abs((int) Tools::getValue('p', 1));
            $n = abs((int) Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE')));
            $filters = Tools::getValue('filters', null);
            if (($search = $this->searchOnApi($query, $p, $n, 8000, $filters, true)) &&
                $query &&
                !is_array($query)
            ) {
                if ($returnToSearchController) {
                    return $search;
                }

                return $this->generateFiltersBlock($search['facets'], $search['filters'], $search['df_query_name']);
            } else {
                return false;
            }
        }
    }

    public function generateFiltersBlock($facets, $filters, $query_name = false)
    {
        if ($filter_block = $this->getFilterBlock($facets, $filters, $query_name)) {
            if ($filter_block['nbr_filterBlocks'] == 0) {
                return false;
            }

            $translate = [];
            $translate['price'] = $this->l('price');
            $translate['weight'] = $this->l('weight');

            $this->context->smarty->assign($filter_block);
            $this->context->smarty->assign([
                'hide_0_values' => Configuration::get('PS_LAYERED_HIDE_0_VALUES'),
                'blocklayeredSliderName' => $translate,
                'col_img_dir' => _PS_COL_IMG_DIR_,
            ]);

            return $this->display(__FILE__, 'views/templates/front/doofinder_facets.tpl');
        } else {
            return false;
        }
    }

    public function getFilterBlock($facets, $filters, $query_name)
    {
        $optionsDoofinder = $this->getDoofinderTermsOptions(false);

        $r_facets = [];
        $t_facets = [];
        if (isset($optionsDoofinder['facets'])) {
            foreach ($optionsDoofinder['facets'] as $f_values) {
                $r_facets[$f_values['name']] = $f_values['label'];
                $t_facets[$f_values['name']] = $f_values['type'];
            }
        }

        // Reorder filter block as doofinder dashboard
        $facetsBlock = [];
        foreach ($r_facets as $key_o => $value_o) {
            $facetsBlock[$key_o] = $facets[$key_o];
            $this->multiRenameKey(
                $facetsBlock[$key_o]['terms']['buckets'],
                ['key', 'doc_count'],
                ['term', 'count']
            );
            $facetsBlock[$key_o]['terms'] = $facetsBlock[$key_o]['terms']['buckets'];
            $facetsBlock[$key_o]['original_val'] = $value_o;
            if (count($facetsBlock[$key_o]['terms'])) {
                foreach ($facetsBlock[$key_o]['terms'] as $key_t => $value_t) {
                    $facetsBlock[$key_o]['terms'][$key_t]['selected'] = 0;
                    $facetsBlock[$key_o]['original_terms_val'][$key_t] = $value_t;
                }
            }
            $facetsBlock[$key_o]['_type'] = $t_facets[$key_o];
            if ($t_facets[$key_o] == 'range') {
                $facetsBlock[$key_o]['ranges'][0] = [
                    'from' => $facets[$key_o]['range']['buckets'][0]['from'],
                    'count' => $facets[$key_o]['range']['buckets'][0]['doc_count'],
                    'min' => floor($facets[$key_o]['range']['buckets'][0]['stats']['min']),
                    'max' => ceil($facets[$key_o]['range']['buckets'][0]['stats']['max']),
                    'total_count' => $facets[$key_o]['range']['buckets'][0]['stats']['count'],
                    'total' => $facets[$key_o]['range']['buckets'][0]['stats']['sum'],
                    'mean' => $facets[$key_o]['range']['buckets'][0]['stats']['avg'],
                    'selected_from' => false,
                    'selected_to' => false,
                ];
            }
        }
        $facets = $facetsBlock;

        return [
            'options' => $r_facets,
            'facets' => $facets,
            'filters' => $filters,
            'nbr_filterBlocks' => 1,
            'df_query_name' => $query_name,
        ];
    }

    public function getSelectedFilters()
    {
        $options = $this->getDoofinderTermsOptions();

        $filters = [];
        $option_keys = array_keys($options);
        foreach ($option_keys as $key) {
            if ($selected = Tools::getValue('layered_terms_' . $key, false)) {
                $filters[$key] = $selected;
            } elseif ($selected = Tools::getValue('layered_' . $key . '_slider', false)) {
                $selected = explode('_', $selected);
                $filters[$key] = [
                    'from' => $selected[0],
                    'to' => $selected[1],
                ];
            }
        }

        return $filters;
    }

    public function getPaginationValues($nb_products, $p, $n, &$pages_nb, &$range, &$start, &$stop)
    {
        $range = 2; /* how many pages around page selected */

        if ($n <= 0) {
            $n = 1;
        }

        if ($p < 0) {
            $p = 0;
        }

        if ($p > ($nb_products / $n)) {
            $p = ceil($nb_products / $n);
        }
        $pages_nb = ceil($nb_products / (int) $n);

        $start = (int) ($p - $range);
        if ($start < 1) {
            $start = 1;
        }

        $stop = (int) ($p + $range);
        if ($stop > $pages_nb) {
            $stop = (int) $pages_nb;
        }
    }

    public function ajaxCall()
    {
        $selected_filters = $this->getSelectedFilters();
        $_POST['filters'] = $selected_filters;

        $search = $this->generateSearch(true);
        $products = $search['result'];
        $p = abs((int) Tools::getValue('p', 1));
        $n = abs((int) Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE')));
        if (!$n) {
            $n = Configuration::get('PS_PRODUCTS_PER_PAGE');
        }

        // Add pagination variable
        $nArray = (int) Configuration::get('PS_PRODUCTS_PER_PAGE') != 10 ? [
            (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
            10,
            20,
            50,
        ] : [10, 20, 50];
        // Clean duplicate values
        $nArray = array_unique($nArray);
        asort($nArray);

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            $this->context->controller->addColorsToProductList($products);
        }

        $category = new Category(Tools::getValue(
            'id_category_layered',
            Configuration::get('PS_HOME_CATEGORY')
        ), (int) $this->context->language->id);

        // Generate meta title and meta description
        $category_title = (empty($category->meta_title) ? $category->name : $category->meta_title);
        $category_metas = Meta::getMetaTags((int) $this->context->language->id, 'category');
        $title = '';
        $keywords = '';

        $title = $category_title . $title;

        if (!empty($title)) {
            $meta_title = $title;
        } else {
            $meta_title = $category_metas['meta_title'];
        }

        $meta_description = $category_metas['meta_description'];

        $keywords = Tools::substr(Tools::strtolower($keywords), 0, 1000);
        if (!empty($keywords)) {
            $meta_keywords = rtrim($category_title . ', ' . $keywords . ', ' . $category_metas['meta_keywords'], ', ');
        }
        $nb_products = $search['total'];
        // var_dump($search);
        $pages_nb = 0;
        $range = 0;
        $start = 0;
        $stop = 0;
        $this->getPaginationValues($nb_products, $p, $n, $pages_nb, $range, $start, $stop);
        $this->context->smarty->assign(
            [
                'homeSize' => Image::getSize(ImageType::getFormatedName('home')),
                'nb_products' => $nb_products,
                'category' => $category,
                'pages_nb' => (int) $pages_nb,
                'p' => (int) $p,
                'n' => (int) $n,
                'range' => (int) $range,
                'start' => (int) $start,
                'stop' => (int) $stop,
                'n_array' => ((int) Configuration::get('PS_PRODUCTS_PER_PAGE') != 10) ? [
                    (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
                    10,
                    20,
                    50,
                ] : [10, 20, 50],
                'comparator_max_item' => (int) Configuration::get('PS_COMPARATOR_MAX_ITEM'),
                'products' => $products,
                'products_per_page' => (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
                'static_token' => Tools::getToken(false),
                'page_name' => 'search',
                'nArray' => $nArray,
                'compareProducts' => CompareProduct::getCompareProducts((int) $this->context->cookie->id_compare),
            ]
        );

        // Prevent bug with old template where category.tpl contain the title of the category
        // and category-count.tpl do not exists
        if (file_exists(_PS_THEME_DIR_ . 'category-count.tpl')) {
            $category_count = $this->context->smarty->fetch(_PS_THEME_DIR_ . 'category-count.tpl');
        } else {
            $category_count = '';
        }

        if ($nb_products == 0) {
            $product_list = $this->display(__FILE__, 'views/templates/front/doofinder-no-products.tpl');
        } else {
            $product_list = $this->context->smarty->fetch(_PS_THEME_DIR_ . 'product-list.tpl');
        }
        // To avoid Notice
        $filter_block = ['current_friendly_url' => ''];
        $vars = [
            // 'filtersBlock' => utf8_encode($this->generateFiltersBlock($search['facets'],$search['filters'])),
            'productList' => utf8_encode($product_list),
            'pagination' => $this->context->smarty->fetch(_PS_THEME_DIR_ . 'pagination.tpl'),
            'categoryCount' => $category_count,
            'meta_title' => $meta_title . ' - ' . Configuration::get('PS_SHOP_NAME'),
            'heading' => $meta_title,
            'meta_keywords' => isset($meta_keywords) ? $meta_keywords : null,
            'meta_description' => $meta_description,
            'current_friendly_url' => ((int) $n == (int) $nb_products) ? '#/show-all' :
                '#' . $filter_block['current_friendly_url'],
            // 'filters' => $filter_block['filters'],
            'nbRenderedProducts' => (int) $nb_products,
            'nbAskedProducts' => (int) $n,
        ];

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            $vars = array_merge($vars, ['pagination_bottom' => $this->context->smarty->assign(
                'paginationId',
                'bottom'
            )->fetch(_PS_THEME_DIR_ . 'pagination.tpl')]);
        }
        // We are sending an array in jSon to the .js controller, it will update both
        //  the filters and the products zones
        return Tools::jsonEncode($vars);
    }

    // http://stackoverflow.com/a/17254761
    public function multiRenameKey(&$array, $old_keys, $new_keys)
    {
        if (!is_array($array)) {
            ($array == '') ? $array = [] : false;

            return $array;
        }
        foreach ($array as &$arr) {
            if (is_array($old_keys)) {
                foreach ($new_keys as $k => $new_key) {
                    (isset($old_keys[$k])) ? true : $old_keys[$k] = null;
                    $arr[$new_key] = (isset($arr[$old_keys[$k]]) ? $arr[$old_keys[$k]] : null);
                    unset($arr[$old_keys[$k]]);
                }
            } else {
                $arr[$new_keys] = (isset($arr[$old_keys]) ? $arr[$old_keys] : null);
                unset($arr[$old_keys]);
            }
        }

        return $array;
    }

    public function hookProductSearchProvider($params)
    {
        $ovrSearch = Configuration::get('DF_OWSEARCH');
        if (isset($params['query']) && $ovrSearch) {
            $query = $params['query'];
            if ($query->getSearchString()) {
                if ($this->testDoofinderApi(Context::getContext()->language->iso_code)) {
                    return new DoofinderProductSearchProvider($this);
                } else {
                    return null;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function hookActionProductSave($params)
    {
        if (Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
            $action = $params['product']->active ? 'update' : 'delete';
            $id_shop = $this->context->shop->id;
            $this->addProductQueue($params['id_product'], $id_shop, $action);

            if ($this->allowProcessProductsQueue()) {
                $this->processProductQueue($id_shop);
            }
        }
    }

    public function hookActionProductDelete($params)
    {
        if (Configuration::get('DF_UPDATE_ON_SAVE_DELAY')) {
            $id_shop = $this->context->shop->id;
            $this->addProductQueue($params['id_product'], $id_shop, 'delete');

            if ($this->allowProcessProductsQueue()) {
                $this->processProductQueue($id_shop);
            }
        }
    }

    public function allowProcessProductsQueue()
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

    public function setExecUpdateOnSave()
    {
        Configuration::updateValue('DF_UPDATE_ON_SAVE_LAST_EXEC', date('Y-m-d H:i:s'));
    }

    public function addProductQueue($id_product, $id_shop, $action)
    {
        Db::getInstance()->insert(
            'doofinder_product',
            [
                'id_shop' => $id_shop,
                'id_product' => $id_product,
                'action' => $action,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            false,
            true,
            Db::REPLACE
        );
    }

    public function processProductQueue($id_shop)
    {
        $this->setExecUpdateOnSave();

        $products_update = $this->getProductsQueue($id_shop);
        $products_delete = $this->getProductsQueue($id_shop, 'delete');

        $languages = Language::getLanguages(true, $id_shop);
        $currencies = Currency::getCurrenciesByIdShop($id_shop);

        foreach ($languages as $language) {
            foreach ($currencies as $currency) {
                $this->sendProductsApi($products_update, $id_shop, $language['id_lang'], $currency['id_currency']);
                $this->sendProductsApi($products_delete, $id_shop, $language['id_lang'], $currency['id_currency'], 'delete');
            }
        }

        $this->deleteProductQueue($id_shop);
    }

    public function getProductsQueue($id_shop, $action = 'update')
    {
        $products = Db::getInstance()->executeS(
            '
            SELECT id_product FROM ' . _DB_PREFIX_ . "doofinder_product 
            WHERE action = '" . pSQL($action) . "' AND id_shop = " . (int) $id_shop
        );

        return array_column($products, 'id_product');
    }

    public function deleteProductQueue($id_shop)
    {
        Db::getInstance()->execute('DELETE from ' . _DB_PREFIX_ . 'doofinder_product WHERE id_shop = ' . (int) $id_shop);
    }

    public function sendProductsApi($products, $id_shop, $id_lang, $id_currency, $action = 'update')
    {
        if (empty($products)) {
            return;
        }

        $apikey = explode('-', Configuration::get('DF_API_KEY'))[1];
        $region = Configuration::get('DF_REGION');

        $hashid = $this->getHashId($id_lang, $id_currency);

        if ($hashid) {
            require_once dirname(__FILE__) . '/lib/dfProduct_build.php';
            require_once dirname(__FILE__) . '/lib/doofinder_api_products.php';

            if ($action == 'update') {
                $builder = new DfProductBuild($id_shop, $id_lang, $id_currency);
                $builder->setProducts($products);
                $payload = $builder->build();

                $api = new DoofinderApiProducts($hashid, $apikey, $region);
                $response = $api->updateBulk($payload);

                if (isset($response['error']) && !empty($response['error'])) {
                    $this->debug(json_encode($response['error']));
                }
            } elseif ($action == 'delete') {
                $api = new DoofinderApiProducts($hashid, $apikey, $region);
                $payload = array_map(function ($a) {
                    return ['id' => $a];
                }, $products);

                $response = $api->deleteBulk(json_encode($payload));

                if (isset($response['error']) && !empty($response['error'])) {
                    $this->debug(json_encode($response['error']));
                }
            }
        }
    }

    public function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // lowercase
        $text = Tools::strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    public function isRegisteredInHookInShop($hook, $id_shop = 0)
    {
        if (!$this->id) {
            return false;
        }

        $sql = 'SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'hook_module` hm
            LEFT JOIN `' . _DB_PREFIX_ . 'hook` h ON
                            (h.`id_hook` = hm.`id_hook`)
            WHERE h.`name` = \'' . (string) pSQL($hook) . '\''
            . ' AND hm.id_shop = ' . (int) pSQL($id_shop) . ' AND hm.`id_module` = ' . (int) pSQL($this->id);

        return Db::getInstance()->getValue($sql);
    }

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

    public function getDoofinderTermsOptions($only_facets = true)
    {
        $disableCache = Configuration::get('DF_DSBL_FAC_CACHE');
        $cacheOptionsDoofinderFileName = _PS_CACHE_DIR_
            . 'smarty/compile/OptionsDoofinderFileName-'
            . Context::getContext()->shop->id . '-'
            . Context::getContext()->language->id . '-'
            . Tools::encrypt('OptionsDoofinderFileName')
            . '-' . date('Ymd') . '.html';

        $debug = Configuration::get('DF_DEBUG');
        if (isset($debug) && $debug) {
            $this->debug('Get Terms Options API Start');
        }
        $lang_iso = Tools::strtoupper(Context::getContext()->language->iso_code);
        $currency_iso = Tools::strtoupper(Context::getContext()->currency->iso_code);
        $hash_id = Configuration::get('DF_HASHID_' . $currency_iso . '_' . $lang_iso);
        $api_key = Configuration::get('DF_API_KEY');
        if ($hash_id && $api_key) {
            try {
                $options = [];
                if (
                    file_exists($cacheOptionsDoofinderFileName) &&
                    !$disableCache
                ) {
                    $options = json_decode(Tools::file_get_contents($cacheOptionsDoofinderFileName), true);
                }
                if (empty($options)) {
                    if (!class_exists('DoofinderApi')) {
                        include_once dirname(__FILE__) . '/lib/doofinder_api.php';
                    }
                    $df = new DoofinderApi($hash_id, $api_key, false, ['apiVersion' => '5']);
                    $dfOptions = $df->getOptions();
                    if ($dfOptions) {
                        $options = json_decode($dfOptions, true);
                    }
                    if (isset($debug) && $debug) {
                        $this->debug('Options: ' . var_export($dfOptions, true));
                    }
                    $jsonCacheOptionsDoofinder = json_encode($options);
                    file_put_contents($cacheOptionsDoofinderFileName, $jsonCacheOptionsDoofinder);
                }

                if ($only_facets) {
                    $facets = [];
                    $r_facets = [];
                    if (isset($options['facets'])) {
                        $facets = $options['facets'];
                    }
                    foreach ($facets as $f_values) {
                        $r_facets[$f_values['name']] = $f_values['label'];
                    }

                    return $r_facets;
                } else {
                    return $options;
                }
            } catch (Exception $e) {
                if (isset($debug) && $debug) {
                    $this->debug('Exception:  ' . $e->getMessage());
                }
            }
        }

        return false;
    }

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
            $customexplodeattr = Configuration::get('DF_CUSTOMEXPLODEATTR');
            foreach ($dfResultsArray as $entry) {
                if ($entry['type'] == 'product') {
                    if (!empty($customexplodeattr) && strpos($entry['id'], $customexplodeattr) !== false) {
                        $id_products = explode($customexplodeattr, $entry['id']);
                        $product_pool_attributes[] = $id_products[1];
                        $product_pool_ids[] = (int) pSQL($id_products[0]);
                    }
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

    public function getFormatedName($name)
    {
        $theme_name = Context::getContext()->shop->theme_name;
        $name_without_theme_name = str_replace(['_' . $theme_name, $theme_name . '_'], '', $name);

        // check if the theme name is already in $name if yes only return $name
        if (strstr($name, $theme_name) && ImageType::getByNameNType($name)) {
            return $name;
        } elseif (ImageType::getByNameNType($name_without_theme_name . '_' . $theme_name)) {
            return $name_without_theme_name . '_' . $theme_name;
        } elseif (ImageType::getByNameNType($theme_name . '_' . $name_without_theme_name)) {
            return $theme_name . '_' . $name_without_theme_name;
        } else {
            return $name_without_theme_name . '_default';
        }
    }

    public function displayErrorCtm($error, $link = false)
    {
        return $this->displayGeneralMsg($error, 'error', 'danger', $link);
    }

    public function displayWarningCtm($warning, $link = false)
    {
        return $this->displayGeneralMsg($warning, 'warning', 'warning', $link);
    }

    public function displayConfirmationCtm($string, $link = false)
    {
        return $this->displayGeneralMsg($string, 'confirmation', 'success', $link);
    }

    public function displayGeneralMsg($string, $type, $alert, $link = false)
    {
        $this->context->smarty->assign(
            [
                'd_type_message' => $type,
                'd_type_alert' => $alert,
                'd_message' => $string,
                'd_link' => $link,
            ]
        );

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/display_msg.tpl');
    }

    public function canAjax()
    {
        $id_shop = 1;
        if (method_exists($this->context->shop, 'getContextShopID')) {
            $id_shop = $this->context->shop->getContextShopID();
        }
        $facets_enabled = Configuration::get('DF_OWSEARCHFAC');
        $response = [];
        if ($facets_enabled) {
            $savedToken = Configuration::get('DF_FACETS_TOKEN');
            $dsblToken = Configuration::get('DF_DSBL_AJAX_TKN');
            $token = Tools::getValue('token');
            if (($savedToken == $token) || !$dsblToken) {
                if (
                    !$this->isRegisteredInHookInShop('displayLeftColumn', $id_shop) &&
                    !$this->isRegisteredInHookInShop('displayRightColumn', $id_shop)
                ) {
                    $response = [
                        'error' => 'You must hook Doofinder on displayLeftColumn or displayRightColumn',
                    ];
                }
            } else {
                $response = [
                    'error' => 'Token is invalid',
                ];
            }
        } else {
            $response = [
                'error' => 'Doofinder facets not enabled but executing ajax request??',
            ];
        }

        if (empty($response)) {
            return true;
        } else {
            echo json_encode($response);

            return false;
        }
    }

    public function getEmbeddedTemplateLocation()
    {
        return _PS_MODULE_DIR_ . $this->name . '/views/templates/front/doofinder-embedded.tpl';
    }

    private function setSearchEnginesByConfig()
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
    }

    private function debug($message)
    {
        $debug = Configuration::get('DF_DEBUG', null);
        if (isset($debug) && $debug) {
            error_log("$message\n", 3, dirname(__FILE__) . '/doofinder.log');
        }
    }

    public function migrateOldConfigHashIDs()
    {
        $shops = Shop::getShops();
        foreach ($shops as $shop) {
            $sid = $shop['id_shop'];
            $sgid = $shop['id_shop_group'];

            foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
                $lang_iso = Tools::strtoupper($lang['iso_code']);
                $hash_id = Configuration::get('DF_HASHID_' . $lang_iso, null, $sgid, $sid);
                if ($hash_id) {
                    $currencies = Currency::getCurrencies();
                    foreach ($currencies as $cur) {
                        $currency_iso = Tools::strtoupper($cur['iso_code']);
                        Configuration::updateValue(
                            'DF_HASHID_' . $currency_iso . '_' . $lang_iso,
                            $hash_id,
                            null,
                            $sgid,
                            $sid
                        );
                    }
                    Configuration::deleteByName('DF_HASHID_' . $lang_iso);
                }
            }
        }

        return true;
    }

    public function checkOutsideConnection()
    {
        // Require only on this function to not overload memory with not needed classes
        require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';
        $client = new EasyREST(true, 3);
        $result = $client->get('https://admin.doofinder.com/auth/login');
        if (
            $result && $result->originalResponse && isset($result->headers['code'])
            && (strpos($result->originalResponse, 'HTTP/2 200') || $result->headers['code'] == 200)
        ) {
            return true;
        } else {
            return false;
        }
    }

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

    public function autoinstaller($shop_id = null)
    {
        if (!empty($shop_id)) {
            $shop = Shop::getShop($shop_id);
            $this->createStore($shop);

            return;
        }

        $shops = Shop::getShops();
        $this->manualInstallation();
        foreach ($shops as $shop) {
            $this->createStore($shop);
        }
    }

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
                if ($cur["deleted"] == 1) {
                    continue;
                }
                $ciso = $cur['iso_code'];
                $feed_url = $this->buildFeedUrl($shopId, $lang['iso_code'], $ciso);
                $store_data['search_engines'][] = [
                    'name' => $shop['name'] . ' | Lang:' . $lang['iso_code'] . ' Currency:' . strtoupper($ciso),
                    'language' => $lang['language_code'],
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
            }
        }

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
        Configuration::updateValue('DF_GS_MPN_FIELD', 'reference', false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_FEED_MAINCATEGORY_PATH', false, false, $shopGroupId, $shopId);
        Configuration::updateValue('DF_GS_IMAGE_SIZE', key(dfTools::getAvailableImageSizes()), false, $shopGroupId, $shopId);
    }

    public function getShopBaseURI($shop)
    {
        return $shop->physical_uri . $shop->virtual_uri;
    }

    public function getShopURL($shop_id)
    {
        $shop = new Shop($shop_id);
        $force_ssl = (Configuration::get('PS_SSL_ENABLED')
            && Configuration::get('PS_SSL_ENABLED_EVERYWHERE'));
        $url = ($force_ssl) ? 'https://' . $shop->domain_ssl : 'http://' . $shop->domain;

        return $url . $this->getShopBaseURI($shop);
    }

    public function checkApiKey($text = false)
    {
        $result = Db::getInstance()->getValue('SELECT id_configuration FROM ' . _DB_PREFIX_
            . 'configuration WHERE name = "DF_API_KEY" AND (value IS NOT NULL OR value <> "")');
        $return = (($result) ? 'OK' : 'KO');

        return ($text) ? $return : $result;
    }

    protected function haveHashId($return_hash = false)
    {
        foreach (Language::getLanguages(false, null) as $lang) {
            $currencies = Currency::getCurrencies();
            foreach ($currencies as $cur) {
                $currency_iso = Tools::strtoupper($cur['iso_code']);
                $field_name_iso = 'DF_HASHID_' . $currency_iso . '_' . Tools::strtoupper($lang['iso_code']);
                $hashId = Configuration::get($field_name_iso);
                if ($hashId && $hashId != null && $hashId != '') {
                    return $return_hash ? $hashId : true;
                }
            }
        }

        return false;
    }

    protected function createInstallationID($shopHashes, $defaultHash)
    {
        require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';
        $client = new EasyREST();
        $api_endpoint_installationid = 'https://admin.doofinder.com/plugins/script/prestashop';
        $apikey = Configuration::get('DF_API_KEY');
        $apikey = explode('-', $apikey)[1];

        $shRequest = '{
            "config": {
                "defaults": ' . json_encode($defaultHash) . ',
                "search_engines": ' . json_encode($shopHashes) . '
            }
        }';

        $shResponse = $client->post(
            $api_endpoint_installationid,
            $shRequest,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $apikey]
        );

        $shData = json_decode($shResponse->response, true);

        $script = $shData['script'];
        if (preg_match('/installationId:\s\'(.*)\'/', $script, $matches)) {
            $installationID = $matches[1];
        }

        return $installationID;
    }

    protected function alternateLayoutStateInDoofinder($hashId, $state)
    {
        require_once _PS_MODULE_DIR_ . 'doofinder/lib/EasyREST.php';
        $client = new EasyREST();
        $api_endpoint = 'https://admin.doofinder.com/plugins/state/prestashop';
        $apikey = Configuration::get('DF_API_KEY');
        $apikey = explode('-', $apikey)[1];

        $request = '{
            "search_engine": "' . $hashId . '",
            "state": ' . ($state ? 'true' : 'false') . '
        }';

        $client->post(
            $api_endpoint,
            $request,
            false,
            false,
            'application/json',
            ['Authorization: Token ' . $apikey]
        );
    }

    protected function getIsoCodeById($id)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
            SELECT `iso_code` FROM ' . _DB_PREFIX_ . 'currency WHERE `id_currency` = ' . (int) $id
        );
    }

    protected function getLanguageCode($code)
    {
        // $code is in the form of 'xx-YY' where xx is the language code
        // and 'YY' a country code identifying a variant of the language.
        $lang_country = explode('-', $code);

        return $lang_country[0];
    }

    protected function getHashId($id_lang, $id_currency)
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
}
