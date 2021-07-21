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
  </script>
<!-- END OF DOOFINDER SCRIPT -->
{/if}
