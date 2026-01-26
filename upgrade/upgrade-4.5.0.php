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
