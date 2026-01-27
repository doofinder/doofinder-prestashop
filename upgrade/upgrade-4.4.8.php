<?php
/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
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
