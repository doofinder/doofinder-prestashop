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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade the module to version 4.5.0.
 *
 * This upgrade step:
 * - Creates the doofinder_landing table if it does not already exist.
 * - Registers the 'moduleRoutes' hook.
 * - Calls setSearchEnginesByConfig() to configure search engines.
 *
 * @param Doofinder $module the module instance being upgraded
 *
 * @return bool true if all steps succeed, false otherwise
 */
function upgrade_module_4_5_0($module)
{
    return installDb_4_5_0()
        && $module->registerHook('moduleRoutes')
        && $module->setSearchEnginesByConfig();
}

/**
 * Install database structure for module version 4.5.0.
 *
 * Creates a table to store landing page data if it does not exist.
 *
 * @return bool true if the query executes successfully, false otherwise
 */
function installDb_4_5_0()
{
    return Db::getInstance()->execute(
        '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doofinder_landing` (
                `name` VARCHAR(45) NOT NULL,
                `hashid` VARCHAR(45) NOT NULL,
                `data` TEXT NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`name`, `hashid`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
    );
}
