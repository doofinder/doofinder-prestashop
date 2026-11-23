<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 *
 * @see       https://opensource.org/licenses/MIT
 */

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;
use PrestaShop\Module\Doofinder\Core\SalesFromPlatform;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 8.7.0.
 *
 * The sales are sent from the module: it saves the Doofinder session of each cart and sends the
 * sale when an order gets a valid or paid status. Doofinder registers them only in the stores it
 * has turned them on for.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an exception occurs during the update
 */
function upgrade_module_8_7_0($module)
{
    DoofinderConfig::debug('Initiating 8.7.0 upgrade');

    try {
        if (!SalesFromPlatform::installDb()
            || !$module->registerHook('actionCartSave')
            || !$module->registerHook('actionOrderStatusPostUpdate')) {
            return false;
        }
    } catch (Exception $exception) {
        PrestaShopLogger::addLog($exception->getMessage(), 3, $exception->getCode(), 'Module', $module->id);

        return false;
    }

    DoofinderConfig::debug('8.7.0 upgrade finished');

    return true;
}
