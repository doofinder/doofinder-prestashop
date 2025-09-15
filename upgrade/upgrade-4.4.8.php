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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade the module to version 4.4.8.
 *
 * This upgrade step:
 * - Sets the DF_FEED_INDEXED configuration value to true.
 * - Installs a back-office tab for the Doofinder admin controller.
 *
 * @param Doofinder $module the module instance being upgraded
 *
 * @return bool true if the configuration update and tab installation succeed, false otherwise
 */
function upgrade_module_4_4_8($module)
{
    return Configuration::updateGlobalValue('DF_FEED_INDEXED', true)
    && installTabs_4_4_8();
}

/**
 * Install an inactive admin tab for the Doofinder admin controller.
 *
 * This tab is created but not displayed in the back-office menu (active = false).
 *
 * @return bool true if the tab was saved successfully, false otherwise
 */
function installTabs_4_4_8()
{
    $tab = new Tab();
    $tab->active = false;
    $tab->class_name = 'DoofinderAdmin';
    $tab->name = [];
    foreach (Language::getLanguages() as $lang) {
        $tab->name[$lang['id_lang']] = 'Doofinder admin controller';
    }
    $tab->id_parent = 0;
    $tab->module = 'doofinder';

    return $tab->save();
}
