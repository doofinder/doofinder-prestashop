$(document).on('ready', function() {
    if(typeof doofinderAppendAfterBanner !== undefined && doofinderAppendAfterBanner.length !== undefined
            && doofinderAppendAfterBanner.length > 0 && $(doofinderAppendAfterBanner).length !== undefined){
        $(doofinderAppendAfterBanner).after($('.doofinder_dinamic_banner'));
        $('.doofinder_dinamic_banner').show();
    }
});