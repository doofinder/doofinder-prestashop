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
    require_once(dirname(__FILE__) . '/lib/dfTools.class.php');
}

//use \PrestaShop\PrestaShop\Core\Module\WidgetInterface;
if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
    require_once implode(DIRECTORY_SEPARATOR, array(
                dirname(__FILE__), 'src', 'DoofinderProductSearchProvider.php',
    ));

    require_once implode(DIRECTORY_SEPARATOR, array(
                dirname(__FILE__), 'src', 'DoofinderRangeAggregator.php',
    ));
}

if (!defined('_PS_VERSION_')) {
    exit;
}

class Doofinder extends Module
{

    protected $html = '';
    protected $postErrors = array();
    protected $productLinks = array();
    public $ps_layered_full_tree = true;
    public $searchBanner = false;

    const GS_SHORT_DESCRIPTION = 1;
    const GS_LONG_DESCRIPTION = 2;
    const VERSION = '3.0.1';
    const YES = 1;
    const NO = 0;

    public function __construct()
    {
        $this->name = 'doofinder';
        $this->tab = 'search_filter';
        $this->version = '3.0.0';
        $this->author = 'Doofinder (http://www.doofinder.com)';
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.7');
        $this->module_key = 'd1504fe6432199c7f56829be4bd16347';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Doofinder');
        $this->description = $this->l('Install Doofinder in your shop with no effort');

        $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service');
    }

    public function manualOverride()
    {
        $msg = $this->displayConfirmationCtm($this->l('Override installed sucessfully!'));
        $originFile = dirname(__FILE__) . '/lib/SearchController.php';
        $destFile = dirname(__FILE__) . '/override/controllers/front/SearchController.php';

        if (file_exists($originFile)) {
            if (!file_exists($destFile)) {
                copy($originFile, $destFile);
                // Install overrides
                try {
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
        if (file_exists(dirname(__FILE__) . '/override/controllers/front/SearchController.php')) {
            unlink(dirname(__FILE__) . '/override/controllers/front/SearchController.php');
        }
        $msgErrorColumn = 'This module need to be hooked in a column and your '
                . 'theme does not implement one if you want Search Facets';
        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true &&
                version_compare(_PS_VERSION_, '1.7', '<') === true) {
            // Hook the module either on the left or right column
            $theme = new Theme(Context::getContext()->shop->id_theme);
            if ((!$theme->default_left_column || !$this->registerHook('displayLeftColumn')) &&
                    (!$theme->default_right_column || !$this->registerHook('displayRightColumn'))) {
                $this->_errors[] = $this->l($msgErrorColumn);
            }
        } elseif (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
            if ((!$this->registerHook('displayLeftColumn')) && (!$this->registerHook('displayRightColumn'))) {
                $this->_errors[] = $this->l($msgErrorColumn);
            }
            $this->registerHook('productSearchProvider');
        } else {
            $this->registerHook('displayLeftColumn');
        }

        if (!parent::install() ||
                !$this->registerHook('header') ||
                !$this->registerHook('displayHeader') ||
                !$this->registerHook('displayFooter') ||
                !$this->registerHook('displayMobileTopSiteMap')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('DOOFINDER_LIVE_MODE');

        return parent::uninstall();
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
        $this->context->smarty->assign('oldPS', $oldPS);
        $this->context->smarty->assign('module_dir', $this->_path);
        $output.= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        $output.= $this->renderFormDataFeed();
        $output.= $this->renderFormSearchLayer();
        $output.= $this->renderFormInternalSearch();
        $output.= $this->renderFormCustomCSS();
        if ($adv) {
            $output.= $this->renderFormAdvanced();
        }
        $output.= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure_footer.tpl');

        return $output;
    }

    protected function renderFeedURLs()
    {
        $doofinder_hash = Configuration::get('DF_FEED_HASH');
        $enable_hash = Configuration::get('DF_ENABLE_HASH');
        $urls = array();
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            $currCfg = 'DF_GS_CURRENCY_' . Tools::strtoupper($lang['iso_code']);
            $currencyIso = Configuration::get($currCfg);
            $url = $this->context->shop->getBaseURL(true, false)
                    . $this->_path
                    . 'feed.php?language=' . Tools::strtoupper($lang['iso_code'])
                    . "&currency=" . Tools::strtoupper($currencyIso);
            if (!empty($doofinder_hash) && $enable_hash) {
                $url.='&dfsec_hash=' . $doofinder_hash;
            }
            $urls[] = array(
                'url' => $url,
                'lang' => Tools::strtoupper($lang['iso_code'])
            );
        }
        $this->context->smarty->assign('df_feed_urls', $urls);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/feed_url_partial_tab.tpl');
    }

    protected function renderFormCustomCSS()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        //$helper->submit_action = 'submitDoofinderModuleCustomCSS';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValuesCustomCSS(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        $html = '<div class="tab-pane" id="custom_css_tab">';
        $html.= $helper->generateForm(array($this->getConfigFormCustomCSS()));
        $html.= '</div>';
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
        //helper->submit_action = 'submitDoofinderModuleAdvanced';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&adv=1&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValuesAdvanced(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        $html = '<div class="tab-pane" id="advanced_tab">';
        $html.= $helper->generateForm(array($this->getConfigFormAdvanced()));
        $html.= '</div>';
        return $html;
    }

    protected function renderFormSearchLayer()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        //$helper->submit_action = 'submitDoofinderModuleSearchLayer';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValuesSearchLayer(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        $html = '<div class="tab-pane" id="search_layer_tab">';
        $html.= $helper->generateForm(array($this->getConfigFormSearchLayer()));
        $html.= '</div>';
        return $html;
    }

    protected function renderFormInternalSearch()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        //$helper->submit_action = 'submitDoofinderModuleInternalSearch';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValuesInternalSearch(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );


        $id_shop = 1;
        $errorsMsg = '';
        if (method_exists($this->context->shop, 'getContextShopID')) {
            $id_shop = $this->context->shop->getContextShopID();
        }
        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            $facets_enabled = Configuration::get('DF_OWSEARCHFAC');
            if ($facets_enabled) {
                if (!$this->isRegisteredInHookInShop('displayLeftColumn', $id_shop) &&
                        !$this->isRegisteredInHookInShop('displayRightColumn', $id_shop)) {
                    $link = $this->context->link->getAdminLink('AdminModulesPositions');
                    $msg = $this->l('You must hook Doofinder on displayLeftColumn or displayRightColumn');
                    $errorsMsg .= $this->displayErrorCtm($msg, $link);
                }
            }
        } else {
            if (!$this->isRegisteredInHookInShop('productSearchProvider', $id_shop)) {
                $link = $this->context->link->getAdminLink('AdminModulesPositions');
                $msg = $this->l('You must hook your module on productSearchProvider');
                $errorsMsg .= $this->displayErrorCtm($msg, $link);
            }
        }

        $html = '<div class="tab-pane" id="internal_search_tab">';
        $html.= $errorsMsg;
        $html.= $helper->generateForm(array($this->getConfigFormInternalSearch()));
        $html.= '</div>';
        return $html;
    }

    protected function renderFormDataFeed()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        //$helper->submit_action = 'submitDoofinderModuleDataFeed';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValuesDataFeed(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        $html = '<div class="tab-pane active" id="data_feed_tab">';
        $html.= $this->renderFeedURLs();
        $html.= $helper->generateForm(array($this->getConfigFormDataFeed()));
        $html.= $this->renderFormDataFeedCurrency();
        $html.= '</div>';
        return $html;
    }

    protected function renderFormDataFeedCurrency()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        //$helper->submit_action = 'submitDoofinderModuleDataFeedCurrency';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValuesDataFeedCurrency(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm(array($this->getConfigFormDataFeedCurrency()));
    }

    protected function getConfigFormDataFeedCurrency()
    {
        $form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Data Feed Currencies'),
                    'icon' => 'icon-dolar-sign',
                ),
                'submit' => array(
                    'title' => $this->l('Save Data Feed Currency Options'),
                    'name' => 'submitDoofinderModuleDataFeedCurrency'
                ),
            ),
        );

        $optname = 'DF_GS_CURRENCY_';
        $currencies = dfTools::getAvailableCurrencies();
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            $realoptname = $optname . Tools::strtoupper($lang['iso_code']);
            $form['form']['input'][] = array(
                'label' => sprintf($this->l("Currency for %s"), $lang['name']),
                'type' => 'select',
                'options' => array(
                    'query' => $currencies,
                    'id' => 'iso_code',
                    'name' => 'name',
                ),
                'name' => $realoptname,
                'required' => true,
            );
        }
        return $form;
    }

    protected function getBooleanFormValue()
    {
        $option = array(
            array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Enabled')
            ),
            array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Disabled')
            )
        );
        return $option;
    }

    protected function getConfigFormCustomCSS()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Custom CSS'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Extra CSS'),
                        'name' => 'DF_EXTRA_CSS',
                        'desc' => $this->l('Extra CSS to adjust Doofinder to your template'),
                        'cols' => 100,
                        'rows' => 10,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Custom CSS Options'),
                    'name' => 'submitDoofinderModuleCustomCSS'
                ),
            ),
        );
    }

    protected function getConfigFormSearchLayer()
    {
        $descHashid = 'Search Engine ID. If you need customize your JS, you must do it on your Doofinder account. '
                . 'Also you must fill this field to have Internal Search';
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Search Layer'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Doofinder Search Engine ID'),
                        'name' => 'DF_HASHID',
                        'desc' => $this->l($descHashid),
                        'lang' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Layer Widget Options'),
                    'name' => 'submitDoofinderModuleSearchLayer'
                ),
            ),
        );
    }

    protected function getConfigFormInternalSearch()
    {
        $descAppendBanner = 'Need to write "jQuery" identifier where to append after the banner.'
            . ' If empty or not valid, not banner will show. Example: "#content-wrapper #main h2"';
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Internal Search'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Overwrite Search page with Doofinder results'),
                        'name' => 'DF_OWSEARCH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Enable facets on Overwrite Search Page'),
                        'name' => 'DF_OWSEARCHFAC',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Doofinder Api Key'),
                        'name' => 'DF_API_KEY',
                        'desc' => $this->l('Api Key, needed to overwrite Search page'),
                    ),
                    array(
                        'col' => 9,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-code"></i>',
                        'desc' => $this->l($descAppendBanner),
                        'name' => 'DF_APPEND_BANNER',
                        'label' => $this->l('"Append after" banner on Overwrite Search Page'),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-code"></i>',
                        'desc' => $this->l('Optional. Default empty. Only if you have another "query input"'),
                        'name' => 'DF_SEARCH_SELECTOR',
                        'label' => $this->l('Query Input Selector'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Custom attribute separator'),
                        'name' => 'DF_CUSTOMEXPLODEATTR',
                        'desc' => $this->l('Optional. Used if you have a custom data feed'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Internal Search Options'),
                    'name' => 'submitDoofinderModuleInternalSearch'
                ),
            ),
        );
    }

    protected function getConfigFormDataFeed()
    {
        $descEnableHash = 'If you use this, please be sure to update your feed URL on doofinder panel';
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Data Feed'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Enable security hash on feed URL'),
                        'name' => 'DF_ENABLE_HASH',
                        'is_bool' => true,
                        'desc' => $this->l($descEnableHash),
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Display Prices in Data Feed'),
                        'name' => 'DF_GS_DISPLAY_PRICES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Display Prices With Taxes'),
                        'name' => 'DF_GS_PRICES_USE_TAX',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Export full categories path in the feed'),
                        'name' => 'DF_FEED_FULL_PATH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Include product variations in feed'),
                        'name' => 'DF_SHOW_PRODUCT_VARIATIONS',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id' => '0',
                                    'name' => $this->l('No, only product')
                                ),
                                array(
                                    'id' => '1',
                                    'name' => $this->l('Yes, Include each variations')
                                ),
                                array(
                                    'id' => '2',
                                    'name' => $this->l('Only product but all possible attribute for them')
                                ),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Attribute Groups'),
                        'name' => 'DF_GROUP_ATTRIBUTES_SHOWN',
                        'multiple' => true,
                        'options' => array(
                            'query' => AttributeGroup::getAttributesGroups(Context::getContext()->language->id),
                            'id' => 'id_attribute_group',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Include product features in feed'),
                        'name' => 'DF_SHOW_PRODUCT_FEATURES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Select features will be shown in feed'),
                        'name' => 'DF_FEATURES_SHOWN',
                        'multiple' => true,
                        'options' => array(
                            'query' => Feature::getFeatures(
                                Context::getContext()->language->id,
                                $this->context->shop->id
                            ),
                            'id' => 'id_feature',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Product Image Size'),
                        'name' => 'DF_GS_IMAGE_SIZE',
                        'options' => array(
                            'query' => dfTools::getAvailableImageSizes(),
                            'id' => 'DF_GS_IMAGE_SIZE',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Product Description Length'),
                        'name' => 'DF_GS_DESCRIPTION_TYPE',
                        'options' => array(
                            'query' => array(
                                array(
                                    'DF_GS_DESCRIPTION_TYPE' => self::GS_SHORT_DESCRIPTION,
                                    'name' => $this->l('Short')
                                ),
                                array(
                                    'DF_GS_DESCRIPTION_TYPE' => self::GS_LONG_DESCRIPTION,
                                    'name' => $this->l('Long')
                                ),
                            ),
                            'id' => 'DF_GS_DESCRIPTION_TYPE',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('MPN Field for Data Feed'),
                        'name' => 'DF_GS_MPN_FIELD',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id' => 'reference',
                                    'name' => 'reference'
                                ),
                                array(
                                    'id' => 'supplier_reference',
                                    'name' => 'supplier_reference'
                                ),
                                array(
                                    'id' => 'ean13',
                                    'name' => 'ean13'
                                ),
                                array(
                                    'id' => 'upc',
                                    'name' => 'upc'
                                ),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Data Feed Options'),
                    'name' => 'submitDoofinderModuleDataFeed'
                ),
            ),
        );
    }

    protected function getConfigFormAdvanced()
    {
        $descHttpsCurl = 'If your server have an untrusted certificate and you have '
            . 'connection problems with the API, please enable this';
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Advanced Options'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Debug Mode. Write info logs in doofinder.log file'),
                        'name' => 'DF_DEBUG',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable doofinder-links.js'),
                        'name' => 'DF_DSBL_DFLINK_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable doofinder-pagination(_15).js'),
                        'name' => 'DF_DSBL_DFPAG_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable doofinder_facets.js'),
                        'name' => 'DF_DSBL_DFFAC_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable js.cookie.js'),
                        'name' => 'DF_DSBL_DFCKIE_JS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('CURL disable HTTPS check'),
                        'name' => 'DF_DSBL_HTTPS_CURL',
                        'desc' => $this->l($descHttpsCurl),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Debug CURL error response'),
                        'name' => 'DF_DEBUG_CURL',
                        'desc' => $this->l('To debug if your server has symptoms of connection problems'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                    array(
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->l('Disable facets cache'),
                        'name' => 'DF_DSBL_FAC_CACHE',
                        'desc' => $this->l('Caution. This increment API requests'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Internal Search Options'),
                    'name' => 'submitDoofinderModuleAdvanced'
                ),
            ),
        );
    }

    protected function getConfigFormValuesAdvanced()
    {
        return array(
            'DF_DEBUG' => Configuration::get('DF_DEBUG'),
            'DF_DSBL_DFLINK_JS' => Configuration::get('DF_DSBL_DFLINK_JS'),
            'DF_DSBL_DFPAG_JS' => Configuration::get('DF_DSBL_DFPAG_JS'),
            'DF_DSBL_DFFAC_JS' => Configuration::get('DF_DSBL_DFFAC_JS'),
            'DF_DSBL_DFCKIE_JS' => Configuration::get('DF_DSBL_DFCKIE_JS'),
            'DF_DSBL_HTTPS_CURL' => Configuration::get('DF_DSBL_HTTPS_CURL'),
            'DF_DEBUG_CURL' => Configuration::get('DF_DEBUG_CURL'),
            'DF_DSBL_FAC_CACHE' => Configuration::get('DF_DSBL_FAC_CACHE'),
        );
    }

    protected function getConfigFormValuesCustomCSS()
    {
        return array(
            'DF_EXTRA_CSS' => Configuration::get('DF_EXTRA_CSS'),
        );
    }

    protected function getConfigFormValuesDataFeed()
    {
        return array(
            'DF_ENABLE_HASH' => Configuration::get('DF_ENABLE_HASH'),
            'DF_GS_DISPLAY_PRICES' => Configuration::get('DF_GS_DISPLAY_PRICES'),
            'DF_GS_PRICES_USE_TAX' => Configuration::get('DF_GS_PRICES_USE_TAX'),
            'DF_FEED_FULL_PATH' => Configuration::get('DF_FEED_FULL_PATH'),
            'DF_SHOW_PRODUCT_VARIATIONS' => Configuration::get('DF_SHOW_PRODUCT_VARIATIONS'),
            'DF_GROUP_ATTRIBUTES_SHOWN[]' => explode(',', Configuration::get('DF_GROUP_ATTRIBUTES_SHOWN')),
            'DF_SHOW_PRODUCT_FEATURES' => Configuration::get('DF_SHOW_PRODUCT_FEATURES'),
            'DF_FEATURES_SHOWN[]' => explode(',', Configuration::get('DF_FEATURES_SHOWN')),
            'DF_GS_IMAGE_SIZE' => Configuration::get('DF_GS_IMAGE_SIZE'),
            'DF_GS_DESCRIPTION_TYPE' => Configuration::get('DF_GS_DESCRIPTION_TYPE'),
            'DF_GS_MPN_FIELD' => Configuration::get('DF_GS_MPN_FIELD'),
        );
    }

    protected function getConfigFormValuesSearchLayer($update = false)
    {
        $fields = array();
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            $field_name = 'DF_HASHID_' . $lang['id_lang'];
            $field_name_iso = 'DF_HASHID_' . Tools::strtoupper($lang['iso_code']);
            if ($update) {
                $fields[$field_name] = array(
                    'real_config' => $field_name_iso,
                    'value' => Configuration::get($field_name)
                );
            } else {
                $fields['DF_HASHID'][$lang['id_lang']] = Configuration::get($field_name_iso);
            }
        }
        return $fields;
    }

    protected function getConfigFormValuesInternalSearch()
    {
        $fields = array(
            'DF_OWSEARCH' => Configuration::get('DF_OWSEARCH'),
            'DF_OWSEARCHFAC' => Configuration::get('DF_OWSEARCHFAC'),
            'DF_API_KEY' => Configuration::get('DF_API_KEY'),
            'DF_APPEND_BANNER' => Configuration::get('DF_APPEND_BANNER'),
            'DF_SEARCH_SELECTOR' => Configuration::get('DF_SEARCH_SELECTOR'),
            'DF_CUSTOMEXPLODEATTR' => Configuration::get('DF_CUSTOMEXPLODEATTR'),
        );

        return $fields;
    }

    protected function getConfigFormValuesDataFeedCurrency()
    {
        $default_currency = Currency::getDefaultCurrency();
        $configValues = array();

        $optname = 'DF_GS_CURRENCY_';
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            $realoptname = $optname . Tools::strtoupper($lang['iso_code']);
            $value = Configuration::get($realoptname);
            $configValues[$realoptname] = (($value) ? $value : $default_currency->iso_code);
        }
        return $configValues;
    }

    protected function postProcess()
    {
        $form_values = array();
        $formUpdated = '';
        $messages = '';
        if (((bool) Tools::isSubmit('submitDoofinderModuleDataFeed')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesDataFeed());
            $formUpdated = 'data_feed_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleDataFeedCurrency')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesDataFeedCurrency());
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
        if (((bool) Tools::isSubmit('submitDoofinderModuleCustomCSS')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesCustomCSS());
            $formUpdated = 'custom_css_tab';
        }
        if (((bool) Tools::isSubmit('submitDoofinderModuleAdvanced')) == true) {
            $form_values = array_merge($form_values, $this->getConfigFormValuesAdvanced());
            $formUpdated = 'advanced_tab';
            $messages.= $this->testDoofinderApi();
            $this->context->smarty->assign('adv', 1);
        }

        foreach (array_keys($form_values) as $key) {
            $postKey = str_replace(array('[', ']'), '', $key);
            $value = Tools::getValue($postKey);
            if (isset($form_values[$key]['real_config'])) {
                $postKey = $form_values[$key]['real_config'];
            }
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $value = trim($value);
            $html = false;
            if (strpos($postKey, 'DOOFINDER_SCRIPT_') !== false) {
                $html = true;
                $value = str_replace('type="text/javascript"', '', $value);
            }
            Configuration::updateValue($postKey, $value, $html);
        }

        $doofinder_hash = Configuration::get('DF_FEED_HASH');
        $enable_hash = Configuration::get('DF_ENABLE_HASH');
        if (empty($doofinder_hash)) {
            if ($enable_hash) {
                $doofinder_hash = Tools::encrypt('PrestaShop_Doofinder_' . date('YmdHis'));
                Configuration::updateValue('DF_FEED_HASH', $doofinder_hash);
            }
        }
        $ovrSearch = Configuration::get('DF_OWSEARCH');
        if ($ovrSearch && $formUpdated == 'internal_search_tab' &&
                version_compare(_PS_VERSION_, '1.7', '<') === true) {
            $messages.= $this->manualOverride();
        }

        if ($formUpdated == 'data_feed_tab') {
            $msg = $this->l('IF YOU HAVE CHANGED ANYTHING IN YOUR DATA FEED SETTINGS, REMEMBER YOU MUST REPROCESS.');
            $messages .= $this->displayWarningCtm($msg);
            if (!empty($doofinder_hash) && $enable_hash) {
                $msg = $this->l('Be sure to update your feed URL on Doofinder with the new params');
                $messages .= $this->displayWarningCtm($msg);
            }
        }
        
        if ($formUpdated == 'custom_css_tab') {
            try {
                $extraCSS = Configuration::get('DF_EXTRA_CSS');
                $cssVS = (int)Configuration::get('DF_CSS_VS');
                $cssVS++;
                Configuration::updateValue('DF_CSS_VS', $cssVS);
                $file = 'doofinder_custom_'.$this->context->shop->id.'_vs_'.$cssVS.'.css';
                file_put_contents(dirname(__FILE__).'/views/css/'.$file, $extraCSS);
            } catch (Exception $e) {
                trigger_error('Doofinder Captured exception:'.$e->getMessage(), E_USER_WARNING);
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
        $lang = Tools::strtoupper($this->context->language->iso_code);
        $search_engine_id = Configuration::get('DF_HASHID_'.$lang);
        $script = Configuration::get("DOOFINDER_SCRIPT_" . $lang);
        $extra_css = Configuration::get('DF_EXTRA_CSS');
        $df_querySelector = Configuration::get('DF_SEARCH_SELECTOR');
        if (empty($df_querySelector)) {
            $df_querySelector = '#search_query_top';
        }
        $this->smarty->assign(array(
            'ENT_QUOTES' => ENT_QUOTES,
            'lang' => Tools::strtolower($lang),
            'script_html' => dfTools::fixScriptTag($script),
            'extra_css_html' => dfTools::fixStyleTag($extra_css),
            'productLinks' => $this->productLinks,
            'search_engine_id' => $search_engine_id,
            'self' => dirname(__FILE__),
            'df_another_params' => $params,
            'doofinder_search_selector' => $df_querySelector
        ));
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
        if (isset($this->context->controller->php_self) &&
                $this->context->controller->php_self == 'search') {
            $noCookieJS = Configuration::get('DF_DSBL_DFCKIE_JS');
            $noFacetsJS = Configuration::get('DF_DSBL_DFFAC_JS');
            $noPaginaJS = Configuration::get('DF_DSBL_DFPAG_JS');
            $noLinksJS = Configuration::get('DF_DSBL_DFLINK_JS');

            $overwrite_search = Configuration::get('DF_OWSEARCH', null);
            $overwrite_facets = Configuration::get('DF_OWSEARCHFAC', null);
            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                if ($overwrite_search && $overwrite_facets) {
                    $css_path = str_replace('doofinder', 'blocklayered', $this->_path);
                    if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
                        if (!$noPaginaJS) {
                            $this->context->controller->addJS(($this->_path) . 'views/js/doofinder-pagination.js');
                        }
                        if (file_exists(_PS_MODULE_DIR_ . 'blocklayered/blocklayered.css')) {
                            $this->context->controller->addCSS(
                                $css_path . 'blocklayered.css',
                                'all'
                            );
                        } else {
                            $this->context->controller->addCSS(
                                ($this->_path) . 'views/css/doofinder-filters.css',
                                'all'
                            );
                        }
                    } else {
                        if (!$noPaginaJS) {
                            $this->context->controller->addJS(
                                ($this->_path) . 'views/js/doofinder-pagination_15.js'
                            );
                        }
                        if (file_exists(_PS_MODULE_DIR_ . 'blocklayered/blocklayered-15.css')) {
                            $this->context->controller->addCSS($css_path . 'blocklayered-15.css', 'all');
                        } else {
                            $this->context->controller->addCSS(
                                ($this->_path) . 'views/css/doofinder-filters-15.css',
                                'all'
                            );
                        }
                    }
                    if (!$noFacetsJS) {
                        $this->context->controller->addJS(($this->_path) . 'views/js/doofinder_facets.js');
                    }
                }
                if (!$noCookieJS) {
                    $this->context->controller->addJS(($this->_path) . 'views/js/js.cookie.js');
                }
                $this->context->controller->addJQueryUI('ui.slider');
                $this->context->controller->addJQueryUI('ui.accordion');
                $this->context->controller->addJqueryPlugin('multiaccordion');
                $this->context->controller->addJQueryUI('ui.sortable');
                $this->context->controller->addJqueryPlugin('jscrollpane');
                $this->context->controller->addJQueryPlugin('scrollTo');
            }
            if (!$noLinksJS) {
                $this->context->controller->addJS(($this->_path) . 'views/js/doofinder-links.js');
            }
            $appendTo = Configuration::get('DF_APPEND_BANNER');
            if ($appendTo) {
                $this->context->controller->addJS(($this->_path) . 'views/js/doofinder-banner.js');
            }
        }
        $cssVS = (int)Configuration::get('DF_CSS_VS');
        $file = 'doofinder_custom_'.$this->context->shop->id.'_vs_'.$cssVS.'.css';
        if (file_exists(dirname(__FILE__).'/views/css/'.$file)) {
            $this->context->controller->addCSS(
                ($this->_path) . 'views/css/'.$file,
                'all'
            );
        }
        return $this->display(__FILE__, 'views/templates/front/script.tpl');
    }

    public function hookDisplayFooter($params)
    {
        if (isset($this->context->controller->php_self) &&
                $this->context->controller->php_self == 'search') {
            $appendTo = Configuration::get('DF_APPEND_BANNER');
            if (!empty($this->searchBanner) && !empty($appendTo)) {
                $this->context->smarty->assign(array(
                    'doofinder_banner_image' => $this->searchBanner['image'],
                    'doofinder_banner_blank' => $this->searchBanner['blank'],
                    'doofinder_banner_id' => $this->searchBanner['id'],
                    'doofinder_banner_link' => $this->searchBanner['link'],
                ));
            }
            return $this->display(__FILE__, 'views/templates/hook/footer.tpl');
        }
        return false;
    }

    public function hookDisplayLeftColumn($params)
    {
        if (isset($this->context->controller->php_self) &&
                $this->context->controller->php_self == 'search') {
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
            $p = abs((int) (Tools::getValue('p', 1)));
            $n = abs((int) (Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
            $filters = Tools::getValue('filters', null);
            if (($search = $this->searchOnApi($query, $p, $n, 8000, $filters, true)) &&
                    $query &&
                    !is_array($query)) {
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

            $translate = array();
            $translate['price'] = $this->l('price');
            $translate['weight'] = $this->l('weight');

            $this->context->smarty->assign($filter_block);
            $this->context->smarty->assign(array(
                'hide_0_values' => Configuration::get('PS_LAYERED_HIDE_0_VALUES'),
                'blocklayeredSliderName' => $translate,
                'col_img_dir' => _PS_COL_IMG_DIR_
            ));
            return $this->display(__FILE__, 'views/templates/front/doofinder_facets.tpl');
        } else {
            return false;
        }
    }

    public function getFilterBlock($facets, $filters, $query_name)
    {
        $optionsDoofinder = $this->getDoofinderTermsOptions(false);

        $r_facets = array();
        $t_facets = array();
        if (isset($optionsDoofinder['facets'])) {
            foreach ($optionsDoofinder['facets'] as $f_values) {
                $r_facets[$f_values['name']] = $f_values['label'];
                $t_facets[$f_values['name']] = $f_values['type'];
            }
        }

        //Reorder filter block as doofinder dashboard
        $facetsBlock = array();
        foreach ($r_facets as $key_o => $value_o) {
            $facetsBlock[$key_o] = $facets[$key_o];
            $this->multiRenameKey(
                $facetsBlock[$key_o]['terms']['buckets'],
                array("key", "doc_count"),
                array("term", "count")
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
                $facetsBlock[$key_o]['ranges'][0] = array(
                    'from' => $facets[$key_o]['range']['buckets'][0]['from'],
                    'count' => $facets[$key_o]['range']['buckets'][0]['doc_count'],
                    'min' => floor($facets[$key_o]['range']['buckets'][0]['stats']['min']),
                    'max' => ceil($facets[$key_o]['range']['buckets'][0]['stats']['max']),
                    'total_count' => $facets[$key_o]['range']['buckets'][0]['stats']['count'],
                    'total' => $facets[$key_o]['range']['buckets'][0]['stats']['sum'],
                    'mean' => $facets[$key_o]['range']['buckets'][0]['stats']['avg'],
                    'selected_from' => false,
                    'selected_to' => false,
                );
            }
        }
        $facets = $facetsBlock;

        return array('options' => $r_facets,
            'facets' => $facets,
            'filters' => $filters,
            'nbr_filterBlocks' => 1,
            'df_query_name' => $query_name);
    }

    public function getSelectedFilters()
    {
        $options = $this->getDoofinderTermsOptions();

        $filters = array();
        $option_keys = array_keys($options);
        foreach ($option_keys as $key) {
            if ($selected = Tools::getValue('layered_terms_' . $key, false)) {
                $filters[$key] = $selected;
            } elseif ($selected = Tools::getValue('layered_' . $key . '_slider', false)) {
                $selected = explode('_', $selected);
                $filters[$key] = array(
                    'from' => $selected[0],
                    'to' => $selected[1]
                );
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
        $pages_nb = ceil($nb_products / (int) ($n));

        $start = (int) ($p - $range);
        if ($start < 1) {
            $start = 1;
        }

        $stop = (int) ($p + $range);
        if ($stop > $pages_nb) {
            $stop = (int) ($pages_nb);
        }
    }

    public function ajaxCall()
    {

        $selected_filters = $this->getSelectedFilters();
        $_POST['filters'] = $selected_filters;


        $search = $this->generateSearch(true);
        $products = $search['result'];
        $p = abs((int) (Tools::getValue('p', 1)));
        $n = abs((int) (Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
        if (!$n) {
            $n = Configuration::get('PS_PRODUCTS_PER_PAGE');
        }

        // Add pagination variable
        $nArray = (int) Configuration::get('PS_PRODUCTS_PER_PAGE') != 10 ? array(
            (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
            10,
            20,
            50
        ) : array(10, 20, 50);
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
        //var_dump($search);
        $pages_nb = 0;
        $range = 0;
        $start = 0;
        $stop = 0;
        $this->getPaginationValues($nb_products, $p, $n, $pages_nb, $range, $start, $stop);
        $this->context->smarty->assign(
            array(
                'homeSize' => Image::getSize(ImageType::getFormatedName('home')),
                'nb_products' => $nb_products,
                'category' => $category,
                'pages_nb' => (int) $pages_nb,
                'p' => (int) $p,
                'n' => (int) $n,
                'range' => (int) $range,
                'start' => (int) $start,
                'stop' => (int) $stop,
                'n_array' => ((int) Configuration::get('PS_PRODUCTS_PER_PAGE') != 10) ? array(
                    (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
                    10,
                    20,
                    50
                ) : array(10, 20, 50),
                'comparator_max_item' => (int) (Configuration::get('PS_COMPARATOR_MAX_ITEM')),
                'products' => $products,
                'products_per_page' => (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
                'static_token' => Tools::getToken(false),
                'page_name' => 'search',
                'nArray' => $nArray,
                'compareProducts' => CompareProduct::getCompareProducts((int) $this->context->cookie->id_compare)
            )
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
        $filter_block = array('current_friendly_url' => '');
        $vars = array(
            //'filtersBlock' => utf8_encode($this->generateFiltersBlock($search['facets'],$search['filters'])),
            'productList' => utf8_encode($product_list),
            'pagination' => $this->context->smarty->fetch(_PS_THEME_DIR_ . 'pagination.tpl'),
            'categoryCount' => $category_count,
            'meta_title' => $meta_title . ' - ' . Configuration::get('PS_SHOP_NAME'),
            'heading' => $meta_title,
            'meta_keywords' => isset($meta_keywords) ? $meta_keywords : null,
            'meta_description' => $meta_description,
            'current_friendly_url' => ((int) $n == (int) $nb_products) ? '#/show-all' :
            '#' . $filter_block['current_friendly_url'],
            //'filters' => $filter_block['filters'],
            'nbRenderedProducts' => (int) $nb_products,
            'nbAskedProducts' => (int) $n
        );

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            $vars = array_merge($vars, array('pagination_bottom' => $this->context->smarty->assign(
                'paginationId',
                'bottom'
            )->fetch(_PS_THEME_DIR_ . 'pagination.tpl')));
        }
        // We are sending an array in jSon to the .js controller, it will update both
        //  the filters and the products zones
        return Tools::jsonEncode($vars);
    }

    //http://stackoverflow.com/a/17254761
    public function multiRenameKey(&$array, $old_keys, $new_keys)
    {
        if (!is_array($array)) {
            ($array == "") ? $array = array() : false;
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

    public function getSQLOnlyProductsWithAttributes()
    {
        $attr_groups = AttributeGroup::getAttributesGroups((int) Configuration::get('PS_LANG_DEFAULT'));
        $cfg_group_attributes_shown = explode(
            ',',
            dfTools::cfg(Context::getContext()->shop->id, 'DF_GROUP_ATTRIBUTES_SHOWN')
        );

        $sql_select_attributes = array();
        $sql_from_attributes = array();
        $sql_from_only = ' LEFT JOIN _DB_PREFIX_product_attribute pa ON'
                . ' (p.id_product = pa.id_product) LEFT JOIN _DB_PREFIX_product_attribute_combination pac'
                . ' ON (pa.id_product_attribute = pac.id_product_attribute) ';
        foreach ($attr_groups as $a_group) {
            if (isset($cfg_group_attributes_shown) &&
                    count($cfg_group_attributes_shown) > 0 &&
                    $cfg_group_attributes_shown[0] !== "" &&
                    !in_array($a_group['id_attribute_group'], $cfg_group_attributes_shown)) {
                continue;
            }
            $a_group_name = str_replace('-', '_', Tools::str2url($a_group['name']));
            $id_atg = (int)$a_group['id_attribute_group'];
            $sql_select_attributes[] = ' GROUP_CONCAT(DISTINCT REPLACE(pal_' . $id_atg
                    . '.name,\'/\',\'\/\/\') SEPARATOR \'/\') as attributes_' . $a_group_name;
            $sql_from_attributes[] = ' LEFT JOIN _DB_PREFIX_attribute pat_' . $id_atg
                    . ' ON (pat_' . $id_atg . '.id_attribute = pac.id_attribute'
                    . ' AND pat_' . $id_atg . '.id_attribute_group = ' . $id_atg . ' )'
                    . ' LEFT JOIN _DB_PREFIX_attribute_lang pal_' . $id_atg
                    . ' ON (pal_' . $id_atg . '.id_attribute = pat_' . $id_atg . '.id_attribute'
                    . ' AND pal_' . $id_atg . '.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . ') ';
        }

        $sql = "
            SELECT
              ps.id_product,
              __ID_CATEGORY_DEFAULT__,

              m.name AS manufacturer,
              
              IF(isnull(pa.id_product), p.__MPN__ , GROUP_CONCAT(DISTINCT pa.__MPN__ SEPARATOR '/')) AS mpn,
              IF(isnull(pa.id_product), p.ean13 , GROUP_CONCAT(DISTINCT pa.ean13 SEPARATOR '/')) AS ean13,
              p.ean13 AS simple_ean13,
              p.__MPN__ AS simple_mpn,
              pl.name,
              pl.description,
              pl.description_short,
              pl.meta_title,
              pl.meta_keywords,
              pl.meta_description,
              GROUP_CONCAT(DISTINCT tag.name SEPARATOR '/') AS tags,
              pl.link_rewrite,
              cl.link_rewrite AS cat_link_rew,

              im.id_image,

              p.available_for_order "
              . (count($sql_select_attributes) ? ',' . implode(',', $sql_select_attributes) : '') . "
            FROM
              _DB_PREFIX_product p
              INNER JOIN _DB_PREFIX_product_shop ps
                ON (p.id_product = ps.id_product AND ps.id_shop = _ID_SHOP_)
              LEFT JOIN _DB_PREFIX_product_lang pl
                ON (p.id_product = pl.id_product AND pl.id_shop = _ID_SHOP_ AND pl.id_lang = _ID_LANG_)
              LEFT JOIN _DB_PREFIX_manufacturer m
                ON (p.id_manufacturer = m.id_manufacturer)
              LEFT JOIN _DB_PREFIX_category_lang cl
                ON (p.id_category_default = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
              LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
                ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
              LEFT JOIN (_DB_PREFIX_tag tag INNER JOIN _DB_PREFIX_product_tag pt 
                ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
                ON (pt.id_product = p.id_product)
                " . (count($sql_from_attributes) ? $sql_from_only . implode(' ', $sql_from_attributes) : '') . "
            WHERE
              __IS_ACTIVE__
              __VISIBILITY__
            GROUP BY
              p.id_product
            ORDER BY
              p.id_product
          ";

        return $sql;
    }

    public function hookProductSearchProvider($params)
    {
        $query = $params['query'];
        if ($query->getSearchString()) {
            return new DoofinderProductSearchProvider($this);
        } else {
            return null;
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
			WHERE h.`name` = \'' . pSQL($hook) . '\''
                . ' AND hm.id_shop = ' . $id_shop . ' AND hm.`id_module` = ' . (int) $this->id;
        return Db::getInstance()->getValue($sql);
    }

    public function testDoofinderApi()
    {
        if (!class_exists('DoofinderApi')) {
            include_once dirname(__FILE__) . '/lib/doofinder_api.php';
        }
        $messages = '';
        foreach (Language::getLanguages(true, $this->context->shop->id) as $lang) {
            $hash_id = Configuration::get('DF_HASHID_' . Tools::strtoupper($lang['iso_code']));
            $api_key = Configuration::get('DF_API_KEY');
            $lang_iso = Tools::strtoupper($lang['iso_code']);
            if ($hash_id && $api_key) {
                try {
                    $df = new DoofinderApi($hash_id, $api_key, false, array('apiVersion' => '5'));
                    $dfOptions = $df->getOptions();
                    if ($dfOptions) {
                        $msg = 'Connection succesful for Search Engine - ';
                        $messages.= $this->displayConfirmationCtm($this->l($msg) . $lang_iso);
                    } else {
                        $msg = 'Error: no connection for Search Engine - ';
                        $messages.= $this->displayErrorCtm($this->l($msg) . $lang_iso);
                    }
                } catch (DoofinderException $e) {
                    $messages.= $this->displayErrorCtm($e->getMessage() . ' - Search Engine ' . $lang_iso);
                } catch (Exception $e) {
                    $msg = $e->getMessage() . ' - Search Engine ';
                    $messages.= $this->displayErrorCtm($msg . $lang_iso);
                }
            } else {
                $msg = 'Empty Api Key or empty Search Engine - ';
                $messages.= $this->displayWarningCtm($this->l($msg) . $lang_iso);
            }
        }
        return $messages;
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

        $hash_id = Configuration::get('DF_HASHID_' . Tools::strtoupper(Context::getContext()->language->iso_code));
        $api_key = Configuration::get('DF_API_KEY');
        if ($hash_id && $api_key) {
            try {
                $options = array();
                if (file_exists($cacheOptionsDoofinderFileName) &&
                        !$disableCache) {
                    $options = json_decode(Tools::file_get_contents($cacheOptionsDoofinderFileName), true);
                }
                if (empty($options)) {
                    if (!class_exists('DoofinderApi')) {
                        include_once dirname(__FILE__) . '/lib/doofinder_api.php';
                    }
                    $df = new DoofinderApi($hash_id, $api_key, false, array('apiVersion' => '5'));
                    $dfOptions = $df->getOptions();
                    if ($dfOptions) {
                        $options = json_decode($dfOptions, true);
                    }
                    if (isset($debug) && $debug) {
                        $this->debug("Options: " . var_export($dfOptions, true));
                    }
                    $jsonCacheOptionsDoofinder = json_encode($options);
                    file_put_contents($cacheOptionsDoofinderFileName, $jsonCacheOptionsDoofinder);
                }

                if ($only_facets) {
                    $facets = array();
                    $r_facets = array();
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
                    $this->debug("Exception:  " . $e->getMessage());
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
        $query_name = Tools::getValue('df_query_name', false);
        $debug = Configuration::get('DF_DEBUG');
        if (isset($debug) && $debug) {
            $this->debug('Search On API Start');
        }
        $lang_iso = Tools::strtoupper(Context::getContext()->language->iso_code);
        $hash_id = Configuration::get('DF_HASHID_' . $lang_iso, null);
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
                $df = new DoofinderApi($hash_id, $api_key, false, array('apiVersion' => '5'));
                $queryParams = array('rpp' => $page_size, // results per page
                    'timeout' => $timeout,
                    'types' => array(
                        'product',
                    ), 'transformer' => 'basic');
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
            $product_pool_attributes = array();
            $product_pool_ids = array();
            $customexplodeattr = Configuration::get('DF_CUSTOMEXPLODEATTR');
            foreach ($dfResultsArray as $entry) {
                if ($entry['type'] == 'product') {
                    if (!empty($customexplodeattr) && strpos($entry['id'], $customexplodeattr) !== false) {
                        $id_products = explode($customexplodeattr, $entry['id']);
                        $product_pool_attributes[] = $id_products[1];
                        $product_pool_ids[] = $id_products[0];
                    }
                    if (strpos($entry['id'], 'VAR-') === false) {
                        $product_pool_ids[] = $entry['id'];
                    } else {
                        $id_product_attribute = str_replace('VAR-', '', $entry['id']);
                        if (!in_array($id_product_attribute, $product_pool_attributes)) {
                            $product_pool_attributes[] = $id_product_attribute;
                        }
                        $id_product = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                            'SELECT id_product FROM ' . _DB_PREFIX_ . 'product_attribute'
                            . ' WHERE id_product_attribute = ' . $id_product_attribute
                        );
                        $product_pool_ids[] = ((!empty($id_product)) ? $id_product : 0 );
                    }
                }
            }
            $product_pool = implode(', ', $product_pool_ids);

            // To avoid SQL errors.
            if ($product_pool == "") {
                $product_pool = "0";
            }

            if (isset($debug) && $debug) {
                $this->debug("Product Pool: $product_pool");
            }

            $product_pool_attributes = implode(',', $product_pool_attributes);

            $context = Context::getContext();
            // Avoids SQL Error
            if ($product_pool_attributes == "") {
                $product_pool_attributes = "0";
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
                ' IF(pai.`id_image` IS NULL OR pai.`id_image` = 0, MAX(image_shop.`id_image`),pai.`id_image`)'
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
                    AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl') . ') '
                . (Combination::isFeatureActive() ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                    ON (p.`id_product` = pa.`id_product`)
                    ' . Shop::addSqlAssociation('product_attribute', 'pa', false, (($show_variations) ? '' :
                    ' product_attribute_shop.default_on = 1')) . '
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
                    . ' ON (i.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $id_lang . ') '
                    . ' WHERE p.`id_product` IN (' . $product_pool . ') ' .
                    (($show_variations) ? ' AND (product_attribute_shop.`id_product_attribute` IS NULL'
                        . ' OR product_attribute_shop.`id_product_attribute`'
                        . ' IN (' . $product_pool_attributes . ')) ' : '') .
                    ' GROUP BY product_shop.id_product '
                    . (($show_variations) ? ' ,  product_attribute_shop.`id_product_attribute` ' : '') .
                    ' ORDER BY FIELD (p.`id_product`,' . $product_pool . ') '
                    . (($show_variations) ? ' , FIELD (product_attribute_shop.`id_product_attribute`,'
                        . $product_pool_attributes . ')' : '');
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
                    $this->productLinks = array();

                    foreach ($result_properties as $rp) {
                        $this->productLinks[$rp['link']] = $rp['id_product'];
                    }
                } else {
                    $result_properties = $result;
                }
            }
            $this->searchBanner = $dfResults->getBanner();

            if ($return_facets) {
                return array(
                    'doofinder_results' => $dfResultsArray,
                    'total' => $dfResults->getProperty('total'),
                    'result' => $result_properties,
                    'facets' => $dfResults->getFacets(),
                    'filters' => $df->getFilters(),
                    'df_query_name' => $dfResults->getProperty('query_name')
                );
            }
            return array(
                'doofinder_results' => $dfResultsArray,
                'total' => $dfResults->getProperty('total'),
                'result' => $result_properties,
                'df_query_name' => $dfResults->getProperty('query_name')
            );
        } else {
            return false;
        }
    }

    public function getFormatedName($name)
    {
        $theme_name = Context::getContext()->shop->theme_name;
        $name_without_theme_name = str_replace(array('_' . $theme_name, $theme_name . '_'), '', $name);

        //check if the theme name is already in $name if yes only return $name
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

    public function displayErrorCtm($error, $link=false)
    {
        return $this->displayGeneralMsg($error, 'error', 'danger', $link);
    }

    public function displayWarningCtm($warning, $link=false)
    {
        return $this->displayGeneralMsg($warning, 'warning', 'warning', $link);
    }

    public function displayConfirmationCtm($string, $link=false)
    {
        return $this->displayGeneralMsg($string, 'confirmation', 'success', $link);
    }
    
    public function displayGeneralMsg($string, $type, $alert, $link=false)
    {
        $this->context->smarty->assign(
            array(
                'type_message' => $type,
                'type_alert' => $alert,
                'message' => $string,
                'link' => $link
            )
        );
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/display_msg.tpl');
    }
}
