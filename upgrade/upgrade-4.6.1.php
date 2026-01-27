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
