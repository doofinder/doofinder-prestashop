/**
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
 */

$(document).ready(function () {
  $(".apply-to-all").on("change", function () {
    if ($(this).is(":checked")) {
      set_default_value();
    } else {
      $(".sector-select")
        .not(".default-shop")
        .find('option[value=""]')
        .prop("selected", true);
    }
  });

  $(".sector-select.default-shop").on("change", function () {
    if ($(".apply-to-all").is(":checked")) {
      set_default_value();
    }
  });
});

function set_default_value() {
  let value = $(".default-shop").val();
  $(".sector-select").not(".default-shop").val(value).change();
}
