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

/**
 * Admin controller for managing Doofinder module configuration.
 *
 * Provides AJAX endpoints to update and check configuration fields,
 * specifically for the DF_FEED_INDEXED flag, which indicates if
 * the feed has been indexed or not for the first time or after launching
 * a reindexation.
 */
class DoofinderAdminController extends ModuleAdminController
{
    /**
     * Initializes the controller with PrestaShop context.
     */
    public function __construct()
    {
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->lang = false;
        parent::__construct();
    }

    /**
     * AJAX action to update the DF_FEED_INDEXED configuration flag.
     *
     * Sets DF_FEED_INDEXED to true and returns a JSON success response.
     *
     * @return void
     */
    public function displayAjaxUpdateConfigurationField()
    {
        Configuration::updateValue('DF_FEED_INDEXED', true);
        $this->ajaxRender(json_encode(['success' => true]));
    }

    /**
     * AJAX action to check the DF_FEED_INDEXED configuration flag.
     *
     * Returns a JSON response indicating whether the flag is enabled.
     *
     * @return void
     */
    public function displayAjaxCheckConfigurationField()
    {
        $is_feed_indexed = Configuration::get('DF_FEED_INDEXED', null, null, null, false);
        $this->ajaxRender(json_encode(['success' => $is_feed_indexed]));
    }

    /**
     * Renders AJAX responses, with compatibility for several PrestaShop versions.
     *
     * Note that `ajaxRender` method is only available since PrestaShop 1.7
     *
     * @param string|null $value the response content to render
     * @param string|null $controller optional controller name for compatibility
     * @param string|null $method optional method name for compatibility
     *
     * @return void
     */
    public function ajaxRender($value = null, $controller = null, $method = null)
    {
        if (method_exists('ModuleAdminController', 'ajaxRender')) {
            parent::ajaxRender($value, $controller, $method);

            return;
        }

        echo $value;
        exit;
    }
}
