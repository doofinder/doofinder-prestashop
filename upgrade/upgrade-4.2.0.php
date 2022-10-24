<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   GPLv3
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_4_2_0($module)
{
    return installDb() &&
        $module->registerHook('actionProductSave') &&
        $module->registerHook('actionProductDelete');
}

function installDb()
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
