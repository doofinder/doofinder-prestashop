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
 * Upgrade the module to version 4.2.0.
 *
 * This upgrade step:
 * - Installs or updates the required database table.
 * - Registers new hooks to handle product save and delete actions for the Update On Save.
 *
 * @param Doofinder $module the module instance being upgraded
 *
 * @return bool true on success, false on failure
 */
function upgrade_module_4_2_0($module)
{
    return installDb_4_2_0()
        && $module->registerHook('actionProductSave')
        && $module->registerHook('actionProductDelete');
}

/**
 * Create or update the `doofinder_product` table if it does not already exist.
 *
 * The table stores product changes to synchronize with Doofinder:
 * - `id_shop` and `id_product` identify the product in a specific shop.
 * - `action` stores the type of change (e.g., save or delete).
 * - `date_upd` stores the timestamp of the last change.
 * A unique key ensures there are no duplicate entries per shop/product combination.
 *
 * @return bool true if the query executed successfully, false otherwise
 */
function installDb_4_2_0()
{
    return Db::getInstance()->execute(
        '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doofinder_product` (
            `id_doofinder_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT(10) UNSIGNED NOT NULL,
            `id_product` INT(10) UNSIGNED NOT NULL,
            `action` VARCHAR(45) NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_doofinder_product`),
            CONSTRAINT uc_shop_product UNIQUE KEY (id_shop,id_product)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
    );
}
