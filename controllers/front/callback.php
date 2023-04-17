<?php

class DoofinderCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: https://*.doofinder.com');
            Configuration::updateValue('DF_FEED_INDEXED', true);

            exit(json_encode(['status' => 'success']));
        } else {
            http_response_code(405);
            exit;
        }
    }

    public function initContent()
    {
        parent::initContent();
    }
}
