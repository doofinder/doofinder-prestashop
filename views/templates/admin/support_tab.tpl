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
	<div class="row doofinder-header text-center pb-5 mb-5">
		<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_logo.png"  id="payment-logo" />
	</div>
	<div class="row" style="margin-top: 2em;">
		<div class="col-md-12 text-center">
			<h3 style="background-color:  #f4f4f4 ; text-transform:uppercase;">{l s='Need help configuring your search engine?' mod='doofinder'}</h3>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12 text-center">
			<h2><strong>{l s='All documentation in one place!' mod='doofinder'} </strong><a href="https://support.doofinder.com/">https://support.doofinder.com/</a></h2>
		</div>
	</div>
		
	<div class="doofinder-content">
		<div class="row" style="margin-top: 2em;">
			<div class="col-md-12">
				<dl>
					<dt>&middot; {l s='Understand how the product feed works to display results in the Doofinder search layer' mod='doofinder'}</dt>
					<dd><a href="https://support.doofinder.com/managing-data/the-product-data-feed.html" target="_blank">Visitar página</a></dd>
					
					<dt>&middot; {l s='How to add information in the Doofinder search layer' mod='doofinder'}</dt>
					<dd><a href="https://support.doofinder.com/layers/appearance.html" target="_blank">Visitar página</a></dd>
					
					<dt>&middot; {l s='How to configure the search layer filters' mod='doofinder'}</dt>
					<dd><a href="https://support.doofinder.com/managing-results/filters-configuration" target="_blank">Visitar página</a></dd>

					<dt>&middot; {l s='Learn the basics about Live Layer' mod='doofinder' mod='doofinder'}</dt>
					<dd><a href="https://support.doofinder.com/layers/live-layer-basics.html" target="_blank">Visitar página</a></dd>

				</dl>
			</div>
		</div>

		<hr />

		<div class="row">
			<div class="col-md-12">
				<h4>{l s='Or contact directly with us. We will be glad to help you' mod='doofinder'}</h4>
				<dl>
					<dt>&middot; {l s='Support email' mod='doofinder'}</dt>
					<dd><a href="mailto:support@doofinder.com" target="_blank">support@doofinder.com</a></dd>
				</dl>
			</div>
		</div>
		<div class="row">
					<span><strong>&middot; {l s='You can debug or disable some options on the hidden advanced tab of the module. Caution: Use only if you are a experienced user!!' mod='doofinder'}</strong></span><br />
					<span><a href="{html_entity_decode($adv_url|escape:'htmlall':'UTF-8')}">{l s='Enable advanced module tab options' mod='doofinder'}</a></span>
		</div>
	</div>
</div>
