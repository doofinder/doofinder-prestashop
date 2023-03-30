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

    if (value ) {
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

});