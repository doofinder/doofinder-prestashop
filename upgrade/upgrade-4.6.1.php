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
 * Deletes the table used to store landing page data, since this feature was removed.
 *
 * @param Doofinder $module the module instance being upgraded
 *
 * @return bool true if the query executes successfully, false otherwise
 */
function upgrade_module_4_6_1($module)
{
    return Db::getInstance()->delete('doofinder_landing');
}
