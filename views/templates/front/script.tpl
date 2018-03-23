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
{if isset($script_html)}
  <!-- START OF DOOFINDER SCRIPT -->
  {($script_html|escape:'html':'UTF-8'|html_entity_decode:$smarty.const.ENT_QUOTES:'utf-8') nofilter}
  <!-- END OF DOOFINDER SCRIPT -->
{/if}

{if isset($extra_css_html)}
  <!-- START OF DOOFINDER CSS -->
  {$extra_css_html|escape:'html':'UTF-8'|html_entity_decode:$smarty.const.ENT_QUOTES:'utf-8' nofilter}
  <!-- END OF DOOFINDER CSS -->
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
  
{if isset($script_debug_html)}
  <!-- START OF DOOFINDER SCRIPT DEBUG -->
  {($script_debug_html|escape:'html':'UTF-8'|html_entity_decode:$smarty.const.ENT_QUOTES:'utf-8') nofilter}
  <!-- END OF DOOFINDER SCRIPT DEBUG -->
{/if}
