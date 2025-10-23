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
  <!-- START OF DOOFINDER ADD TO CART SCRIPT -->
  <script>
    let item_link; 
    document.addEventListener('doofinder.cart.add', function(event) {
      
      item_link = event.detail.link;

      const checkIfCartItemHasVariation = (cartObject) => {
        return (cartObject.item_id === cartObject.grouping_id) ? false : true;
      }

      /**
      * Returns only ID from string
      */
      const sanitizeVariationID = (variationID) => {
        return variationID.replace(/\D/g, "")
      }

      doofinderManageCart({
        cartURL          : "{if isset($urls)}{$urls.pages.cart|escape:'htmlall':'UTF-8'}{/if}",  //required for prestashop 1.7, in previous versions it will be empty.
        cartToken        : "{$static_token|escape:'htmlall':'UTF-8'}",
        productID        : checkIfCartItemHasVariation(event.detail) ? event.detail.grouping_id : event.detail.item_id,
        customizationID  : checkIfCartItemHasVariation(event.detail) ? sanitizeVariationID(event.detail.item_id) : 0,   // If there are no combinations, the value will be 0
        quantity         : event.detail.amount,
        statusPromise    : event.detail.statusPromise,
        itemLink         : event.detail.link,
        group_id         : event.detail.group_id
      });
    });
  </script>
  <!-- END OF DOOFINDER ADD TO CART SCRIPT -->

  <!-- START OF DOOFINDER INTEGRATIONS SUPPORT -->
  <script data-keepinline>
    var dfKvCustomerEmail;
    if ('undefined' !== typeof klCustomer && "" !== klCustomer.email) {
      dfKvCustomerEmail = klCustomer.email;
    }
  </script>
  <!-- END OF DOOFINDER INTEGRATIONS SUPPORT -->

  <!-- START OF DOOFINDER UNIQUE SCRIPT -->
  <script data-keepinline>
    {literal}
    (function(w, k) {w[k] = window[k] || function () { (window[k].q = window[k].q || []).push(arguments) }})(window, "doofinderApp")
    {/literal}

    // Custom personalization:
    doofinderApp("config", "language", "{$lang|escape:'htmlall':'UTF-8'}");
    doofinderApp("config", "currency", "{$currency|escape:'htmlall':'UTF-8'}");
    {if $is_customer_group_feature_active && $is_customer_logged}
    doofinderApp("config", "priceName", "{$currency|escape:'htmlall':'UTF-8'}_{$customer.id_default_group|escape:'htmlall':'UTF-8'}");
    doofinderApp("config", "hidePrices", {$customer_group_price_visibility|escape:'htmlall':'UTF-8'});
    {/if}
  </script>
  <script src="https://{$df_region|escape:'htmlall':'UTF-8'}-config.doofinder.com/2.x/{$installation_ID|escape:'htmlall':'UTF-8'}.js" async></script>
  <!-- END OF DOOFINDER UNIQUE SCRIPT -->
{/if}
