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
<h1 class="page-heading product-listing">{$title|escape:'html':'UTF-8'}</h1>
<div class="block-category-inner">
    <div id="category-description" class="text-muted">{$description|escape:'html':'UTF-8'}</div>
</div>
{include file="$tpl_dir./product-list.tpl" products=$search_products}
