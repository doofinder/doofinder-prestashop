{*
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
*}
{if isset($installation_ID) && $installation_ID}
<!-- START OF DOOFINDER SCRIPT -->
  <script>
    const dfLayerOptions = {
      installationId: "{$installation_ID|escape:'htmlall':'UTF-8'}",
      zone: "{$df_region|escape:'htmlall':'UTF-8'}",
      language: "{$lang|escape:'htmlall':'UTF-8'}",
      currency: "{$currency|escape:'htmlall':'UTF-8'}"
    };
    (function (l, a, y, e, r, s) {
      r = l.createElement(a); r.onload = e; r.async = 1; r.src = y;
      s = l.getElementsByTagName(a)[0]; s.parentNode.insertBefore(r, s);
    })(document, 'script', 'https://cdn.doofinder.com/livelayer/1/js/loader.min.js', function () {
      doofinderLoader.load(dfLayerOptions);
    });

    document.addEventListener('doofinder.cart.add', function(event) {

      const checkIfCartItemHasVariation = (cartObject) => {
        return (cartObject.item_id === cartObject.grouping_id) ? false : true;
      };

      /**
      * Returns only ID from string
      */
      const sanitizeVariationID = (variationID) => {
        return variationID.replace(/\D/g, "")
      };

      doofinderManageCart({
        cartURL          : "{if isset($urls)}{$urls.pages.cart|escape:'htmlall':'UTF-8'}{/if}",  /* required for prestashop 1.7, in previous versions it will be empty. */
        cartToken        : "{$static_token|escape:'htmlall':'UTF-8'}",
        productID        : checkIfCartItemHasVariation(event.detail) ? event.detail.grouping_id : event.detail.item_id,
        customizationID  : checkIfCartItemHasVariation(event.detail) ? sanitizeVariationID(event.detail.item_id) : 0,   /* If there are no combinations, the value will be 0 */
        quantity         : event.detail.amount,
        statusPromise    : event.detail.statusPromise,
        itemLink         : event.detail.link
      });
    });
  </script>
<!-- END OF DOOFINDER SCRIPT -->
{/if}
