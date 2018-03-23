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
<!-- Doofinder Block layered navigation module -->
<script type="text/javascript">
var current_friendly_url = '#';
var param_product_url = '';
var df_query_name = '{$df_query_name|escape:'htmlall':'UTF-8'}';
</script>
{if $nbr_filterBlocks != 0}
<div id="layered_block_left" class="block">
	<div class="block_content">
		<form action="#" id="layered_form">
			<div>
				
                                {foreach from=$facets item=facet}

                                    {if isset($facet._type)}
                                        {if isset($facet.ranges)}
                                        <div class="layered_price">
                                        {else}
                                        <div class="layered_filter {if !isset($facet.terms) OR $facet.terms|@count lt 1}hidden{/if}">
                                        {/if}
                                            <span class="layered_subtitle">{$options[$facet@key]|escape:'htmlall':'UTF-8'}</span>
                                            <span class="layered_close"><a href="#" data-rel="ul_layered_{$facet._type|escape:'htmlall':'UTF-8'}_{$facet@key|escape:'htmlall':'UTF-8'}">v</a></span>
                                            <div class="clear"></div>
                                            <ul id="ul_layered_{$facet._type|escape:'htmlall':'UTF-8'}_{$facet@key|escape:'htmlall':'UTF-8'}">
                                            {if !isset($facet.ranges) && $facet._type == 'terms'}
                                                {foreach from=$facet.terms key=id_value item=value name=fe}

                                                    {if $value.count}
                                                    <li class="nomargin {*if $smarty.foreach.fe.index >= $filter.filter_show_limit}hiddable{/if*}">

                                                            <input type="checkbox" class="checkbox" name="layered_{$facet._type|escape:'htmlall':'UTF-8'}_{$facet@key|escape:'htmlall':'UTF-8'}[]" id="layered_{$facet._type|escape:'htmlall':'UTF-8'}_{$facet@key|escape:'htmlall':'UTF-8'}_{$id_value|escape:'htmlall':'UTF-8'}" value="{$value.term|escape:'htmlall':'UTF-8'}"{if $value.selected} checked="checked"{/if}{if !$value.count} disabled="disabled"{/if} /> 

                                                            <label for="layered_{$facet._type|escape:'htmlall':'UTF-8'}_{$facet@key|escape:'htmlall':'UTF-8'}_{$id_value|escape:'htmlall':'UTF-8'}"{if !$value.count} class="disabled"{/if}>
                                                                    {if !$value.count}
                                                                    {$value.term|escape:'htmlall':'UTF-8'} <span> ({$value.count|escape:'htmlall':'UTF-8'})</span>
                                                                    {else}
                                                                    <a href="{$value.term|escape:'htmlall':'UTF-8'}" data-rel="{$value.term|escape:'htmlall':'UTF-8'}">{$value.term|escape:html:'UTF-8'} <span> ({$value.count|escape:'htmlall':'UTF-8'})</span></a>
                                                                    {/if}
                                                            </label>
                                                    </li>
                                                    {/if}
                                                {/foreach}
                                            {else}
                                                <span id="layered_{$facet@key|escape:'htmlall':'UTF-8'}_range"></span>
								<div class="layered_slider_container">
									<div class="layered_slider" id="layered_{$facet@key|escape:'htmlall':'UTF-8'}_slider"></div>
								</div>
								<script type="text/javascript">
								{literal}
									var filterRange = {/literal}{$facet.ranges[0].max|string_format:"%.2f"|escape:'htmlall':'UTF-8'}-{$facet.ranges[0].min|string_format:"%.2f"|escape:'htmlall':'UTF-8'}{literal};
									var step = filterRange / 100;
									if (step > 1)
										step = parseInt(step);
									addSlider('{/literal}{$facet@key|escape:'htmlall':'UTF-8'}{literal}',{
										range: true,
										step: step,
										min: {/literal}{$facet.ranges[0].min|string_format:"%.2f"|escape:'htmlall':'UTF-8'}{literal},
										max: {/literal}{$facet.ranges[0].max|string_format:"%.2f"|escape:'htmlall':'UTF-8'}{literal},
										values: [ {/literal}{$facet.ranges[0].min|string_format:"%.2f"|escape:'htmlall':'UTF-8'}{literal}, {/literal}{$facet.ranges[0].max|string_format:"%.2f"|escape:'htmlall':'UTF-8'}{literal}],
										slide: function( event, ui ) {
											stopAjaxQuery();
											{/literal}
	
											{literal}
												from = ui.values[0].toFixed(2)+' {/literal}{*$filter.unit*}€{literal}';
												to = ui.values[1].toFixed(2)+' {/literal}{*$filter.unit*}€{literal}';
											{/literal}
											
                                                                                            
											{literal}
											$('#layered_{/literal}{$facet@key|escape:'htmlall':'UTF-8'}{literal}_range').html(from+' - '+to);
										},
										stop: function () {
											reloadContent();
										}
									}, '{/literal}{*$filter.unit*} €{literal}', {/literal}{*$filter.format*}5{literal});
								{/literal}
								</script>
                                            {/if}
                                            </ul>
                                        </div>
                                    {/if}
                                {/foreach}
				
			</div>
			<input type="hidden" name="id_category_layered" value="0" />
			<input type="hidden" name="search_query" id="doofinder_facets_search_query" value="" />
		</form>
	</div>
	<div id="layered_ajax_loader" style="display: none;">
		<p><img src="{$img_ps_dir|escape:'htmlall':'UTF-8'}loader.gif" alt="" /><br />{l s='Loading...' mod='doofinder'}</p>
	</div>
</div>
{/if}
<!-- /Doofinder Block layered navigation module -->
