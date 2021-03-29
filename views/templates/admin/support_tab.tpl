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
	<div class="row doofinder-header">
		<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_logo.png" class="col-xs-6 col-md-4 text-center" id="payment-logo" />
		<div class="col-xs-6 col-md-4 text-center">
			<h4>{l s='Add a smart search engine to your e-commerce in 5 minutes and with no programming' mod='doofinder'}</h4>
			<h4>{l s='Doofinder increases sales because your customers search and find the most relevant results' mod='doofinder'}</h4>
		</div>
		<div class="col-xs-12 col-md-4 text-center">
			<a href="https://www.doofinder.com/signup/?mktcod=PSHOP&utm_source=prestashop_module&utm_campaign=support_tab&utm_content=prestashop_module_support_tab" target="_blank" class="btn btn-primary" id="create-account-btn">{l s='Create an account now!' mod='doofinder'}</a><br />
			{l s='Already have an account?' mod='doofinder'}<a href="https://app.doofinder.com/admin/login" target="_blank"> {l s='Log in' mod='doofinder'}</a>
		</div>
	</div>

	<hr />
	
	<div class="doofinder-content">
		<div class="row">
			<div class="col-md-12">
				<h3>{l s='Need help configuring your search engine?' mod='doofinder'}</h3>
				<dl>
					<dt>&middot; {l s='How to install Doofinder in PrestaShop 1.5-1.7' mod='doofinder'}</dt>
					<dd><a href="https://www.doofinder.com/support/plugins/prestashop-15" target="_blank">https://www.doofinder.com/support/plugins/prestashop-15</a></dd>
					
					<dt>&middot; {l s='Products are displayed without VAT in PrestaShop' mod='doofinder'}</dt>
					<dd><a href="https://www.doofinder.com/support/troubleshooting/products-are-displayed-without-vat-in-prestashop" target="_blank">https://www.doofinder.com/support/troubleshooting/products-are-displayed-without-vat-in-prestashop</a></dd>
					
					<dt>&middot; {l s='Customize the Doofinder Layer Look & Feel' mod='doofinder'}</dt>
					<dd><a href="https://www.doofinder.com/support/layer-customization/custom-look-n-feel" target="_blank">https://www.doofinder.com/support/troubleshooting/products-are-displayed-without-vat-in-prestashop</a></dd>
					
					<dt>&middot; {l s='All documentation in one place!' mod='doofinder'}</dt>
					<dd><a href="https://www.doofinder.com/support/" target="_blank">https://www.doofinder.com/support/</a></dd>

					<dt>&middot; {l s='Code example to paste on your Doofinder dashboard to use Embedded layer instead of API on your PrestaShop native search results page' mod='doofinder'}</dt>
					<dd><a href="https://gist.github.com/danidomen/71f03ad80ac58e22af94bf479675f521" target="_blank">GitHub</a></dd>

					<dt>&middot; {l s='You can debug or disable some options on the hidden advanced tab of the module. Caution: Use only if you are a experienced user!!' mod='doofinder'}</dt>
					<dd><a href="{html_entity_decode($adv_url|escape:'htmlall':'UTF-8')}">{l s='Enable advanced module tab options' mod='doofinder'}</a></dd>
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
			<div class="col-md-12 text-center">
				<a href="https://www.doofinder.com/signup/?mktcod=PSHOP&utm_source=prestashop_module&utm_campaign=support_tab&utm_content=prestashop_module_free_trial_link" target="_blank" class="btn btn-primary btn-lg" style="font-size:27px;background-color: #27c356" id="create-account-btn">{l s='Create a free acount!' mod='doofinder'}</a>
			</div>
		</div>
	</div>
</div>
