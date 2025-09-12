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

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Installer\DoofinderInstallation;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 5.1.4.
 *
 * This upgrade step updates feed URLs used by the module, because in older versions the paths
 * were /modules/doofinder/{name}.php (where {name} could be config, feed, etc.) which were physical
 * PHP files and PrestaShop 9 flags direct calls as errors, so instead it encourages to call a Module FrontController.
 * So from this versions the paths are /module/doofinder/{name} (Note that this time is `module` and not `modules` and the .php
 * extension has been removed since it is now a rewritten URL and not a real path to a physical file)
 *
 * Logs progress messages and captures any exceptions during the update process.
 * If an error occurs, it records the issue in PrestaShop logs and stops the upgrade.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an exception occurs during the update
 */
function upgrade_module_5_1_4($module)
{
    DoofinderConfig::debug('Initiating 5.1.4 upgrade');

    try {
        DoofinderInstallation::updateFeedUrls();
    } catch (Exception $exception) {
        PrestaShopLogger::addLog($exception->getMessage(), 3, $exception->getCode(), 'Module', $module->id);

        return false;
    }

    DoofinderConfig::debug('Feed URLs updated successfully.');

    return true;
}
