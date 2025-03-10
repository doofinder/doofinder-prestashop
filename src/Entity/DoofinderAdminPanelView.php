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

class DoofinderAdminPanelView
{
    /*
    Name an tab are required because they are using internally
    by Helper class (/classes/helper/Helper.php)
    */
    public $name;
    public $tab;

    /**
     * Doofinder main module class object
     *
     * @var \Doofinder
     */
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
     * the `&first_time=1` parameter has been introduced for these purposes:
     * - It is displayed only once and is automatically removed upon form submission or
     *   module reopening.
     * - It ensures initial settings, such as enabling `multiprice` by default and removing indexation in progress popup.
     *   (See `FormManager` class, `postProcess` function)
     * - It prevents the indexing popup from appearing when essential data, such as
     *   `installation ID`, is missing, ensuring that indexing does not occur prematurely.
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
        $isModuleEnabledInShop = DfTools::isModuleEnabledInShop($context->shop->id);
        if (!$isModuleEnabledInShop) {
            return $this->getWarningModuleNotEnabledHtml();
        }

        $adv = \Tools::getValue('adv', 0);
        $skip = \Tools::getValue('skip', 0);

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
        // This URL will be used in `onboarding_tab.tpl` to skip the automatic store wizard.
        $skipUrl .= $separator . http_build_query($skipUrlParams);

        $redirect = $context->shop->getBaseURL(true, false) . $this->module->getPath() . 'config.php';
        $token = DfTools::encrypt($redirect);
        $paramsPopup = 'email=' . $context->employee->email . '&token=' . $token;

        $context->smarty->assign('oldPS', $oldPS);
        $context->smarty->assign('module_dir', $this->module->getPath());
        $context->smarty->assign('configured', $configured);
        $context->smarty->assign('is_new_shop', $isNewShop);
        $context->smarty->assign('shop_id', $shopId);
        $context->smarty->assign('checkConnection', DoofinderConfig::checkOutsideConnection());
        $context->smarty->assign('tokenAjax', DfTools::encrypt('doofinder-ajax'));
        $context->smarty->assign('skipurl', $skipUrl . '&first_time=1');
        $context->smarty->assign('paramsPopup', $paramsPopup);
        $context->smarty->assign('doofinderAdminUrl', sprintf(DoofinderConstants::DOOMANAGER_REGION_URL, ''));

        $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/configure.tpl');
        if ($configured) {
            $feedIndexed = \Configuration::get('DF_FEED_INDEXED');
            if (empty($feedIndexed)) {
                $controllerUrl = $context->link->getAdminLink('DoofinderAdmin', true) . '&ajax=1';
                $context->smarty->assign('update_feed_url', $controllerUrl . '&action=UpdateConfigurationField');
                $context->smarty->assign('check_feed_url', $controllerUrl . '&action=CheckConfigurationField');
                $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/indexation_status.tpl');
            }

            $output .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/configure_administration_panel.tpl');
            $output .= $this->renderFormDataFeed($adv, $skip);
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
            $context->smarty->assign('text_one_shop', $this->module->l('You cannot manage Doofinder from a \'All Shops\' or a \'Group Shop\' context, select directly the shop you want to edit', 'doofinderadminpanelview'));
            $stop = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/message_manage_one_shop.tpl');
        }

        return $stop;
    }

    public function getWarningModuleNotEnabledHtml()
    {
        $context = \Context::getContext();
        $context->smarty->assign('text_one_shop', $this->module->l('You cannot manage Doofinder from a shop context where the module is deactivated. Please activate it to access its features.', 'doofinderadminpanelview'));
        return $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/message_manage_one_shop.tpl');
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
        $apiKey = DfTools::getFormattedApiKey();

        return !$installationId && $multishopEnable && $apiKey;
    }

    /**
     * Check if the module has already been configured
     *
     * @return bool
     */
    protected function isConfigured()
    {
        $context = \Context::getContext();
        $idShop = $context->shop->id;
        $multishopEnable = (bool) \Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE');
        $defaultShopId = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $skip = \Tools::getValue('skip');
        if ($skip) {
            \Configuration::updateValue('DF_ENABLE_HASH', 0);
        }
        $sql = 'SELECT id_configuration FROM ' . _DB_PREFIX_ . 'configuration WHERE name = \'DF_ENABLE_HASH\'';
        if ($multishopEnable && is_numeric($idShop) && $defaultShopId !== (int) $idShop) {
            $sql .= ' AND id_shop = ' . (int) $idShop;
        }

        return \Db::getInstance()->getValue($sql);
    }

    /**
     * Render the data feed configuration form.
     *
     * The `skip` feature allows bypassing the onboarding screen and directly accessing the
     * configuration page by appending `&skip=1` to the URL. Unlocks the fields for
     * `installation ID`, `API Key`, and `region` like `&adv=1`.
     *
     * The differences of `&skip=1` and `&adv=1` are:
     * - These fields can now be edited without requiring `&adv=1`, while the access to the
     *   advanced tab remains restricted, as it is intended for the Support team.
     * - The hashids section is now "transparent" for the customers and only becomes explicitly visible when
     *   `&adv=1` is set, without being affected by `&skip=1`.
     * - The `&skip=1` parameter persists during form submissions to maintain expected behavior.
     *
     * @param bool $adv
     * @param bool $skip
     *
     * @return string
     */
    protected function renderFormDataFeed($adv = false, $skip = false)
    {
        $helper = new \HelperForm();
        $context = \Context::getContext();
        $idShop = $context->shop->id;
        $helper->show_toolbar = false;
        $helper->table = $this->module->getTable();
        $helper->module = $this->module;
        $helper->default_form_language = $context->language->id;
        $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->module->getIdentifier();
        // $helper->submit_action = 'submitDoofinderModuleDataFeed';
        $helper->currentIndex = $context->link->getAdminLink('AdminModules', false)
            . (($adv) ? '&adv=1' : '') . (($skip) ? '&skip=1' : '')
            . '&configure=' . $this->module->name . '&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');

        $context->smarty->assign('id_tab', 'data_feed_tab');
        $html = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/pre_tab.tpl');
        // Data feed form
        $helper->tpl_vars = [
            'fields_value' => DoofinderConfig::getConfigFormValuesDataFeed($idShop),
            'languages' => $context->controller->getLanguages(),
            'id_language' => $context->language->id,
        ];

        if (!$this->showNewShopForm(\Context::getContext()->shop)) {
            $validUpdateOnSave = UpdateOnSave::isValid();
            $html .= $helper->generateForm([$this->getConfigFormDataFeed($validUpdateOnSave)]);
            // Store information
            $helper->tpl_vars['fields_value'] = DoofinderConfig::getConfigFormValuesStoreInfo($idShop);
            $html .= $helper->generateForm([$this->getConfigFormStoreInfo()]);
        } else {
            $context->controller->warnings[] = $this->module->l("This shop is new and it hasn't been synchronized with Doofinder yet.", 'doofinderadminpanelview');
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
            'fields_value' => DoofinderConfig::getConfigFormValuesAdvanced($context->shop->id),
            'languages' => $context->controller->getLanguages(),
            'id_language' => $context->language->id,
        ];
        $context->smarty->assign('id_tab', 'advanced_tab');
        $html = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/pre_tab.tpl');
        $html .= $helper->generateForm([$this->getConfigFormAdvanced()]);
        $html .= $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/dummy/after_tab.tpl');

        return $html;
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
                5 => ['id' => 5, 'name' => sprintf($this->module->l('Each %s minutes', 'doofinderadminpanelview'), '5')],
                15 => ['id' => 15, 'name' => sprintf($this->module->l('Each %s minutes', 'doofinderadminpanelview'), '15')],
                30 => ['id' => 30, 'name' => sprintf($this->module->l('Each %s minutes', 'doofinderadminpanelview'), '30')],
                60 => ['id' => 60, 'name' => $this->module->l('Each hour', 'doofinderadminpanelview')],
                120 => ['id' => 120, 'name' => sprintf($this->module->l('Each %s hours', 'doofinderadminpanelview'), '2')],
                360 => ['id' => 360, 'name' => sprintf($this->module->l('Each %s hours', 'doofinderadminpanelview'), '6')],
                720 => ['id' => 720, 'name' => sprintf($this->module->l('Each %s hours', 'doofinderadminpanelview'), '12')],
                1440 => ['id' => 1440, 'name' => $this->module->l('Once a day', 'doofinderadminpanelview')],
                0 => ['id' => 0, 'name' => $this->module->l('Disabled', 'doofinderadminpanelview')],
            ];
        } else {
            $disabled = true;
            $query = [
                0 => ['id' => 0, 'name' => $this->module->l('Disabled', 'doofinderadminpanelview')],
            ];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Doofinder configuration', 'doofinderadminpanelview'),
                ],
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Doofinder script', 'doofinderadminpanelview'),
                        'desc' => $this->module->l('Activating this option you are inserting the script into your store code. You can manage product visibility from admin.doofinder.com.', 'doofinderadminpanelview'),
                        'name' => 'DF_SHOW_LAYER',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index product prices', 'doofinderadminpanelview'),
                        'desc' => $this->module->l('If you activate this option you will be able to show the prices of each product in the search results.', 'doofinderadminpanelview'),
                        'name' => 'DF_GS_DISPLAY_PRICES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Show product prices including taxes', 'doofinderadminpanelview'),
                        'desc' => $this->module->l('If you activate this option, the price of the products that will be displayed will be inclusive of taxes.', 'doofinderadminpanelview'),
                        'name' => 'DF_GS_PRICES_USE_TAX',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index the full path of the product category', 'doofinderadminpanelview'),
                        'name' => 'DF_FEED_FULL_PATH',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Index product attribute combinations', 'doofinderadminpanelview'),
                        'name' => 'DF_SHOW_PRODUCT_VARIATIONS',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Define which combinations of product attributes you want to index for', 'doofinderadminpanelview'),
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
                        'label' => $this->module->l('Index customized product features', 'doofinderadminpanelview'),
                        'name' => 'DF_SHOW_PRODUCT_FEATURES',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Select features will be shown in feed', 'doofinderadminpanelview'),
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
                        'label' => $this->module->l('Product Image Size', 'doofinderadminpanelview'),
                        'name' => 'DF_GS_IMAGE_SIZE',
                        'options' => [
                            'query' => DfTools::getAvailableImageSizes(),
                            'id' => 'DF_GS_IMAGE_SIZE',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->module->l('Automatically process modified products', 'doofinderadminpanelview'),
                        'desc' => $this->module->l('This action will only be executed if there are changes. If you see the field disabled, it is because you are making a usage in the indexes that is not supported by the automatic processing of modified products.', 'doofinderadminpanelview'),
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
                    'title' => $this->module->l('Save configuration', 'doofinderadminpanelview'),
                    'name' => 'submitDoofinderModuleDataFeed',
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
                    'title' => $this->module->l('Advanced Options', 'doofinderadminpanelview'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Debug Mode. Write info logs in doofinder.log file', 'doofinderadminpanelview'),
                        'name' => 'DF_DEBUG',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Debug CURL error response', 'doofinderadminpanelview'),
                        'name' => 'DF_DEBUG_CURL',
                        'desc' => $this->module->l('To debug if your server has symptoms of connection problems', 'doofinderadminpanelview'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Doofinder script in mobile version', 'doofinderadminpanelview'),
                        'name' => 'DF_SHOW_LAYER_MOBILE',
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                    [
                        'type' => (version_compare(_PS_VERSION_, '1.6.0', '>=') ? 'switch' : 'radio'),
                        'label' => $this->module->l('Enable multicurrency', 'doofinderadminpanelview'),
                        'name' => 'DF_MULTIPRICE_ENABLED',
                        'desc' => $this->module->l('Do not change this option unless our support team has given you a specific guidance', 'doofinderadminpanelview'),
                        'is_bool' => true,
                        'values' => $this->getBooleanFormValue(),
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save Internal Search Options', 'doofinderadminpanelview'),
                    'name' => 'submitDoofinderModuleAdvanced',
                ],
            ],
        ];
    }

    /**
     * Get the fields of the store information form
     *
     * @return array
     */
    protected function getConfigFormStoreInfo()
    {
        $isAdvParamPresent = (bool) \Tools::getValue('adv', 0);
        $isManualInstallation = (bool) \Tools::getValue('skip', 0);
        $multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');
        $inputs = [
            [
                'type' => 'text',
                'label' => $this->module->l('Doofinder Store ID', 'doofinderadminpanelview'),
                'name' => 'DF_INSTALLATION_ID',
                'desc' => $this->module->l('You can find this identifier in our control panel. Inside the side menu labeled "Store settings".', 'doofinderadminpanelview'),
                'lang' => false,
                'readonly' => !$isAdvParamPresent && !$isManualInstallation,
            ],
            [
                'type' => 'text',
                'label' => $this->module->l('Doofinder Api Key', 'doofinderadminpanelview'),
                'name' => 'DF_API_KEY',
                'readonly' => !$isAdvParamPresent && !$isManualInstallation,
            ],
            [
                'type' => 'select',
                'label' => $this->module->l('Region', 'doofinderadminpanelview'),
                'name' => 'DF_REGION',
                'options' => [
                    'query' => [
                        0 => ['id' => 'eu1', 'name' => $this->module->l('Europe', 'doofinderadminpanelview')],
                        1 => ['id' => 'us1', 'name' => $this->module->l('United States', 'doofinderadminpanelview')],
                        2 => ['id' => 'ap1', 'name' => $this->module->l('Asia - Pacific', 'doofinderadminpanelview')],
                    ],
                    'id' => 'id',
                    'name' => 'name',
                ],
                'disabled' => !$isAdvParamPresent && !$isManualInstallation,
            ],
        ];

        // This is necessary since disabled fields are not sent in the submit, thus causing errors.
        if (!$isAdvParamPresent && !$isManualInstallation) {
            $inputs[] = [
                'type' => 'hidden',
                'name' => 'DF_REGION',
            ];
        }

        if ($isAdvParamPresent) {
            if ($multipriceEnabled) {
                $hashidKeys = self::getMultipriceKeys();
                $keyToUse = 'keyMultiprice';
                $labelToUse = 'labelMultiprice';
            } else {
                $hashidKeys = DfTools::getHashidKeys();
                $keyToUse = 'key';
                $labelToUse = 'label';
            }

            foreach ($hashidKeys as $hashidKey) {
                $inputs[] = [
                    'type' => 'text',
                    'label' => $this->module->l('Hashid for Search Engine', 'doofinderadminpanelview') . ' ' . $hashidKey[$labelToUse],
                    'name' => $hashidKey[$keyToUse],
                    'readonly' => !$isAdvParamPresent && !$isManualInstallation,
                ];
            }
        }

        $inputs[] = [
            'type' => 'html',
            'label' => $this->module->l('Feed URLs to use on Doofinder Admin panel', 'doofinderadminpanelview'),
            'name' => 'DF_FEED_READONLY_URLS',
            'html_content' => $this->feedUrlsFormatHtml($this->getFeedURLs()),
        ];

        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Store Information', 'doofinderadminpanelview'),
                ],
                'input' => $inputs,
                'submit' => [
                    'title' => $this->module->l('Save Store Info Widget Options', 'doofinderadminpanelview'),
                    'name' => 'submitDoofinderModuleStoreInfo',
                ],
            ],
        ];
    }

    private static function getMultipriceKeys()
    {
        $hashidKeys = DfTools::getHashidKeys();
        $arrayKeys = [];

        foreach ($hashidKeys as $hashidKey) {
            $arrayKeys[$hashidKey['keyMultiprice']] = $hashidKey;
        }

        return $arrayKeys;
    }

    private function getBooleanFormValue()
    {
        $option = [
            [
                'id' => 'active_on',
                'value' => true,
                'label' => $this->module->l('Enabled', 'doofinderadminpanelview'),
            ],
            [
                'id' => 'active_off',
                'value' => false,
                'label' => $this->module->l('Disabled', 'doofinderadminpanelview'),
            ],
        ];

        return $option;
    }

    private function getFeedURLs()
    {
        $urls = [];
        $context = \Context::getContext();
        $languages = \Language::getLanguages(true, $context->shop->id);
        $multipriceEnabled = \Configuration::get('DF_MULTIPRICE_ENABLED');

        foreach ($languages as $lang) {
            $langIso = \Tools::strtoupper($lang['iso_code']);

            if ($multipriceEnabled) {
                $urls[] = [
                    'url' => UrlManager::getFeedUrl($context->shop->id, $langIso),
                    'lang' => $langIso,
                ];
            } else {
                foreach (\Currency::getCurrencies() as $cur) {
                    $currencyIso = \Tools::strtoupper($cur['iso_code']);
                    $urls[] = [
                        'url' => UrlManager::getFeedUrl($context->shop->id, $langIso, $currencyIso),
                        'lang' => $langIso,
                        'currency' => $currencyIso,
                    ];
                }
            }
        }

        return $urls;
    }

    private function feedUrlsFormatHtml($df_feed_urls)
    {
        $htmlContent = '<dl style="max-height:150px; overflow-y: auto;">';
        foreach ($df_feed_urls as $feed_url) {
            $htmlContent .= '<dt>' . $this->module->l('Data feed URL for', 'doofinderadminpanelview') . ' ['
                . htmlspecialchars($feed_url['lang'], ENT_QUOTES, 'UTF-8') . (isset($feed_url['currency']) ? ' - '
                . htmlspecialchars($feed_url['currency'], ENT_QUOTES, 'UTF-8') : '') . ']</dt>';
            $htmlContent .= '<dd><a href="' . htmlspecialchars(urldecode($feed_url['url']), ENT_QUOTES, 'UTF-8') . '" target="_blank">'
                . htmlspecialchars($feed_url['url'], ENT_QUOTES, 'UTF-8') . '</a></dd>';
        }
        $htmlContent .= '</dl>';

        return $htmlContent;
    }
}
