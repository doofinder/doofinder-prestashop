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

const shopDomain =
  location.protocol +
  "//" +
  location.hostname +
  (location.port ? ":" + location.port : "");

$(document).ready(function () {
  $("#apply-to-all").on("change", function () {
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
    if ($("#apply-to-all").is(":checked")) {
      set_default_value();
    }
  });

  $("#sector-form").submit(function (event) {
    event.preventDefault();
    let formData = $(this).serializeArray();
    $.ajax({
      type: "POST",
      url: shopDomain + "/modules/doofinder/config.php",
      data: formData,
      dataType: "json",
      success: function (response) {
        console.log(response);
        if (response.success) {
          launchAutoinstaller();
        } else {
          alert("An error ocurred while saving the sector");
        }
      },
    });
  });

  window.addEventListener(
    "message",
    (event) => {
      const doofinder_regex = /.*\.doofinder\.com/gm;
      //Check that the sender is doofinder
      if (!doofinder_regex.test(event.origin)) return;
      if (event.data) {
        data = event.data.split("|");
        event_name = data[0];
        event_data = JSON.parse(atob(data[1]));
        processMessage(event_name, event_data);
      }
    },
    false
  );
});

function showSectorSelector() {
  $(".choose-installer").hide();
  $("#choose-sector").show();
}

function set_default_value() {
  let value = $(".default-shop").val();
  console.log("Selected value = " + value);
  $(".sector-select").not(".default-shop").val(value).change();
}

function popupDoofinder(type) {
  var params =
    "?" +
    paramsPopup +
    "&mktcod=PSHOP&utm_source=prestashop_module&utm_campaing=freetrial&utm_content=autoinstaller";
  var domain = "https://admin.doofinder.com/plugins/" + type + "/prestashop";
  var winObj = popupCenter(domain + params, "Doofinder", 400, 850);
}

function initializeAutoinstallerMessages() {
  $(".loading-installer").show();
  var loop = setInterval(function () {
    if (!$(".loading-installer ul li.active").is(":last-child")) {
      $(".loading-installer ul li.active")
        .removeClass("active")
        .next()
        .addClass("active");
    } else {
      clearInterval(loop);
    }
  }, 4000);
}

function launchAutoinstaller() {
  $("#choose-sector").hide();
  $("#installation-errors").empty();
  initializeAutoinstallerMessages();
  let data = {
    autoinstaller: 1,
    token: installerToken,
  };

  if (typeof shop_id != 'undefined') {
    data["shop_id"] = shop_id;
  }

  $.post({
    type: "POST",
    dataType: "json",
    url: shopDomain + "/modules/doofinder/doofinder-ajax.php",
    data: data,
    success: function (data) {
      if (data == "OK") {
        location.reload();
      } else {
        if (data.errors && data.errors.length > 0) {
          $(".loading-installer").hide();
          for (const error in data.errors) {
            if (Object.hasOwnProperty.call(data.errors, error)) {
              $("#installation-errors").append(
                "<li>" + data.errors[error] + "</li>"
              );
            }
          }
        }
        showErrorMessage();
      }
    },
    error: function () {
      showErrorMessage();
    },
  });
}

function popupCenter(url, title, w, h) {
  const dualScreenLeft =
    window.screenLeft !== undefined ? window.screenLeft : window.screenX;
  const dualScreenTop =
    window.screenTop !== undefined ? window.screenTop : window.screenY;

  const width = window.innerWidth
    ? window.innerWidth
    : document.documentElement.clientWidth
    ? document.documentElement.clientWidth
    : screen.width;
  const height = window.innerHeight
    ? window.innerHeight
    : document.documentElement.clientHeight
    ? document.documentElement.clientHeight
    : screen.height;

  const systemZoom = width / window.screen.availWidth;
  const left = (width - w) / 2 / systemZoom + dualScreenLeft;
  const top = (height - h) / 2 / systemZoom + dualScreenTop;

  const newWindow = window.open(
    url,
    title,
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
  );

  if (window.focus) newWindow.focus();
  return newWindow;
}

function processMessage(name, data) {
  if (name === "set_doofinder_data") send_connect_data(data);
}

function send_connect_data(data) {
  $.ajax({
    type: "POST",
    dataType: "json",
    url: shopDomain + "/modules/doofinder/config.php",
    data: data,
    success: function (response) {
      if (response.success) {
        showSectorSelector();
      } else {
        showConnectionError();
      }
    },
    error: function (data) {
      showConnectionError();
    },
  });
}

function showConnectionError() {
  $(".message-popup").show();
  setTimeout(function () {
    $(".message-popup").hide();
  }, 10000);
}

function showErrorMessage() {
  $(".message-error").show();
}
