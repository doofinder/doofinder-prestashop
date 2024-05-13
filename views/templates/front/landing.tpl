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

{extends file=$layout}

{block name='content'}
    <div class="container">
        <section class="page_content mb-1 card card-block">
            <div id="js-product-list-header">
                <div class="mb-2 mt-2">
                    <h1 class="h1 text-center text-xs-center">{$title|escape:'html':'UTF-8'}</h1>
                </div>
            </div>

            <section id="products">
                {foreach $blocks as $block}
                    {if $block['products']|count}
                        <div class="mb-1 mt-2 df-block-above">
                            {$block['above']|cleanHtml nofilter}
                        </div>
                        <div id="js-product-list">
                            {include file="module:doofinder/views/templates/front/catalog/_partials/productlist.tpl" products=$block['products'] cssClass="row" productClass="col-xs-6 col-xl-3"}
                        </div>
                        <div class="mb-2 mt-1 df-block-below">
                            {$block['below']|cleanHtml nofilter}
                        </div>
                    {else}
                        <div id="js-product-list">
                        {capture assign="errorContent"}
                            <h4>{l s='No products available yet' d='Shop.Theme.Catalog'}</h4>
                            <p>{l s='Stay tuned! More products will be shown here as they are added.' d='Shop.Theme.Catalog'}</p>
                        {/capture}

                        {include file='errors/not-found.tpl' errorContent=$errorContent}
                        </div>
                    {/if}
                {/foreach}
            </section>
        </section>
    </div>
{/block}
