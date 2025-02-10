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

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConstants;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderFeedModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->ajax = 1;

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
        header('Content-Type: text/csv; charset=utf-8');
        if (method_exists($this, 'ajaxRender')) {
            $this->ajaxRender($feed);
            exit;
        } else {
            // Workaround for PS 1.6 as ajaxRender is not available
            $this->ajaxDie($feed);
        }
    }

    private static function get_plugin_dir()
    {
        return _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . DoofinderConstants::NAME . DIRECTORY_SEPARATOR;
    }
}
