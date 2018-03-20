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
  {$script|html_entity_decode:2:"UTF-8" nofilter}
  <!-- END OF DOOFINDER SCRIPT -->
{/if}

{if isset($extra_css)}
  <!-- START OF DOOFINDER CSS -->
  {$extra_css}
  <!-- END OF DOOFINDER CSS -->
{/if}
  <!-- TO REGISTER CLICKS -->
{if isset($productLinks)}
<script>
  var dfProductLinks = {$productLinks|json_encode nofilter};
  var dfLinks = Object.keys(dfProductLinks);
  var doofinderAppendAfterBanner = "{$doofinder_banner_append}";
</script>  
{/if}
  <!-- END OF TO REGISTER CLICKS -->
  
{if isset($script_debug)}
  <!-- START OF DOOFINDER SCRIPT DEBUG -->
  {$script_debug|html_entity_decode:2:"UTF-8" nofilter}
  <!-- END OF DOOFINDER SCRIPT DEBUG -->
{/if}
