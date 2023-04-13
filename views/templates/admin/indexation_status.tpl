<div class="doofinder-indexation-status" style="margin-top: 2em; margin-bottom: 2em; background-color: #e2e2e2;">
    <div class="row" >
        <div class="col-xs-10" style="margin-left: 1em background-color: #e2e2e2; border-bottom: none;">
            <h3 style="margin: 0; background-color: #e2e2e2; border-bottom: none;">{l s='Doofinder Indexation Status' mod='doofinder'}</h3>
        </div>
        <div class="col-xs-2" style="">
            <button type="button" class="close" aria-label="Close" style="font-size: 3em;">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <div style="padding: 15px;">
        <p>{l s='The product feed is being processed. Depending on the size of the product catalog in the store, this process may take a few minutes.' mod='doofinder'}</p>
        <div class="text-center">
            <div class="loader"></div>
            <p><strong>{l s='Your products may not appear correctly updated in the search results until the process has been completed.' mod='doofinder'}</strong></p>
        </div>
    </div>
</div>

<style>
    .loader {
        border: 5px solid #e2e2e2;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
    $( ".close" ).on( "click", function() {
        $(".doofinder-indexation-status").css("display", "none");
        var adminURL = "{$admin_url}";
        $.post(adminURL, function( data ) {
        });
    });
</script>