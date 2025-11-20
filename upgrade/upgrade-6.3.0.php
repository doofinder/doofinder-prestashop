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

use PrestaShop\Module\Doofinder\Configuration\DoofinderConfig;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades the Doofinder module to version 6.3.0.
 *
 * This upgrade removes the landing pages feature which has been deprecated.
 * It drops the doofinder_landing table that was used to cache landing page data.
 *
 * @param Doofinder $module the Doofinder module instance being upgraded
 *
 * @return bool true on success, false if an error occurs
 */
function upgrade_module_6_3_0($module)
{
    DoofinderConfig::debug('Initiating 6.3.0 upgrade - Removing landing pages feature');

    $result = Db::getInstance()->execute(
        'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'doofinder_landing`'
    );

    if ($result) {
        DoofinderConfig::debug('Landing pages table dropped successfully.');
    } else {
        DoofinderConfig::debug('Failed to drop landing pages table.');
    }

    return $result;
}
