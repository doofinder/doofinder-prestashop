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
 * Upgrade the module to version 4.6.4.
 *
 * This upgrade step:
 * - Replaces the old doofinder_product table with a new unified doofinder_updates table.
 * - Adds support for tracking updates from multiple indexable objects (products, CMS pages, categories).
 * - Registers hooks for CMS and category CRUD operations.
 *
 * @param Doofinder $module the module instance being upgraded
 *
 * @return bool true if all steps succeed, false otherwise
 */
function upgrade_module_4_6_4($module)
{
    return installDb_4_6_4()
        && $module->registerHook('actionObjectCmsAddAfter')
        && $module->registerHook('actionObjectCmsUpdateAfter')
        && $module->registerHook('actionObjectCmsDeleteAfter')
        && $module->registerHook('actionObjectCategoryAddAfter')
        && $module->registerHook('actionObjectCategoryUpdateAfter')
        && $module->registerHook('actionObjectCategoryDeleteAfter');
}

/**
 * Install database structure for module version 4.6.4.
 *
 * Drops the old `doofinder_product` table (used only for products)
 * and creates a new `doofinder_updates` table to handle multiple item types.
 *
 * @return bool true if the new table is created successfully, false otherwise
 */
function installDb_4_6_4()
{
    Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'doofinder_product`');

    return Db::getInstance()->execute(
        '
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'doofinder_updates` (
                `id_doofinder_update` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL,
                `object` varchar(45) NOT NULL,
                `id_object` INT(10) UNSIGNED NOT NULL,
                `action` VARCHAR(45) NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_doofinder_update`),
                CONSTRAINT uc_shop_update UNIQUE KEY (id_shop,object,id_object)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
    );
}
