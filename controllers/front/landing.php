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

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConfig;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConstants;
use PrestaShop\Module\Doofinder\Src\Entity\LanguageManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderLandingModuleFrontController extends ModuleFrontController
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

        $hashid = Tools::getValue('hashid');
        $slug = Tools::getValue('slug');

        if (!$hashid || !$slug) {
            DoofinderConfig::debug('[Landing][Warning] Hashid and/or slug could not be retrieved: ' . PHP_EOL . '- slug: ' . DoofinderConfig::dump($slug) . '- hashid: ' . DoofinderConfig::dump($hashid));
            Tools::redirect('index.php?controller=404');
            exit;
        }

        $idLang = LanguageManager::getLanguageByHashid($hashid);

        if (!$idLang) {
            DoofinderConfig::debug('[Landing][Warning] Invalid Language ID: ' . DoofinderConfig::dump($idLang));
            Tools::redirect('index.php?controller=404');
            exit;
        }

        $link = $this->context->link->getPageLink(
            'module-doofinder-landingpage',
            null,
            $idLang,
            ['landing_name' => $slug]
        );

        Tools::redirect($link);
    }
}
