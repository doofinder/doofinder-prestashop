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
    const DOOMANAGER_REGION_URL = '${DOOMANAGER_REGION_URL}';
    const DOOPLUGINS_REGION_URL = '${DOOPLUGINS_REGION_URL}';
    const DOOPHOENIX_REGION_URL = '${DOOPHOENIX_REGION_URL}';
    const GS_SHORT_DESCRIPTION = 1;
    const GS_LONG_DESCRIPTION = 2;
    const VERSION = '5.1.15';
    const NAME = 'doofinder';
    const YES = 1;
    const NO = 0;
}
