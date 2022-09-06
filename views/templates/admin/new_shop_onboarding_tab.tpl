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
			<div class="col-12">
				<p style="font-size:8px;">
				{if $checkConnection}
					<span class="connection-ball green"></span> {l s='Connection with Doofinder successful.' mod='doofinder'}
				{else} 
					<span class="connection-ball red"></span> {l s='You cannot connect with Doofinder. Please contact your server provider to check your web server internet connection or firewall.' mod='doofinder'}
				{/if}
				</p>
			</div>
			<div class="bootstrap message-error" style="display:none;">
				<div class="module_warning alert alert-danger" >
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					{l s='An error occurred during the installation process. Please contact our support team on' mod='doofinder'} support@doofinder.com
					<ul id="installation-errors"></ul>
				</div>
			</div>
			<div class="col-12 text-center loading-installer" style="display:none;">
				<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_logo.png" />
				<br>
				<ul>
					<li class="active">{l s='Please be patient, we are autoinstalling your definitive search solution...' mod='doofinder'}</li>
					<li>{l s='Creating search engines...' mod='doofinder'}</li>
					<li>{l s='Recover data feed of your products...' mod='doofinder'}</li>
					<li>{l s='Creating index to search on your site...' mod='doofinder'}</li>
					<li>{l s='Giving a final magic touch...' mod='doofinder'}</li>
					<li>{l s='Reloading page, please wait...' mod='doofinder'}</li>
				</ul>
			</div>
			<div class="col-md-6 text-center choose-installer">
				<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_logo.png" />
				<br/>
				<img src="{$module_dir|escape:'html':'UTF-8'}views/img/doofinder_mac.png" />
				<div class="col-md-12" style="margin-top:15px">
					<a onclick="javascript:showSectorSelector()" href="#" class="btn btn-primary btn-lg btn-doofinder" id="create-store-btn">{l s='Create this shop in Doofinder' mod='doofinder'}</a>
				</div>
			</div>
			<div class="col-md-6 choose-installer">
				<div class="col-md-8">
				<h2>{l s='Add a smart search engine to your e-commerce in 5 minutes and with no programming' mod='doofinder'}</h2>
				<hr />
				<br />
				<p><strong>{l s='ABOUT DOOFINDER' mod='doofinder'}:</strong></p>
				<br />
				<p>{l s='Doofinder provides you with an instant search layer to display your products when your visitors use the search bar' mod='doofinder'}.</p>
				<br />
				<p>{l s='Your customers then have the opportunity to preview your products, filter them and choose the desired product. Upon hitting enter, doofinder will also power the results page' mod='doofinder'}.</p>
				<br />
				<p>{l s='Among our features are' mod='doofinder'}:</p>
				<br />
				<dl>
					<dd>&middot; {l s='Detailed reports on visitor search behaviour' mod='doofinder'}.</dd>
					<dd>&middot; {l s='Faceted search option' mod='doofinder'}.</dd>
					<dd>&middot; {l s='Learning behaviour' mod='doofinder'}.</dd>
					<dd>&middot; {l s='Merchandising power to set a products positioning' mod='doofinder'}.</dd>
					<dd>&middot; {l s='Banner feature for advertising and promoting products, brands and events' mod='doofinder'}.</dd>
					<dd>&middot; {l s='Options to redirect users to content pages' mod='doofinder'}.</dd>
					<dd>&middot; {l s='Held on our servers for a faster page load time' mod='doofinder'}.</dd>
				</dl>
				<br />
				<p>{l s='More than 5000 e-commerce sites over 35 countries are already using it' mod='doofinder'}.</p>
				</div>
			</div>
		</div>
		<div id="choose-sector" class="row justify-content-md-center" style="display:none">
			<div class="col-md-8 col-md-offset-2">
				<h2 class="mb3">{l s='Select a sector' mod='doofinder'}</h2>
				<form id="sector-form">
					<div class="form-group">
						<label for="exampleFormControlSelect1">{l s='Choose a sector for %s' sprintf=[$shop_data['name']] mod='doofinder'}</label>
						<select required class="form-control sector-select"	id="sector-select-{$shop_data['id_shop']|escape:'htmlall':'UTF-8'}" name="sector_shop[{$shop_id|escape:'htmlall':'UTF-8'}]">
							<option selected disabled value="">{l s=' - Choose a sector - ' mod='doofinder'}</option>
							{foreach from=$sectors item=sector_html key=clave}
							<option value="{$clave|escape:'htmlall':'UTF-8'}">{$sector_html}</option>
							{/foreach}
						</select>
					</div>					
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
.red{
	background-color:#ff0000;
}
.green{
	background-color:#00ff37;
}

li.active{
	display:block!important;
}
.loading-installer ul li{
	font-size: 20px;
    font-weight: bold;
	display:none;
}
.btn-doofinder{
	font-size: 20px!important;
    padding: 20px!important;
    font-weight: bold!important;
    white-space: normal!important;
	border-radius: 50px!important;
}
#create-store-btn{
    color: #1b1851;
    background-color: #fff031;
    border-color: #fff031;
}
#create-store-btn:hover{
    color: #fff031;
    background-color: #1b1851;
    border-color: #1b1851;
}

#module_form{
	display:none;
}
#installation-errors {
    padding-left: 18px;
}
</style>

<script type="text/javascript">
	const shop_id = {$shop_id|escape:'htmlall':'UTF-8'};
	const installerToken = "{$tokenAjax|escape:'htmlall':'UTF-8'}";
</script>
<script type="text/javascript" src="/modules/doofinder/views/js/doofinder-onboarding.js" ></script>