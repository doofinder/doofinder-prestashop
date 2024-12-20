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

namespace PrestaShop\Module\Doofinder\Src\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderConstants
{
    // Feel free to change this value to your own local env or ngrok
    const DOOMANAGER_REGION_URL = 'https://%sadmin.doofinder.com';
    const DOOPLUGINS_REGION_URL = 'https://%splugins.doofinder.com';
    const DOOPHOENIX_REGION_URL = 'https://%ssearch.doofinder.com';
    const GS_SHORT_DESCRIPTION = 1;
    const GS_LONG_DESCRIPTION = 2;
    const VERSION = '4.11.0';
    const NAME = 'doofinder';
    const YES = 1;
    const NO = 0;
}
