<?php
/**
 * 2007-2022 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
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
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConfig;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'doofinder/src/autoloader.php';

function upgrade_module_4_11_0($module)
{
    DoofinderConfig::debug('Initiating 4.11.0 upgrade');
    // Delete old *.php files
    unlink_files();
    DoofinderConfig::debug('Old files deleted successfully.');

    // Update feed URLs
    DoofinderInstallation::updateFeedUrls();

    DoofinderConfig::debug('Feed URLs updated successfully.');

    return true;
}

function unlink_files()
{
    $files = [
        "cache.php",
        "config.php",
        "doofinder-ajax.php",
        "feed.php",
        "landing.php"
    ];

    foreach ($files as $file_name) {
        $file_path = _PS_MODULE_DIR_ . 'doofinder' . DIRECTORY_SEPARATOR . $file_name;
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                error_log('Couldn\'t delete file: ' . $file_path);
                throw new Exception('Error when deleting file: ' . $file_name);
            }
            error_log('Deleted file: ' . $file_path);
        }
    }
}
