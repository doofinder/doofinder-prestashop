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

function upgrade_module_4_3_1($module)
{
    $current_admin_endpoint = Configuration::getGlobalValue('DF_AI_ADMIN_ENDPOINT');
    $admin_endpoint = str_replace('app', 'admin', $current_admin_endpoint);

    return Configuration::updateGlobalValue('DF_AI_ADMIN_ENDPOINT', $admin_endpoint);
}
