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

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Manager\LanguageManager;

/**
 * Front controller for handling Doofinder landing pages.
 *
 * Retrieves landing page requests by hashid and slug,
 * determines the appropriate language, and redirects to the correct landing page URL.
 */
class DoofinderLandingModuleFrontController extends ModuleFrontController
{
    /**
     * Processes the landing page request.
     *
     * - Retrieves 'hashid' and 'slug' parameters from the request.
     * - Validates that both values exist.
     * - Determines the language ID based on the hashid.
     * - Redirects to the proper landing page if valid.
     * - Redirects to the 404 page if parameters are missing or invalid.
     *
     * @see FrontController::initContent()
     *
     * @return void redirects the browser; execution does not continue after redirect
     */
    public function initContent()
    {
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
