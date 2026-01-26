<?php
/**
 * 2007-2022 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2022 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/MIT MIT License
 * International Registered Trademark & Property of PrestaShop SA
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
