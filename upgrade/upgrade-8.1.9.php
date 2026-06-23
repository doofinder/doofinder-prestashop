<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Installer\DoofinderInstallation;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 8.1.9.
 *
 * This upgrade pushes the current store options (url, shop_id, shop_group_id)
 * for every registered Doofinder installation to the plugins API so that
 * Doofinder has up-to-date metadata for each PrestaShop shop.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an exception occurs during the update
 */
function upgrade_module_8_1_9($module)
{
    DoofinderConfig::debug('Initiating 8.1.9 upgrade');

    try {
        DoofinderInstallation::updateStoreOptions();
    } catch (Exception $exception) {
        PrestaShopLogger::addLog($exception->getMessage(), 3, $exception->getCode(), 'Module', $module->id);

        return false;
    }

    DoofinderConfig::debug('Store options updated successfully.');

    return true;
}
