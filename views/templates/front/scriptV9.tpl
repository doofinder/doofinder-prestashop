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

      /**
      * Returns only ID from string
      */
      const sanitizeVariationID = (variationID) => {
        return variationID.replace(/\D/g, "")
      }

      const isVariant = String(event.detail.item_id) !== String(event.detail.grouping_id);

      doofinderManageCart({
        cartURL          : "{if isset($urls)}{$urls.pages.cart|escape:'htmlall':'UTF-8'}{/if}",  //required for prestashop 1.7, in previous versions it will be empty.
        cartToken        : "{$static_token|escape:'htmlall':'UTF-8'}",
        productID        : isVariant ? event.detail.grouping_id : event.detail.item_id,
        customizationID  : isVariant ? sanitizeVariationID(event.detail.item_id) : 0,   // With 0, PrestaShop picks the default combination if the product has any
        quantity         : event.detail.amount,
        statusPromise    : event.detail.statusPromise,
        itemLink         : event.detail.link,
        group_id         : event.detail.group_id
      });
    });
  </script>
  <!-- END OF DOOFINDER ADD TO CART SCRIPT -->

  <!-- START OF DOOFINDER SALES SESSION -->
  <script data-keepinline>
    {literal}
    // Copies the Doofinder session to a cookie the module saves with the cart, for the sales. It runs
    // on load and again on every click and form submission, as the first page the loader runs in
    // creates the session after this script.
    (function () {
      function writeSession() {
        try {
          var session = JSON.parse(localStorage.getItem("df-session"));
          var userId = JSON.parse(localStorage.getItem("df-random-userid"));
          if (!session || !userId) return;

          var config = window.Doofinder && window.Doofinder.getConfig ? window.Doofinder.getConfig() : {};
          var value = JSON.stringify({ session_id: session.id, user_id: userId, hashid: config.hashid });
          document.cookie = "doofinder_session=" + encodeURIComponent(value) +
            "; path=/; max-age=86400; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
        } catch (e) {
          // Browsers that block the storage, or a session that isn't valid JSON.
        }
      }

      writeSession();
      document.addEventListener("click", writeSession, true);
      document.addEventListener("submit", writeSession, true);
    })();
    {/literal}
  </script>
  <!-- END OF DOOFINDER SALES SESSION -->

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
    doofinderApp("config", "hidePrices", {$customer_group_hide_prices|escape:'htmlall':'UTF-8'});
    {if $is_customer_group_feature_active && $is_customer_logged}
    doofinderApp("config", "priceName", "{$currency|escape:'htmlall':'UTF-8'}_{$customer.id_default_group|escape:'htmlall':'UTF-8'}");
    {/if}
  </script>
  <script src="{$config_script_base_url|escape}/{$installation_ID|escape:'htmlall':'UTF-8'}.js" async></script>
  <!-- END OF DOOFINDER UNIQUE SCRIPT -->
{/if}
