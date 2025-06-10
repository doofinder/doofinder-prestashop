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
    document.addEventListener('doofinder.cart.add', function(event) {

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

  <!-- START OF DOOFINDER UNIQUE SCRIPT -->
  <script data-keepinline>
    {literal}
    (function(w, k) {w[k] = window[k] || function () { (window[k].q = window[k].q || []).push(arguments) }})(window, "doofinderApp")
    {/literal}

    // Custom personalization:
    doofinderApp("config", "language", "{$lang|escape:'htmlall':'UTF-8'}");
    doofinderApp("config", "currency", "{$currency|escape:'htmlall':'UTF-8'}");
  </script>
  <script src="https://{$df_region|escape:'htmlall':'UTF-8'}-config.doofinder.com/2.x/{$installation_ID|escape:'htmlall':'UTF-8'}.js" async></script>
  <!-- END OF DOOFINDER UNIQUE SCRIPT -->

  <!-- START INTEGRATION WITH KLAVIYO -->
  <script>
    window.addEventListener('load', async (event) => {
      if ('undefined' !== typeof klaviyo && 'undefined' !== typeof klCustomer && true === await klaviyo.isIdentified() && klCustomer && "" !== klCustomer.email) {
        const companyId = await klaviyo.account();
        let userId = window.localStorage.getItem('df-random-userid');
        userId = JSON.parse(userId);
        
        klaviyo.identify({
            "email": klCustomer.email
        });

        try {
          const response = await fetch('https://a.klaviyo.com/client/profiles?company_id=' + companyId, {
            method: 'POST',
            headers: {
              accept: 'application/vnd.api+json',
              revision: '2025-01-15',
              'content-type': 'application/vnd.api+json'
            },
            body: JSON.stringify({
              data: {
                type: "profile",
                attributes: {
                  email: klCustomer.email,
                  external_id: userId
                }
              }
            })
          });

          if (!response.ok) {
            console.error('Failed to send data to Klaviyo:', await response.text());
          }
        } catch (error) {
          console.error('Failed to send data to Klaviyo:', error);
        }
      }
    });
  </script>
  <!-- END INTEGRATION WITH KLAVIYO -->
{/if}
