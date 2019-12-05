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
<script type="text/javascript">
    var doofinder_token = '{$doofinder_token|escape:'htmlall':'UTF-8'}';
</script>
<style type="text/css">
    .doofinder_dinamic_banner img{
        width:100%;
    }
    .row.sort-by-row{
        display:none;
    }
    #doofinder_original_search_query{
        display:none;
    }
</style>
{if isset($doofinder_banner_image) && $doofinder_banner_image}
<div class="doofinder_dinamic_banner" style="display:none" data-doofinder_banner_id="{$doofinder_banner_id|escape:'htmlall':'UTF-8'}">
    <a href="{$doofinder_banner_link|escape:'htmlall':'UTF-8'}" {if $doofinder_banner_blank}target="_blank"{/if}><img src="{$doofinder_banner_image|escape:'htmlall':'UTF-8'}" /></a>
</div>
{/if}
{if isset($search_query) && $search_query}
<input type="text" id="doofinder_original_search_query" value="{$search_query|escape:'htmlall':'UTF-8'}" />
{/if}

