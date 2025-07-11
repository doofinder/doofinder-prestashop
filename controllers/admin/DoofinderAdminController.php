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

class DoofinderAdminController extends ModuleAdminController
{
    public function __construct()
    {
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->lang = false;
        parent::__construct();
    }

    public function displayAjaxUpdateConfigurationField()
    {
        Configuration::updateValue('DF_FEED_INDEXED', true);
        $this->ajaxRender(json_encode(['success' => true]));
    }

    public function displayAjaxCheckConfigurationField()
    {
        $is_feed_indexed = Configuration::get('DF_FEED_INDEXED', null, null, null, false);
        $this->ajaxRender(json_encode(['success' => $is_feed_indexed]));
    }

    public function ajaxRender($value = null, $controller = null, $method = null)
    {
        if (method_exists('ModuleAdminController', 'ajaxRender')) {
            return parent::ajaxRender($value, $controller, $method);
        }

        echo $value;
        exit;
    }
}
