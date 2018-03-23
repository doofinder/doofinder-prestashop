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
{if isset($script)}
  <!-- START OF DOOFINDER SCRIPT -->
  {($script|escape:'html':'UTF-8'|html_entity_decode:$smarty.const.ENT_QUOTES:'utf-8')}
  <!-- END OF DOOFINDER SCRIPT -->
{/if}

{if isset($extra_css)}
  <!-- START OF DOOFINDER CSS -->
  {$extra_css|escape:'html':'UTF-8'|html_entity_decode:$smarty.const.ENT_QUOTES:'utf-8'}
  <!-- END OF DOOFINDER CSS -->
{/if}
  <!-- TO REGISTER CLICKS -->
{if isset($productLinks)}
<script>
  var dfProductLinks = {html_entity_decode(json_encode($productLinks)|escape:'htmlall':'UTF-8')};
  var dfLinks = Object.keys(dfProductLinks);
  var doofinderAppendAfterBanner = "{$doofinder_banner_append|escape:'htmlall':'UTF-8'}";
</script>  
{/if}
  <!-- END OF TO REGISTER CLICKS -->
  
{if isset($script_debug)}
  <!-- START OF DOOFINDER SCRIPT DEBUG -->
  {($script_debug|escape:'html':'UTF-8'|html_entity_decode:$smarty.const.ENT_QUOTES:'utf-8')}
  <!-- END OF DOOFINDER SCRIPT DEBUG -->
{/if}
