<?php
/**
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade the module to version 4.3.1.
 *
 * This upgrade step:
 * - Updates the DF_AI_ADMIN_ENDPOINT configuration value by replacing
 *   'app' with 'admin' in the existing endpoint URL.
 *
 * @param Doofinder $module the module instance being upgraded (not used here but required by the signature)
 *
 * @return bool true if the value was updated successfully, false otherwise
 */
function upgrade_module_4_3_1($module)
{
    $current_admin_endpoint = Configuration::getGlobalValue('DF_AI_ADMIN_ENDPOINT');
    $admin_endpoint = str_replace('app', 'admin', $current_admin_endpoint);

    return Configuration::updateGlobalValue('DF_AI_ADMIN_ENDPOINT', $admin_endpoint);
}
