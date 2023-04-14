<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderAdminController extends ModuleAdminController
{
    public function __construct()
    {
        $this->context = Context::getContext();
        $this->module = Module::getInstanceByName('doofinder');
        $this->bootstrap = true;
        $this->lang = false;
        parent::__construct();
    }

    public function displayAjaxUpdateConfigurationField()
    {
        Configuration::updateValue('DF_FEED_INDEXED', true);
        $this->ajaxDie(json_encode(['success' => true]));
    }

    public function displayAjaxCheckConfigurationField()
    {
        $is_feed_indexed = Configuration::get('DF_FEED_INDEXED', null, null, null, false);
        $this->ajaxDie(json_encode(['success' => $is_feed_indexed]));
    }
}
