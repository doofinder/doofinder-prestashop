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
    if (dfLinks.length) {
        $('a').click(function() {
            var link = $(this);
            var href = $(this).attr('href');
            var dfLayer;
            if (typeof (dfClassicLayers) != 'undefined') {
                dfLayer = dfClassicLayers[0];
            }
            else if (typeof (dfFullscreenLayers) != 'undefined') {
                dfLayer = dfFullscreenLayers[0];
            }
            dfLinks.forEach(function(item) {
                if (href.indexOf(item) > -1 && typeof (dfLayer) != 'undefined') {
                    var hashid = dfLayer.layerOptions.hashid;
                    var cookie = Cookies.getJSON('doofhit' + hashid);
                    var query = cookie.query;
                    dfLayer.controller.registerClick(dfProductLinks[item], {
                        "query": query
                    });
                }
            });
        });
    }
});