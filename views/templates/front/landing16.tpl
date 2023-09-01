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


{include file="$tpl_dir./errors.tpl"}
<h1 class="page-heading product-listing text-center">{$title|escape:'html':'UTF-8'}</h1>
<div>
    {foreach $blocks as $block}
        {if $block['products']|count}
            <div class="df-block-above main-page-indent">
                {$block['above']|cleanHtml nofilter}
            </div>
            {include file="$tpl_dir./product-list.tpl" products=$block['products']}
            <div class="df-block-below main-page-indent">
                {$block['below']|cleanHtml nofilter}
            </div>
        {/if}
    {/foreach}
</div>
