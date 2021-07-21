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
{if $oldPS}
<script type="text/javascript">
    $(document).ready(function(){
        $("#content").addClass("bootstrap");
        $(".defaultForm").addClass("panel");
        $("input[type='submit']").addClass("btn-lg");
    });
</script>
{/if}
{if isset($formUpdatedToClick)}
<script type="text/javascript">
    $(document).ready(function(){
        $('.nav-tabs a[href="#{$formUpdatedToClick|escape:'htmlall':'UTF-8'}"]').trigger('click');
    });
</script>
{/if}
<!-- Nav tabs -->
<ul class="nav nav-tabs" role="tablist">
    {if !$configured}
    <li class="active"><a href="#onboarding_tab" role="tab" data-toggle="tab">{l s='On Boarding' mod='doofinder'}</a></li>
    {else}
    <li class="active"><a href="#data_feed_tab" role="tab" data-toggle="tab">{l s='Data Feed' mod='doofinder'}</a></li>
    <li><a href="#search_layer_tab" role="tab" data-toggle="tab">{l s='Search Layer' mod='doofinder'}</a></li>
    {if !$dfEnabledV9}
        <li><a href="#internal_search_tab" role="tab" data-toggle="tab">{l s='Internal Search' mod='doofinder'}</a></li>
        <li><a href="#custom_css_tab" role="tab" data-toggle="tab">{l s='Custom CSS' mod='doofinder'}</a></li>
    {/if}
    <li><a href="#support_tab" role="tab" data-toggle="tab">{l s='Support' mod='doofinder'}</a></li>
    {/if}
    {if $adv && $configured}
    <li><a href="#advanced_tab" role="tab" data-toggle="tab">{l s='Advanced' mod='doofinder'}</a></li>
    {/if}
</ul>

<!-- Tab panes -->
<div class="tab-content">
    {if !$configured}
    <div class="tab-pane active" id="onboarding_tab">{include file='./onboarding_tab.tpl'}</div>
    {/if}
