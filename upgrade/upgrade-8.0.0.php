<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licensed under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the license agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 */

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 8.0.0.
 *
 * This upgrade remove the src/Utils/DfDb.php because it's not used anymore.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an exception occurs during the update
 */
function upgrade_module_8_0_0($module)
{
    DoofinderConfig::debug('Initiating 8.0.0 upgrade');

    $files = ['src/Utils/DfDb.php'];

    foreach ($files as $fileName) {
        $filePath = realpath(_PS_MODULE_DIR_ . 'doofinder' . DIRECTORY_SEPARATOR . $fileName);
        if (!file_exists($filePath) || is_dir($filePath)) {
            continue;
        }

        if (!unlink($filePath)) {
            DoofinderConfig::debug('Couldn\'t delete file: ' . $filePath);
            continue;
        }

        DoofinderConfig::debug('Deleted file: ' . $filePath);
    }

    return true;
}
