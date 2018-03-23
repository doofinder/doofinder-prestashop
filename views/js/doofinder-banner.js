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
$(document).on('ready', function() {
    if(typeof doofinderAppendAfterBanner !== undefined
            && typeof doofinderAppendAfterBanner != 'undefined'
            && doofinderAppendAfterBanner.length !== undefined
            && doofinderAppendAfterBanner.length > 0 && $(doofinderAppendAfterBanner).length !== undefined){
        $(doofinderAppendAfterBanner).after($('.doofinder_dinamic_banner'));
        $('.doofinder_dinamic_banner').show();
    }
});