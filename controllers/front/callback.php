<?php
class DoofinderCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
            header('Access-Control-Allow-Headers: x-requested-with, Content-Type, origin, authorization, accept, client-security-token');

            Configuration::updateValue('DF_FEED_INDEXED', true);

            die(json_encode(['status' => 'success']));
        } else {
            http_response_code(405);
            die();
        }
    }

    public function initContent()
    {
        parent::initContent();
    }
}