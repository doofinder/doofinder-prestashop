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
$root_path = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$config_file_path = $root_path . '/config/config.inc.php';
if (file_exists($config_file_path)) {
    require_once $config_file_path;
} else {
    require_once dirname(__FILE__) . '/../../config/config.inc.php';
}

if (!defined('_PS_VERSION_')) {
    exit;
}

if (Module::isInstalled('doofinder') && Module::isEnabled('doofinder')) {
    $hashid = Tools::getValue('hashid');
    $slug = Tools::getValue('slug');

    if ($hashid && $slug) {
        $module = Module::getInstanceByName('doofinder');

        if ($id_lang = $module->getLanguageByHashid($hashid)) {
            $link = Context::getContext()->link->getModuleLink(
                $module->name,
                'landing',
                ['landing_name' => $slug],
                null,
                $id_lang
            );

            Tools::redirect($link);
        }
    }
}
Tools::redirect('index.php?controller=404');
