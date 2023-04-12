<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderAdminController extends ModuleAdminController
{
    public function __construct()
    {
        $this->context->controller->warnings[] = 'Entra en el constructor del controlador';
        $this->context = Context::getContext();
        $this->module = Module::getInstanceByName('doofinder');
        $this->bootstrap = true;
        $this->lang = false;

        parent::__construct();
    }

    public function init()
    {
        $this->context->controller->warnings[] = 'Entra en el init del controlador';
        exit;
    }

    public function displayAjax()
    {
        $this->context->controller->warnings[] = 'Entra en el controlador';

        $action = Tools::getValue('action');

        if (!empty($action) && method_exists($this, 'ajaxProcess' . $action)) {
            $this->{'ajaxProcess' . $action}();
        } else {
            $this->ajaxDie(json_encode(['error' => 'Unknown action']));
        }
    }

    public function ajaxProcessUpdateConfigurationField()
    {
        Configuration::updateValue('DF_FEED_INDEXED', true);
        $this->ajaxDie(json_encode(['success' => true]));
    }
}
