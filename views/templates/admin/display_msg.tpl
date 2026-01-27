{*
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 *}
<div class="bootstrap">
    <div class="module_{$d_type_message|escape:'htmlall':'UTF-8'} alert alert-{$d_type_alert|escape:'htmlall':'UTF-8'}" >
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {if isset($d_raw) && $d_raw}
            {html_entity_decode($d_message|escape:'htmlall':'UTF-8')}            
        {elseif isset($d_link) && $d_link}
            <a href="{$d_link|escape:'htmlall':'UTF-8'}">
            {$d_message|escape:'htmlall':'UTF-8'}
            </a>
        {else}
            {$d_message|escape:'htmlall':'UTF-8'}
        {/if}
    </div>
</div>
