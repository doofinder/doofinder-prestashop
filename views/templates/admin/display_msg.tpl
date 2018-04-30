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
<div class="bootstrap">
    <div class="module_{$type_message|escape:'htmlall':'UTF-8'} alert alert-{$type_alert|escape:'htmlall':'UTF-8'}" >
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {if isset($link) && $link}
            <a href="{$link|escape:'htmlall':'UTF-8'}">
            {$message|escape:'htmlall':'UTF-8'}
            </a>
        {else}
            {$message|escape:'htmlall':'UTF-8'}
        {/if}
    </div>
</div>