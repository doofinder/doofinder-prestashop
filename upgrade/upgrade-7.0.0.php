<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 7.0.0.
 *
 * This upgrade removes the landing pages feature which has been deprecated.
 * It drops the doofinder_landing table that was used to cache landing page data.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an error occurs
 */
function upgrade_module_7_0_0($module)
{
    DoofinderConfig::debug('Initiating 7.0.0 upgrade - Removing landing pages feature');

    $result = Db::getInstance()->execute(
        'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'doofinder_landing`'
    );

    if ($result) {
        DoofinderConfig::debug('Landing pages table dropped successfully.');
    } else {
        DoofinderConfig::debug('Failed to drop landing pages table.');
    }

    return $result;
}
