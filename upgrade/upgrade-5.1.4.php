<?php
/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
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
