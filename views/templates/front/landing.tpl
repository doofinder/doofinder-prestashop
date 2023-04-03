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
        <section class="page_content mb-1">
            <div id="js-product-list-header">
                <div class="block-category card card-block">
                    <h1 class="h1">{$title|escape:'html':'UTF-8'}</h1>
                    <div class="block-category-inner">
                        <div id="category-description" class="text-muted">{$description|escape:'html':'UTF-8'}</div>
                    </div>
                </div>
            </div>

            <section id="products">
                {if $products|count}
                    {include file="catalog/_partials/productlist.tpl" products=$products cssClass="row" productClass="col-xs-6 col-xl-3"}
                {else}
                    <div id="js-product-list">
                      {capture assign="errorContent"}
                        <h4>{l s='No products available yet' d='Shop.Theme.Catalog'}</h4>
                        <p>{l s='Stay tuned! More products will be shown here as they are added.' d='Shop.Theme.Catalog'}</p>
                      {/capture}

                      {include file='errors/not-found.tpl' errorContent=$errorContent}
                    </div>
                {/if}
            </section>
        </section>
    </div>
{/block}
