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

use PrestaShop\Module\Doofinder\Api\DoofinderApiSingleScript;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade the module to version 4.8.2.
 *
 * This upgrade:
 * - Uses existing installation credentials (installation ID, region, API key)
 *   to initialize DoofinderApiSingleScript.
 * - Calls `setSingleScriptFlag()` to mark that the single-script is enabled.
 * - Stores DF_UNIQUE_SCRIPT = true in configuration.
 *
 * @param Doofinder $module the module instance being upgraded
 *
 * @return bool true if the flag is set successfully, false otherwise
 */
function upgrade_module_4_8_2($module)
{
    $installationId = Configuration::get('DF_INSTALLATION_ID');
    $region = Configuration::get('DF_REGION');
    $apiKey = Configuration::get('DF_API_KEY');

    $apiModule = new DoofinderApiSingleScript($installationId, $region, $apiKey);
    $apiModule->setSingleScriptFlag();

    return Configuration::updateValue('DF_UNIQUE_SCRIPT', true);
}
