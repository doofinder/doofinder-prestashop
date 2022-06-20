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
			<div class="bootstrap message-popup" style="display:none;">
				<div class="module_warning alert alert-warning" >
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					{l s='You don\'t receive the Api Key or you close too quickly the popup window. Please try again. If you think this is an error, please contact our support or try the module manual configuration option.' mod='doofinder'}
				</div>
			</div>
			<div class="bootstrap message-error" style="display:none;">
				<div class="module_warning alert alert-danger" >
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					{l s='An error occurred during the installation process. Please contact our support team on' mod='doofinder'} support@doofinder.com
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
				<div class="col-md-12">
					<a onclick="javascript:popupDoofinder('signup')" href="#" class="btn btn-primary btn-lg btn-doofinder" id="create-account-btn">{l s='Use your free trial now!' mod='doofinder'}</a>
				</div>
				<div class="col-md-12" style="margin-top:15px">
					<a onclick="javascript:popupDoofinder('login')" href="#" class="btn btn-primary btn-lg btn-doofinder" id="login-account-btn">{l s='I have an account' mod='doofinder'}</a>
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
				<br />
				<p>{l s='What are you waiting for?' mod='doofinder'}</p>
				<br />
				<p><a onclick="javascript:popupDoofinder('signup')" href="#">{l s='Test our solution for 30 days, for free!' mod='doofinder'}</a></p>
				</div>
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
#create-account-btn{
    color: #1b1851;
    background-color: #fff031;
    border-color: #fff031;
}
#create-account-btn:hover{
    color: #fff031;
    background-color: #1b1851;
    border-color: #1b1851;
}
#login-account-btn{
	color: #ffffff;
    background-color: #4842c1;
	border-color: #4842c1;
}
#login-account-btn:hover{
	color: #fff031;
    background-color: #1b1851;
	border-color: #1b1851;
}
</style>
<script type="text/javascript">
	function popupDoofinder(type){
		var params = '?{html_entity_decode($paramsPopup|escape:'htmlall':'UTF-8')}&mktcod=PSHOP&utm_source=prestashop_module&utm_campaing=freetrial&utm_content=autoinstaller';
		var domain = 'https://app.doofinder.com/plugins/'+type+'/prestashop';
		var winObj = popupCenter( domain+params, 'Doofinder', 400,  850);
		
		var loop = setInterval(function() {   
			if(winObj.closed) {  
				clearInterval(loop);
				installingLoop();  
			}  
		}, 1000); 

	}

	function installingLoop(){
		var shopDomain = location.protocol+'//'+location.hostname+(location.port ? ':'+location.port: '');
		$.post(shopDomain+'/modules/doofinder/doofinder-ajax.php', {
			'check_api_key':1
		}, function(data){
			if(data == 'OK') {
				$('.choose-installer').hide();
				$('.loading-installer').show();
				launchAutoinstaller();				
			} else {
				$('.message-popup').show();
				setTimeout(function(){
					$('.message-popup').hide();
				} ,10000);
			}
		});

		
	}

	function launchAutoinstaller(){
		var shopDomain = location.protocol+'//'+location.hostname+(location.port ? ':'+location.port: '');
		var token = '{$tokenAjax|escape:'htmlall':'UTF-8'}';
		$.post(shopDomain+'/modules/doofinder/doofinder-ajax.php', {
			'autoinstaller':1,
			'token':token
		}, function(data){
			if(data == 'OK') {
				var loop = setInterval(function() {
					$('.loading-installer ul li.active').removeClass('active').next().addClass('active');  
					if($('.loading-installer ul li.active').index() < 0) {  
						clearInterval(loop);
						location.reload();
					}  
				}, 3000);
			} else {
				$('.message-error').show();
			}
		})
		.fail(function() {
		    location.reload();
		});
	}

	function popupCenter(url, title, w, h){
		const dualScreenLeft = window.screenLeft !==  undefined ? window.screenLeft : window.screenX;
		const dualScreenTop = window.screenTop !==  undefined   ? window.screenTop  : window.screenY;

		const width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
		const height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

		const systemZoom = width / window.screen.availWidth;
		const left = (width - w) / 2 / systemZoom + dualScreenLeft
		const top = (height - h) / 2 / systemZoom + dualScreenTop
		{literal}
		const newWindow = window.open(url, title, 
		`
		scrollbars=yes,
		width=${w / systemZoom}, 
		height=${h / systemZoom}, 
		top=${top}, 
		left=${left},
		status=0,
		toolbar=0,
		location=0
		`
		)
		{/literal}
		if (window.focus) newWindow.focus();
		return newWindow;
	}
</script>
