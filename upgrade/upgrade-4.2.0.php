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
