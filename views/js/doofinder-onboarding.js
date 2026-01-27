/**
 * NOTICE OF LICENSE
 *
 * This file is licensed under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the license agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Doofinder
 * @copyright Doofinder
 * @license   MIT
 */

const shopDomain =
  location.protocol +
  "//" +
  location.hostname +
  (location.port ? ":" + location.port : "");

$(document).ready(function () {
  window.addEventListener(
    "message",
    (event) => {
      const doofinder_regex = /.*\.doofinder\.com/gm;
      //Check that the sender is doofinder
      if (!doofinder_regex.test(event.origin)) return;
      if (event.data && 'string' === typeof event.data) {
        data = event.data.split("|");
        event_name = data[0];
        event_data = JSON.parse(atob(data[1]));
        processMessage(event_name, event_data);
      }
    },
    false
  );
});

function popupDoofinder(type) {
  var params =
    "?" +
    paramsPopup +
    "&mktcod=PSHOP&utm_source=prestashop_module&utm_campaing=freetrial&utm_content=autoinstaller";
  if ('undefined' === typeof doofinderAdminUrl) {
    doofinderAdminUrl = 'https://admin.doofinder.com';
  }
  var domain = doofinderAdminUrl + "/plugins/" + type + "/prestashop";
  popupCenter(domain + params, "Doofinder", 400, 850);
}

function initializeAutoinstallerMessages() {
  $(".choose-installer").hide();
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
  $("#installation-errors").empty();
  initializeAutoinstallerMessages();
  let post_data = {
    autoinstaller: 1,
    token: installerToken,
  };

  if (typeof shop_id != "undefined") {
    post_data["shop_id"] = shop_id;
  }

  $.post(ajaxUrl, post_data, function (data) {
    if (data.success) {
      //reload without resending post data
      history.go(0);
    } else {
      if ("string" === typeof data.errors) {
        $(".loading-installer").hide();
        $("#installation-errors").append("<li>" + data.errors + "</li>");
      } else if (data.errors && data.errors.length > 0) {
        $(".loading-installer").hide();
        for (const error in data.errors) {
          if (Object.hasOwnProperty.call(data.errors, error)) {
            $("#installation-errors").append("<li>" + data.errors[error] + "</li>");
          }
        }
      }
      showErrorMessage();
    }
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
    url: configUrl,
    data: data,
    success: function (response) {
      if (response.success) {
        launchAutoinstaller();
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
  $(".loading-installer").hide();
  $(".message-error").show();
}
