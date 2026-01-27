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

use PrestaShop\Module\Doofinder\Core\DoofinderConstants;

/**
 * Front controller for serving Doofinder feeds in CSV format.
 *
 * Supports multiple feed types, including:
 * - Products
 * - Categories
 * - CMS pages
 *
 * Handles AJAX responses compatible with PrestaShop 1.5, 1.6, and 1.7.
 */
class DoofinderFeedModuleFrontController extends ModuleFrontController
{
    /**
     * Generates and outputs the requested feed based on the 'type' parameter.
     *
     * - Determines feed type ('product', 'category', 'page') from request.
     * - Includes the corresponding feed file.
     * - Outputs feed content using ajaxRender/ajaxDie or echo for legacy compatibility.
     *
     * @see FrontController::initContent()
     *
     * @return void outputs feed content directly and exits
     */
    public function initContent()
    {
        parent::initContent();

        $this->ajax = true;

        ob_start();
        switch (Tools::getValue('type')) {
            case 'category':
                require self::get_plugin_dir() . 'feeds/category.php';
                break;

            case 'page':
                require self::get_plugin_dir() . 'feeds/cms.php';
                break;

            case 'product':
            default:
                require self::get_plugin_dir() . 'feeds/product.php';
                break;
        }
        $feed = ob_get_clean();
        if (method_exists($this, 'ajaxRender')) {
            $this->ajaxRender($feed);
            exit;
        } elseif (method_exists($this, 'ajaxDie')) {
            // Workaround for PS 1.6 as ajaxRender is not available
            $this->ajaxDie($feed);
        } else {
            // Workaround for PS 1.5
            echo $feed;
            exit;
        }
    }

    /**
     * Returns the full path to the Doofinder module directory.
     *
     * @return string module directory path
     */
    private static function get_plugin_dir()
    {
        return _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . DoofinderConstants::NAME . DIRECTORY_SEPARATOR;
    }
}
