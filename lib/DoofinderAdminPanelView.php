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

class DoofinderAdminPanelView
{
    /*
    Name an tab are required because they are using internally
    by Helper class (/classes/helper/Helper.php)
    */
    public $name;
    public $tab;
    private $module;

    public function __construct($module)
    {
        $this->name = $module->name;
        $this->tab = $module->tab;
        $this->module = $module;
    }

    /**
     * Handles the module's configuration page
     *
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $stop = $this->getWarningMultishopHtml();
        $context = \Context::getContext();
        if ($stop) {
            return $stop;
        }
        $adv = \Tools::getValue('adv', 0);

        $context->smarty->assign('adv', $adv);

        $formManager = new FormManager($this->module);
        $msg = $formManager->postProcess();

        $output = $msg;
        $oldPS = false;
        $context->controller->addJS($this->module->getPath() . 'views/js/admin-panel.js');

        if (_PS_VERSION_ < 1.6) {
            $oldPS = true;
            $context->controller->addJS($this->module->getPath() . 'views/js/plugins/bootstrap.min.js');
            $context->controller->addCSS($this->module->getPath() . 'views/css/admin-theme_15.css');
        }
        $configured = $this->isConfigured();
        $isNewShop = $this->showNewShopForm($context->shop);
        $shopId = null;
        if ($isNewShop) {
            $shopId = $context->shop->id;
        }

        $skipUrlParams = [
            'skip' => 1,
            'configure' => $this->module->name,
            'tab_module' => $this->module->tab,
            'module_name' => $this->module->name,
        ];
        $skipUrl = $context->link->getAdminLink('AdminModules', true);
        $separator = strpos($skipUrl, '?') === false ? '?' : '&';
        $skipUrl .= $separator . http_build_query($skipUrlParams);

        $redirect = $context->shop->getBaseURL(true, false) . $this->module->getPath() . 'config.php';
        $token = \Tools::encrypt($redirect);
        $paramsPopup = 'email=' . $context->employee->email . '&token=' . $token;
        $dfEnabledV9 = \Configuration::get('DF_ENABLED_V9');

        $context->smarty->assign('oldPS', $oldPS);
        $context->smarty->assign('module_dir', $this->module->getPath());
        $context->smarty->assign('configured', $configured);
        $context->smarty->assign('is_new_shop', $isNewShop);
        $context->smarty->assign('shop_id', $shopId);
        $context->smarty->assign('checkConnection', DoofinderConfig::checkOutsideConnection());
        $context->smarty->assign('tokenAjax', \Tools::encrypt('doofinder-ajax'));
        $context->smarty->assign('skipurl', $skipUrl);
        $context->smarty->assign('paramsPopup', $paramsPopup);
        $context->smarty->assign('dfEnabledV9', $dfEnabledV9);

        $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/configure.tpl');
        if ($configured) {
            $feedIndexed = \Configuration::get('DF_FEED_INDEXED', false);
            if (empty($feedIndexed)) {
                $controllerUrl = $context->link->getAdminLink('DoofinderAdmin', true) . '&ajax=1';
                $context->smarty->assign('update_feed_url', $controllerUrl . '&action=UpdateConfigurationField');
                $context->smarty->assign('check_feed_url', $controllerUrl . '&action=CheckConfigurationField');
                $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/indexation_status.tpl');
            }

            $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/configure_administration_panel.tpl');
            $output .= $this->renderFormDataFeed($adv);
            if ($adv) {
                $output .= $this->renderFormAdvanced();
            }
            $advUrl = $context->link->getAdminLink('AdminModules', true) . '&adv=1'
                . '&configure=' . $this->module->name . '&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
            $context->smarty->assign('adv_url', $advUrl);
        }

        $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/configure_footer.tpl');

        return $output;
    }

    public function getWarningMultishopHtml()
    {
        $stop = false;
        if (\Shop::getContext() == \Shop::CONTEXT_GROUP || \Shop::getContext() == \Shop::CONTEXT_ALL) {
            $context = \Context::getContext();
            $context->smarty->assign('text_one_shop', $this->module->l('You cannot manage Doofinder from a \'All Shops\' or a \'Group Shop\' context, select directly the shop you want to edit'));
            $stop = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/message_manage_one_shop.tpl');
        }

        return $stop;
    }

    public static function displayErrorCtm($error, $link = false, $raw = false)
    {
        return self::displayGeneralMsg($error, 'error', 'danger', $link, $raw);
    }

    public static function displayWarningCtm($warning, $link = false, $raw = false)
    {
        return self::displayGeneralMsg($warning, 'warning', 'warning', $link, $raw);
    }

    public static function displayConfirmationCtm($string, $link = false, $raw = false)
    {
        return self::displayGeneralMsg($string, 'confirmation', 'success', $link, $raw);
    }

    public static function displayGeneralMsg($string, $type, $alert, $link = false, $raw = false)
    {
        $context = \Context::getContext();
        $context->smarty->assign(
            [
                'd_type_message' => $type,
                'd_type_alert' => $alert,
                'd_message' => $string,
                'd_link' => $link,
                'd_raw' => $raw,
            ]
        );

        return $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/display_msg.tpl');
    }

    public static function getLocalPath()
    {
        return _PS_MODULE_DIR_ . DoofinderConstants::NAME . DIRECTORY_SEPARATOR;
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
        $installationId = \Configuration::get('DF_INSTALLATION_ID', null, (int) $shop->id_shop_group, (int) $shop->id);
        $multishopEnable = \Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE');
        $apiKey = \Configuration::get('DF_AI_APIKEY');

        return !$installationId && $multishopEnable && $apiKey;
    }

    /**
     * Check if the module has already been configured
     *
     * @return bool
     */
    protected function isConfigured()
    {
        $skip = \Tools::getValue('skip');
        if ($skip) {
            \Configuration::updateValue('DF_ENABLE_HASH', 0);
            \Configuration::updateValue('DF_ENABLED_V9', true);
        }
        $sql = 'SELECT id_configuration FROM ' . _DB_PREFIX_ . 'configuration WHERE name = \'DF_ENABLE_HASH\'';

        return \Db::getInstance()->getValue($sql);
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
        $helper = new \HelperForm();
        $context = \Context::getContext();
        $helper->show_toolbar = false;
        $helper->table = $this->module->getTable();
        $helper->module = $this->module;
        $helper->default_form_language = $context->language->id;
        $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->module->getIdentifier();
        // $helper->submit_action = 'submitDoofinderModuleDataFeed';
        $helper->currentIndex = $context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '')
            . '&configure=' . $this->module->name . '&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');

        $context->smarty->assign('id_tab', 'data_feed_tab');
        $html = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/pre_tab.tpl');
        // Data feed form
        $helper->tpl_vars = [
            'fields_value' => DoofinderConfig::getConfigFormValuesDataFeed(),
            'languages' => $context->controller->getLanguages(),
            'id_language' => $context->language->id,
        ];

        if (!$this->showNewShopForm(\Context::getContext()->shop)) {
            $validUpdateOnSave = UpdateOnSave::isValid();
            $html .= $helper->generateForm([$this->getConfigFormDataFeed($validUpdateOnSave)]);
            // Search layer form
            $helper->tpl_vars['fields_value'] = DoofinderConfig::getConfigFormValuesSearchLayer();
            $html .= $helper->generateForm([$this->getConfigFormSearchLayer()]);
        } else {
            $context->controller->warnings[] = $this->module->l("This shop is new and it hasn't been synchronized with Doofinder yet.");
        }
        $html .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
    }

    /**
     * Render the advanced configuration form
     *
     * @return string
     */
    protected function renderFormAdvanced()
    {
        $helper = new \HelperForm();
        $context = \Context::getContext();
        $helper->show_toolbar = false;
        $helper->table = $this->module->getTable();
        $helper->module = $this;
        $helper->default_form_language = $context->language->id;
        $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->module->getIdentifier();
        // helper->submit_action = 'submitDoofinderModuleAdvanced';
        $helper->currentIndex = $context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->module->name . '&adv=1&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => DoofinderConfig::getConfigFormValuesAdvanced(),
            'languages' => $context->controller->getLanguages(),
            'id_language' => $context->language->id,
        ];
        $context->smarty->assign('id_tab', 'advanced_tab');
        $html = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/pre_tab.tpl');
        $html .= $this->renderFeedURLs();
        $html .= $helper->generateForm([$this->getConfigFormAdvanced()]);
        $html .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/after_tab.tpl');

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
        $context = \Context::getContext();
        $languages = \Language::getLanguages(true, $context->shop->id);
        foreach ($languages as $lang) {
            foreach (\Currency::getCurrencies() as $cur) {
                $currencyIso = \Tools::strtoupper($cur['iso_code']);
                $langIso = \Tools::strtoupper($lang['iso_code']);
                $urls[] = [
                    'url' => UrlManager::getFeedUrl($context->shop->id, $langIso, $currencyIso),
                    'lang' => $langIso,
                    'currency' => $currencyIso,
                ];
            }
        }
        $context->smarty->assign('df_feed_urls', $urls);

        return $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/feed_url_partial_tab.tpl');
    }

    /**
     * Get the fields of the data feed configuration form
     *
     * @return array
     */
    protected function getConfigFormDataFeed($validUpdateOnSave = false)
    {
        $context = \Context::getContext();
        if ($validUpdateOnSave) {
            $disabled = false;
            $query = [
                5 => ['id' => 5, 'name' => sprintf($this->module->l('Each %s minutes'), '5')],
                15 => ['id' => 15, 'name' => sprintf($this->module->l('Each %s minutes'), '15')],
                30 => ['id' => 30, 'name' => sprintf($this->module->l('Each %s minutes'), '30')],
                60 => ['id' => 60, 'name' => $this->module->l('Each hour')],
                120 => ['id' => 120, 'name' => sprintf($this->module->l('Each %s hours'), '2')],
                360 => ['id' => 360, 'name' => sprintf($this->module->l('Each %s hours'), '6')],
                720 => ['id' => 720, 'name' => sprintf($this->module->l('Each %s hours'), '12')],
                1440 => ['id' => 1440, 'name' => $this->module->l('Once a day')],
                0 => ['id' => 0, 'name' => $this->module->l('Disabled')],
            ];
        } else {
            $disabled = true;
            $query = [
                0 => ['id' => 0, 'name' => $this->module->l('Disabled')],
            ];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Data Feed'),
                ],
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index product prices'),
                        'desc' => $this->module->l('If you activate this option you will be able to show the prices of each product in the search results.'),
                        'name' => 'DF_GS_DISPLAY_PRICES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Show product prices including taxes'),
                        'desc' => $this->module->l('If you activate this option, the price of the products that will be displayed will be inclusive of taxes.'),
                        'name' => 'DF_GS_PRICES_USE_TAX',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index the full path of the product category'),
                        'name' => 'DF_FEED_FULL_PATH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index product attribute combinations'),
                        'name' => 'DF_SHOW_PRODUCT_VARIATIONS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Define which combinations of product attributes you want to index for'),
                        'name' => 'DF_GROUP_ATTRIBUTES_SHOWN',
                        'multiple' => true,
                        'options' => [
                            'query' => \AttributeGroup::getAttributesGroups(\Context::getContext()->language->id),
                            'id' => 'id_attribute_group',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index customized product features'),
                        'name' => 'DF_SHOW_PRODUCT_FEATURES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Select features will be shown in feed'),
                        'name' => 'DF_FEATURES_SHOWN',
                        'multiple' => true,
                        'options' => [
                            'query' => \Feature::getFeatures(
                                $context->language->id,
                                $context->shop->id
                            ),
                            'id' => 'id_feature',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Product Image Size'),
                        'name' => 'DF_GS_IMAGE_SIZE',
                        'options' => [
                            'query' => DfTools::getAvailableImageSizes(),
                            'id' => 'DF_GS_IMAGE_SIZE',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Automatically process modified products'),
                        'desc' => $this->module->l('This action will only be executed if there are changes. If you see the field disabled, it is because you are making a usage in the indexes that is not supported by the automatic processing of modified products.'),
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
                    'title' => $this->module->l('Save Data Feed Options'),
                    'name' => 'submitDoofinderModuleDataFeed',
                ],
            ],
        ];
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
                'label' => $this->module->l('Doofinder search layer'),
                'name' => 'DF_SHOW_LAYER',
                'is_bool' => true,
                'values' => $this->getBooleanFormValue(),
            ],
            [
                'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                'label' => $this->module->l('Doofinder search layer in mobile version'),
                'name' => 'DF_SHOW_LAYER_MOBILE',
                'is_bool' => true,
                'values' => $this->getBooleanFormValue(),
            ],
            [
                'type' => 'text',
                'label' => $this->module->l('Doofinder Store ID'),
                'name' => 'DF_INSTALLATION_ID',
                'desc' => $this->module->l('INSTALLATION_ID_EXPLANATION'),
                'lang' => false,
            ],
        ];

        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Search Layer'),
                ],
                'input' => $inputs,
                'submit' => [
                    'title' => $this->module->l('Save Layer Widget Options'),
                    'name' => 'submitDoofinderModuleSearchLayer',
                ],
            ],
        ];
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
                    'title' => $this->module->l('Advanced Options'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Doofinder Api Key'),
                        'name' => 'DF_API_KEY',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Region'),
                        'name' => 'DF_REGION',
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Enable v9 layer (Livelayer)'),
                        'name' => 'DF_ENABLED_V9',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Debug Mode. Write info logs in doofinder.log file'),
                        'name' => 'DF_DEBUG',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('CURL disable HTTPS check'),
                        'name' => 'DF_DSBL_HTTPS_CURL',
                        'desc' => $this->module->l('CURL_DISABLE_HTTPS_EXPLANATION'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Debug CURL error response'),
                        'name' => 'DF_DEBUG_CURL',
                        'desc' => $this->module->l('To debug if your server has symptoms of connection problems'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save Internal Search Options'),
                    'name' => 'submitDoofinderModuleAdvanced',
                ],
            ],
        ];
    }

    private function getBooleanFormValue()
    {
        $option = [
            [
                'id' => 'active_on',
                'value' => true,
                'label' => $this->module->l('Enabled'),
            ],
            [
                'id' => 'active_off',
                'value' => false,
                'label' => $this->module->l('Disabled'),
            ],
        ];

        return $option;
    }
}
