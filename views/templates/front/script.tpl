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
{if isset($search_engine_id) && $search_engine_id}
  <!-- START OF DOOFINDER SCRIPT -->
  <script async="" src="https://eu1-search.doofinder.com/5/script/{$search_engine_id|escape:'htmlall':'UTF-8'}.js"></script>
  <!-- END OF DOOFINDER SCRIPT -->
{/if}
  <!-- TO REGISTER CLICKS -->
<script>
{if isset($productLinks)}
  var dfProductLinks = {html_entity_decode(json_encode($productLinks)|escape:'htmlall':'UTF-8')};
  var dfLinks = Object.keys(dfProductLinks);
{/if}
{if isset($doofinder_banner_append)}
  var doofinderAppendAfterBanner = "{$doofinder_banner_append|escape:'htmlall':'UTF-8'}";
{/if}
  var doofinderQuerySelector = "{$doofinder_search_selector|escape:'htmlall':'UTF-8'}";
</script>  
  <!-- END OF TO REGISTER CLICKS -->
