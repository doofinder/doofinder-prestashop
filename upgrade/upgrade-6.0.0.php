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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 6.0.0.
 *
 * This upgrade step updates the file paths to the new namespace structure deleting the old ones.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an exception occurs during the update
 */
function upgrade_module_6_0_0($module)
{
    DoofinderConfig::debug('Initiating 6.0.0 upgrade');

    $files = [
        'src/Entity/DfCategoryBuild.php',
        'src/Entity/DfCmsBuild.php',
        'src/Entity/DfDb.php',
        'src/Entity/DfProductBuild.php',
        'src/Entity/DfTools.php',
        'src/Entity/DoofinderAdminPanelView.php',
        'src/Entity/DoofinderApi.php',
        'src/Entity/DoofinderApiIndex.php',
        'src/Entity/DoofinderApiItems.php',
        'src/Entity/DoofinderApiLanding.php',
        'src/Entity/DoofinderApiSingleScript.php',
        'src/Entity/DoofinderConfig.php',
        'src/Entity/DoofinderConstants.php',
        'src/Entity/DoofinderException.php',
        'src/Entity/DoofinderInstallation.php',
        'src/Entity/DoofinderLayerApi.php',
        'src/Entity/DoofinderResults.php',
        'src/Entity/DoofinderScript.php',
        'src/Entity/EasyREST.php',
        'src/Entity/FormManager.php',
        'src/Entity/HookManager.php',
        'src/Entity/LanguageManager.php',
        'src/Entity/SearchEngine.php',
        'src/Entity/UpdateOnSave.php',
        'src/Entity/UrlManager.php'];

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
