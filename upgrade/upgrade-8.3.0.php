<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * @see       https://opensource.org/licenses/MIT
 */

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Installer\DoofinderInstallation;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 8.3.0.
 *
 * The attribute group and feature selectors are gone: everything is exported now and the
 * selection is made from the Doofinder admin. Before dropping their configuration, this
 * upgrade reads it to find out which of the exported attribute groups and features have a
 * name that collides with a feed field, and records them as the ones that take over that
 * field, which is what they do today. What was not being exported keeps the feed field
 * instead, so nothing that works today starts being overwritten.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an exception occurs during the update
 */
function upgrade_module_8_3_0($module)
{
    DoofinderConfig::debug('Initiating 8.3.0 upgrade');

    try {
        DoofinderInstallation::migrateFieldConflicts();
        DoofinderInstallation::deleteFieldSelectionConfig();
    } catch (Exception $exception) {
        PrestaShopLogger::addLog($exception->getMessage(), 3, $exception->getCode(), 'Module', $module->id);

        return false;
    }

    DoofinderConfig::debug('8.3.0 upgrade finished');

    return true;
}
