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

use PrestaShop\Module\Doofinder\Entity\DfTools;
use PrestaShop\Module\Doofinder\Entity\DoofinderApi;
use PrestaShop\Module\Doofinder\Entity\DoofinderInstallation;

class DoofinderAjaxModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->ajax = true;

        $checkApiKey = Tools::getValue('check_api_key');
        if ($checkApiKey) {
            exit(DoofinderApi::checkApiKey(true));
        }

        $autoinstaller = Tools::getValue('autoinstaller');
        $shopId = Tools::getValue('shop_id', null);
        if ($autoinstaller) {
            if (Tools::getValue('token') == DfTools::encrypt('doofinder-ajax')) {
                header('Content-Type:application/json; charset=utf-8');
                DoofinderInstallation::autoinstaller($shopId);
                $this->compatRender(json_encode(['success' => true]));
            } else {
                $this->compatRender(json_encode([
                    'success' => false, 'errors' => ['Forbidden access. Invalid token for autoinstaller.'],
                ]));
            }
        }
    }

    /**
     * The native function exists only after PrestaShop 1.7, so in order
     * to keep compatibility with PrestaShop 1.6 we must keep `ajaxDie` as a fallback.
     *
     * @param string|null $value
     * @param string|null $controller
     * @param string|null $method
     *
     * @throws PrestaShopException
     */
    private function compatRender($value = null, $controller = null, $method = null)
    {
        if (method_exists($this, 'ajaxRender')) {
            $this->ajaxRender($value, $controller, $method);
            exit;
        } elseif (method_exists($this, 'ajaxDie')) {
            $this->ajaxDie($value, $controller, $method);
            exit;
        }

        echo $value;
        exit;
    }
}
