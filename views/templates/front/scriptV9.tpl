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
  <script>
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
        productID: checkIfCartItemHasVariation(event.detail) ? event.detail.grouping_id : event.detail.item_id,
        customizationID: checkIfCartItemHasVariation(event.detail) ? sanitizeVariationID(event.detail.item_id) :
          0,
        /* If there are no combinations, the value will be 0 */
        quantity: event.detail.amount,
        statusPromise: event.detail.statusPromise,
        itemLink: event.detail.link,
        group_id: event.detail.group_id
      });
    });

    (function(w, k) {
      if (typeof w[k] !== 'function') {
        w[k] = function() {
          (w[k].q = w[k].q || []).push(arguments);
        };
      }
    })(window, 'doofinderApp');

    doofinderApp('config', 'currency', {$currency|escape:'htmlall':'UTF-8'});
    doofinderApp('config', 'language', {$lang|escape:'htmlall':'UTF-8'});
    doofinderApp('config', 'zone', {$df_region|escape:'htmlall':'UTF-8'});
  </script>

  <script
    src="https://{$df_region|escape:'htmlall':'UTF-8'}-df-store-config-files.s3.amazonaws.com/{$installation_ID|escape:'htmlall':'UTF-8'}.js"
    async></script>
{/if}