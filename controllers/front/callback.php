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
