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

<div class="panel">
	<div class="doofinder-content">
		<div class="row">
			<div class="col-md-12">
				<h4>{l s='Feed URLs to use on Doofinder Admin panel' mod='doofinder'}</h4>
				<dl>
					{foreach from=$df_feed_urls item=feed_url}
						<dt>{l s='Data feed URL for ' mod='doofinder'} [{$feed_url.lang|escape:'htmlall':'UTF-8'}]<dt>
						<dd><a href="{html_entity_decode($feed_url.url|escape:'htmlall':'UTF-8')}" target="_blank">{$feed_url.url|escape:'htmlall':'UTF-8'}</a></dd>
					{/foreach}
				</dl>
			</div>
		</div>
	</div>
</div>
