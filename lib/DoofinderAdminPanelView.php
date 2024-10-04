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

namespace PrestaShop\Module\Doofinder\Lib;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderAdminPanelView
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function getWarningMultishopHtml()
    {
        $stop = false;
        if (\Shop::getContext() == \Shop::CONTEXT_GROUP || \Shop::getContext() == \Shop::CONTEXT_ALL) {
            $context = \Context::getContext();
            $context->smarty->assign('text_one_shop', $this->module->l('You cannot manage Doofinder from a \'All Shops\' or a \'Group Shop\' context, select directly the shop you want to edit'));
            $stop = $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/message_manage_one_shop.tpl');
        }

        return $stop;
    }

    public static function displayErrorCtm($error, $link = false, $raw = false)
    {
        return self::displayGeneralMsg($error, 'error', 'danger', $link, $raw);
    }

    public static function displayWarningCtm($warning, $link = false, $raw = false)
    {
        return self::displayGeneralMsg($warning, 'warning', 'warning', $link, $raw);
    }

    public static function displayConfirmationCtm($string, $link = false, $raw = false)
    {
        return self::displayGeneralMsg($string, 'confirmation', 'success', $link, $raw);
    }

    public static function displayGeneralMsg($string, $type, $alert, $link = false, $raw = false)
    {
        $context = \Context::getContext();
        $context->smarty->assign(
            [
                'd_type_message' => $type,
                'd_type_alert' => $alert,
                'd_message' => $string,
                'd_link' => $link,
                'd_raw' => $raw,
            ]
        );

        return $context->smarty->fetch(self::getLocalPath() . 'views/templates/admin/display_msg.tpl');
    }

    private static function getLocalPath()
    {
        return _PS_MODULE_DIR_ . DoofinderConstants::NAME . DIRECTORY_SEPARATOR;
    }
}
