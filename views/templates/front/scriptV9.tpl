{*
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 * @see       https://opensource.org/licenses/MIT
 *}
{if isset($installation_ID) && $installation_ID}
  <!-- START OF DOOFINDER ADD TO CART SCRIPT -->
  <script>
    let item_link;
    document.addEventListener('doofinder.cart.add', function(event) {

      item_link = event.detail.link;

      const checkIfCartItemHasVariation = (cartObject) => {
        return (true !== cartObject.group_leader && cartObject.item_id === cartObject.grouping_id) ? false : true;
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
    if ('undefined' !== typeof klCustomer && null !== klCustomer && "" !== klCustomer.email) {
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
    doofinderApp("config", "hidePrices", {$customer_group_hide_prices|escape:'htmlall':'UTF-8'});
    {/if}
  </script>
  <script src="{$config_script_base_url|escape}/{$installation_ID|escape:'htmlall':'UTF-8'}.js" async></script>
  <!-- END OF DOOFINDER UNIQUE SCRIPT -->

  {if isset($df_ps_contextual_prices_enabled) && $df_ps_contextual_prices_enabled}
  <!-- START OF DOOFINDER CONTEXTUAL PRICES -->
  <script data-keepinline>
    window.dfPsContextualPricesEnabled = true;
  </script>
  <script src="https://cdn.doofinder.com/contextual-prices/contextual-prices.js" defer></script>
  <script src="https://cdn.doofinder.com/plugins/prestashop.js" defer></script>
  <!-- END OF DOOFINDER CONTEXTUAL PRICES -->
  {/if}
{/if}
