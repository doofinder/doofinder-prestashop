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

require_once dirname(__FILE__) . '/../../config/config.inc.php';

if (Tools::isSubmit('landing')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return Db::getInstance()->delete('doofinder_landing');
    }
    exit;
}
