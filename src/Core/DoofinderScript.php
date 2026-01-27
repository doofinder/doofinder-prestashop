<?php
/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */

namespace PrestaShop\Module\Doofinder\Core;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DoofinderScript
{
    /**
     * Determines if the Doofinder search layer must be initialized based on the device type (mobile or desktop) and configuration settings.
     *
     * This function checks if the search layer should be displayed for the current user context (mobile or desktop) by
     * retrieving the relevant configuration values. It ensures compatibility with older versions of PrestaShop where the
     * 'isMobile' method may not be directly available in the context, using a fallback for mobile detection.
     *
     * @return bool returns `true` if the search layer should be initialized (based on the device type and configuration), `false` otherwise
     */
    public static function searchLayerMustBeInitialized()
    {
        $displayGeneral = (bool) \Configuration::get('DF_SHOW_LAYER', null, null, null, true);
        if (\Configuration::hasKey('DF_SHOW_LAYER_MOBILE', null, null, null)) {
            $displayMobile = (bool) \Configuration::get('DF_SHOW_LAYER_MOBILE', null, null, null, true);
        } else {
            $displayMobile = true;
        }
        $context = \Context::getContext();
        // PrestaShop 1.5 isn't responsive anyway
        $isMobile = false;
        if (method_exists($context, 'isMobile')) {
            $isMobile = $context->isMobile();
        } elseif (method_exists($context, 'getMobileDetect')) {
            $isMobile = $context->getMobileDetect()->isMobile();
        }

        return $displayGeneral && (!$isMobile || $displayMobile);
    }

    /**
     * Gets the script for the Livelayer search layer according to the PrestaShop version.
     *
     * @return string
     */
    public static function getSingleScriptPath($modulePath)
    {
        /*
         * Loads different cart handling assets depending on the version of PrestaShop used
         * (uses different javascript implementations for this purpose in PrestaShop 1.6.x and 1.7.x)
         */
        if (version_compare(_PS_VERSION_, '1.7', '<') === true) {
            return $modulePath . 'views/js/add-to-cart/doofinder-add_to_cart_ps16.js';
        }

        return $modulePath . 'views/js/add-to-cart/doofinder-add_to_cart_ps17.js';
    }
}
