<?php
/**
 * 2007-2022 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
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
 * @license   https://opensource.org/licenses/MIT MIT License
 * International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 4.12.0.
 *
 * This upgrade step removes obsolete PHP files from previous versions
 * to prevent conflicts, dead code, or deprecated behavior.
 * Logs progress using DoofinderConfig::debug.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool always returns true, regardless of whether files were successfully deleted
 */
function upgrade_module_4_12_0($module)
{
    DoofinderConfig::debug('Initiating 4.12.0 upgrade');

    // Delete old *.php files
    unlinkFiles();
    DoofinderConfig::debug('Old files deleted successfully.');

    return true;
}

/**
 * Deletes legacy files no longer needed by the Doofinder module.
 *
 * Iterates through a predefined list of obsolete files, checks their existence,
 * and attempts to remove them from the module directory. Each deletion attempt
 * is logged, including failures.
 *
 * @return void
 */
function unlinkFiles()
{
    $files = [
        'autoloader.php',
        'cache.php',
        'config.php',
        'doofinder-ajax.php',
        'feed.php',
        'landing.php',
        'controllers/front/landingEntrypoint.php',
        /* Files from older versions. */
        'lib/doofinder_api.php',
        'lib/doofinder_api_landing.php',
        'views/templates/front/script.tpl',
        'lib/doofinder_api_index.php',
        'lib/doofinder_api_items.php',
        'lib/doofinder_layer_api.php',
        'lib/doofinder_api_unique_script.php',
        'lib/doofinder_installation.php',
        'lib/dfTools.class.php',
        'lib/dfCategory_build.php',
        'lib/dfCms_build.php',
        'lib/dfProduct_build.php',
        'lib/DfCategoryBuild.php',
        'lib/DfCmsBuild.php',
        'lib/DfProductBuild.php',
        'lib/DfTools.php',
        'lib/DoofinderAdminPanelView.php',
        'lib/DoofinderApi.php',
        'lib/DoofinderApiIndex.php',
        'lib/DoofinderApiItems.php',
        'lib/DoofinderApiLanding.php',
        'lib/DoofinderApiUniqueScript.php',
        'lib/DoofinderConfig.php',
        'lib/DoofinderConstants.php',
        'lib/DoofinderException.php',
        'lib/DoofinderInstallation.php',
        'lib/DoofinderLayerApi.php',
        'lib/DoofinderResults.php',
        'lib/DoofinderScript.php',
        'lib/EasyREST.php',
        'lib/FormManager.php',
        'lib/HookManager.php',
        'lib/LanguageManager.php',
        'lib/SearchEngine.php',
        'lib/UpdateOnSave.php',
        'lib/UrlManager.php',
    ];

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
}
