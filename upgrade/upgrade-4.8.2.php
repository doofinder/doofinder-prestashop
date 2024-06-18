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

require_once _PS_MODULE_DIR_ . 'doofinder/lib/doofinder_api_unique_script.php';

function upgrade_module_4_8_2($module)
{
    $installationId = Configuration::get('DF_INSTALLATION_ID');
    $region = Configuration::get('DF_REGION');
    $apiKey = Configuration::get('DF_API_KEY');

    $apiModule = new DoofinderApiUniqueScript($installationId, $region, $apiKey);
    $apiModule->set_unique_script_flag();

    return Configuration::updateValue('DF_UNIQUE_SCRIPT', true);
}
