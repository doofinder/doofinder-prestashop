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

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderApi;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation;

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

        $this->ajax = 1;

        $checkApiKey = Tools::getValue('check_api_key');
        if ($checkApiKey) {
            exit(DoofinderApi::checkApiKey(true));
        }

        $autoinstaller = Tools::getValue('autoinstaller');
        $shopId = Tools::getValue('shop_id', null);
        if ($autoinstaller) {
            if (Tools::getValue('token') == Tools::encrypt('doofinder-ajax')) {
                header('Content-Type:application/json; charset=utf-8');
                DoofinderInstallation::autoinstaller($shopId);
                $this->ajaxRender(json_encode(['success' => true]));
                exit;
            } else {
                $this->ajaxRender(json_encode([
                    'success' => false, 'errors' => ['Forbidden access. Invalid token for autoinstaller.'],
                ]));
                exit;
            }
        }
    }
}