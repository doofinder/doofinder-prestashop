<?php
/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles Doofinder callback requests for the module.
 *
 * Processes POST requests from Doofinder and updates module configuration.
 */
class DoofinderCallbackModuleFrontController extends ModuleFrontController
{
    /**
     * Handles incoming requests.
     *
     * For POST requests:
     * - Sets JSON headers and CORS policy.
     * - Updates the DF_FEED_INDEXED configuration flag to true.
     * - Returns a JSON success response.
     *
     * For non-POST requests:
     * - Returns HTTP 405 Method Not Allowed.
     *
     * @return void
     */
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

    /**
     * Initializes the content for the front controller.
     *
     * Calls the parent implementation. No additional content is rendered.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();
    }
}
