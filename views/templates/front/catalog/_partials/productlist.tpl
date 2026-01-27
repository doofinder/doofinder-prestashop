{*
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 *}

{capture assign="productClasses"}{if !empty($productClass)}{$productClass}{else}col-xs-6 col-xl-4{/if}{/capture}

<div style="justify-content:center" class="products{if !empty($cssClass)} {$cssClass|escape:'javascript':'UTF-8'}{/if}">
    {foreach from=$products item="product" key="position"}
        {include file="catalog/_partials/miniatures/product.tpl" product=$product position=$position productClasses=$productClasses}
    {/foreach}
</div>
