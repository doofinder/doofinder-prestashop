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
    <div class="module_{$d_type_message|escape:'htmlall':'UTF-8'} alert alert-{$d_type_alert|escape:'htmlall':'UTF-8'}" >
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {if isset($d_raw) && $d_raw}
            {html_entity_decode($d_raw|escape:'htmlall':'UTF-8')}            
        {elseif isset($d_link) && $d_link}
            <a href="{$d_link|escape:'htmlall':'UTF-8'}">
            {$d_message|escape:'htmlall':'UTF-8'}
            </a>
        {else}
            {$d_message|escape:'htmlall':'UTF-8'}
        {/if}
    </div>
</div>
