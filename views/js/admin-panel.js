/**
 * Copyright (c) Doofinder
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */

$(document).ready(function() {

  addVisibilityDependency('DF_GS_DISPLAY_PRICES', 'DF_GS_PRICES_USE_TAX_on');
  addVisibilityDependency('DF_SHOW_PRODUCT_FEATURES', 'DF_FEATURES_SHOWN[]');
  addVisibilityDependency('DF_SHOW_PRODUCT_VARIATIONS', 'DF_GROUP_ATTRIBUTES_SHOWN[]');

  function addVisibilityDependency(triggeringElementId, targetElementId) {
    var trigeringElementOnId = "#" + triggeringElementId + "_on";
    var trigeringElementOffId = "#" + triggeringElementId + "_off";
    hideOrShowElement(trigeringElementOnId, targetElementId);

    $(trigeringElementOnId + "," + trigeringElementOffId).change(function() {
      hideOrShowElement(trigeringElementOnId, targetElementId);
    });
  }

  function hideOrShowElement(trigeringElementOnId, targetElementId) {
    var value = $(trigeringElementOnId).is(':checked');
    var parent = getParent(targetElementId);

    if (value) {
      parent.show()
    } else {
      parent.hide();
    }
  }

  /* We get the element from the dom and then convert it to jquery 
  because jquery has problems with some ids that prestashop sets, 
  for example DF_FEATURES_SHOWN[]. */
  
  function getParent(targetElementId) {
    var targetElement = $(document.getElementById(targetElementId));
    var parent = targetElement.parents().filter(function() {
      return $(this).is('.form-group');
    });
    return parent;
  }

  const $apiKeyNode = $('#DF_API_KEY');
  const $regionNode = $('#DF_REGION');
  $apiKeyNode.on('change', function() {
    if (0 === $apiKeyNode.length || 0 === $regionNode.length) {
      return;
    }
    const apiKey = $apiKeyNode.val().trim();
    if (!/eu1-|ap1-|us1-/.test(apiKey)) {
      return;
    }
    const region = apiKey.split('-').shift();
    const previousRegion = $regionNode.val();
    $regionNode.val(region);
    
    if (previousRegion === region) {
      return;
    }

    // Small animation to catch user attention
    $regionNode.animate({
      opacity:"0.5"
    }, 1000, function() {
      $regionNode.animate({
        opacity:"1"
      }, 1000);
    });
  });

  $('.df-checkboxes-table [data-checkboxes-toggle]').on('click', function(event){
    const $parentTable = $(this).closest('.df-checkboxes-table');
    const isChecked = $(this).is(':checked');
    $parentTable.find('input[type="checkbox"]').prop('checked', isChecked);
  });

  const commonCheckboxSelector = 'input[type="checkbox"]:not([data-checkboxes-toggle])';

  $('.df-checkboxes-table ' + commonCheckboxSelector).on('click', function(event){
    const $parentTable = $(this).closest('.df-checkboxes-table');
    const checkboxesTotalCount = $parentTable.find(commonCheckboxSelector).length;
    const checkboxesCheckedcount = $parentTable.find(commonCheckboxSelector + ':checked').length;
    const allChecked =  checkboxesTotalCount === checkboxesCheckedcount;
    $parentTable.find('[data-checkboxes-toggle]').prop('checked', allChecked);
  });
});
