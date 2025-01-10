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

use PrestaShop\Module\Doofinder\Src\Entity\DoofinderConfig;
use PrestaShop\Module\Doofinder\Src\Entity\DoofinderInstallation;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'doofinder/src/autoloader.php';

function upgrade_module_4_11_0($module)
{
    DoofinderConfig::debug('Initiating 4.11.0 upgrade');
    // Delete old *.php files
    unlinkFiles();
    DoofinderConfig::debug('Old files deleted successfully.');

    // Update feed URLs
    DoofinderInstallation::updateFeedUrls();

    DoofinderConfig::debug('Feed URLs updated successfully.');

    return true;
}

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
        $filePath = _PS_MODULE_DIR_ . 'doofinder' . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($filePath)) {
            continue;
        }

        if (!unlink($filePath)) {
            error_log('Couldn\'t delete file: ' . $filePath);
            throw new Exception('Error when deleting file: ' . $fileName);
        }

        error_log('Deleted file: ' . $filePath);
    }
}
