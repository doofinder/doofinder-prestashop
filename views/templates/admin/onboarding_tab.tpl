{*
* NOTICE OF LICENSE
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* You must not modify, adapt or create derivative works of this source code
*
* @author Doofinder
* @copyright Doofinder
* @license GPLv3
*}

<div class="panel">
	<div class="doofinder-content">
		<div class="row">
			<div class="col-12">
				<p style="font-size:8px;">
				{if $checkConnection}
					<span class="connection-ball green"></span> {l s='Connection with Doofinder successful.' mod='doofinder'}
				{else}
					<span class="connection-ball red"></span> {l s='You cannot connect with Doofinder. Please contact your server provider to check your web server internet connection or firewall.' mod='doofinder'}
				{/if}
				</p>
			</div>
			<div class="bootstrap message-popup" style="display:none;">
				<div class="module_warning alert alert-warning">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					{l s='You don\'t receive the Api Key or you close too quickly the popup window. Please try again. If
					you think this is an error, please contact our support or try the module manual configuration
					option.' mod='doofinder'}
				</div>
			</div>
			<div class="bootstrap message-error" style="display:none;">
				<div class="module_warning alert alert-danger">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					{l s='An error occurred during the installation process. Please contact our support team on'
					mod='doofinder'} support@doofinder.com
					<ul id="installation-errors"></ul>
				</div>
			</div>
			<div class="col-12 text-center loading-installer" style="display:none;">
				<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_logo.png" />
				<br>
				<ul>
					<li class="active">{l s='Please be patient, we are autoinstalling your definitive search
						solution...' mod='doofinder'}</li>
					<li>{l s='Creating search engines...' mod='doofinder'}</li>
					<li>{l s='Recover data feed of your products...' mod='doofinder'}</li>
					<li>{l s='Creating index to search on your site...' mod='doofinder'}</li>
					<li>{l s='Giving a final magic touch...' mod='doofinder'}</li>
					<li>{l s='Reloading page, please wait...' mod='doofinder'}</li>
				</ul>
			</div>
			<div class="col-md-6 text-center choose-installer">
				<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_logo.png" />
				<br />
				<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_mac.png" />
				<div class="col-md-12">
					<a onclick="javascript:popupDoofinder('signup')" href="#"
						class="btn btn-primary btn-lg btn-doofinder" id="create-account-btn">{l s='Use your free trial
						now!' mod='doofinder'}</a>
				</div>
				<div class="col-md-12" style="margin-top:15px">
					<a onclick="javascript:popupDoofinder('login')" href="#"
						class="btn btn-primary btn-lg btn-doofinder" id="login-account-btn">{l s='I have an account'
						mod='doofinder'}</a>
				</div>
			</div>
			<div class="col-md-6 choose-installer">
				<div class="col-md-8">
					<h2>{l s='Add a smart search engine to your e-commerce in 5 minutes and with no programming'
						mod='doofinder'}</h2>
					<hr />
					<br />
					<p><strong>{l s='ABOUT DOOFINDER' mod='doofinder'}:</strong></p>
					<br />
					<p>{l s='Doofinder provides you with an instant search layer to display your products when your
						visitors use the search bar' mod='doofinder'}.</p>
					<br />
					<p>{l s='Your customers then have the opportunity to preview your products, filter them and choose
						the desired product. Upon hitting enter, doofinder will also power the results page'
						mod='doofinder'}.</p>
					<br />
					<p>{l s='Among our features are' mod='doofinder'}:</p>
					<br />
					<dl>
						<dd>&middot; {l s='Detailed reports on visitor search behaviour' mod='doofinder'}.</dd>
						<dd>&middot; {l s='Faceted search option' mod='doofinder'}.</dd>
						<dd>&middot; {l s='Learning behaviour' mod='doofinder'}.</dd>
						<dd>&middot; {l s='Merchandising power to set a products positioning' mod='doofinder'}.</dd>
						<dd>&middot; {l s='Banner feature for advertising and promoting products, brands and events'
							mod='doofinder'}.</dd>
						<dd>&middot; {l s='Options to redirect users to content pages' mod='doofinder'}.</dd>
						<dd>&middot; {l s='Held on our servers for a faster page load time' mod='doofinder'}.</dd>
					</dl>
					<br />
					<p>{l s='More than 5000 e-commerce sites over 35 countries are already using it' mod='doofinder'}.
					</p>
					<br />
					<p>{l s='What are you waiting for?' mod='doofinder'}</p>
					<br />
					<p><a onclick="javascript:popupDoofinder('signup')" href="#">{l s='Test our solution for 30 days,
							for free!' mod='doofinder'}</a></p>
				</div>
			</div>
		</div>
		<div id="choose-sector" class="row justify-content-md-center" style="display:none">
			<div class="col-md-8 col-md-offset-2">
				<h2 class="mb3">{l s='Select a sector for each store' mod='doofinder'}</h2>
				<form id="sector-form">
					{assign var="is_first" value="true"}
					{foreach from=$shops item=shop}
						{if $shop["active"] == 1}
							<div class="form-group">
								<label for="exampleFormControlSelect1">{l s='Choose a sector for %s' sprintf=[$shop['name']] mod='doofinder'}</label>
								<select required class="form-control sector-select{if $is_first == 'true'} default-shop{/if}"
									id="sector-select-{$shop['id_shop']|escape:'htmlall':'UTF-8'}" 
									name="sector_shop[{$shop['id_shop']|escape:'htmlall':'UTF-8'}]">
									<option selected disabled value="">{l s=' - Choose a sector - ' mod='doofinder'}</option>
									{foreach from=$sectors item=sector key=clave}
										<option value="{$clave}">{$sector}</option>
									{/foreach}
								</select>
								{if $is_first == "true"}
								<input type="checkbox" class="form-check-input" id="apply-to-all">
								<label class="form-check-label" for="apply-to-all">{l s='Apply selection to all stores' mod='doofinder'}</label>
								{/if}
							</div>
							{assign var="is_first" value="false"}
						{/if}
					{/foreach}
					<button type="submit" class="btn btn-primary">{l s='Continue' mod='doofinder'}</button>
					<input type="hidden" name="action" value="set_sector" />
				</form>
			</div>
		</div>
		<hr />
	</div>
</div>
<style type="text/css">
	span.connection-ball {
		width: 8px;
		height: 8px;
		display: inline-block;
		border-radius: 100px;
	}

	.red {
		background-color: #ff0000;
	}

	.green {
		background-color: #00ff37;
	}

	li.active {
		display: block !important;
	}

	.loading-installer ul li {
		font-size: 20px;
		font-weight: bold;
		display: none;
	}

	.btn-doofinder {
		font-size: 20px !important;
		padding: 20px !important;
		font-weight: bold !important;
		white-space: normal !important;
		border-radius: 50px !important;
	}

	#create-account-btn {
		color: #1b1851;
		background-color: #fff031;
		border-color: #fff031;
	}

	#create-account-btn:hover {
		color: #fff031;
		background-color: #1b1851;
		border-color: #1b1851;
	}

	#login-account-btn {
		color: #ffffff;
		background-color: #4842c1;
		border-color: #4842c1;
	}

	#login-account-btn:hover {
		color: #fff031;
		background-color: #1b1851;
		border-color: #1b1851;
	}
</style>
<script type="text/javascript" src="/modules/doofinder/views/js/doofinder-onboarding.js" ></script>

<script type="text/javascript">
	const paramsPopup = "{html_entity_decode($paramsPopup|escape:'htmlall':'UTF-8')}";
	const installerToken = "{$tokenAjax|escape:'htmlall':'UTF-8'}";
</script>