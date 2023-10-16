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

switch (Tools::getValue('type')) {
    case 'category':
        require dirname(__FILE__) . '/feeds/category.php';
        break;

    case 'page':
        require dirname(__FILE__) . '/feeds/cms.php';
        break;

    case 'product':
    default:
        require dirname(__FILE__) . '/feeds/product.php';
        break;
}
