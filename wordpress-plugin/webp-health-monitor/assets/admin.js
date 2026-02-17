(function () {
  function runScan(button) {
    if (!window.whmData) {
      return;
    }

    var container = button.closest(".whm-widget, .whm-wrap") || document;
    var statusNode = container.querySelector(".whm-ajax-result");
    if (statusNode) {
      statusNode.textContent = whmData.strings.running || "Running scan...";
    }
    button.disabled = true;

    var body = new URLSearchParams();
    body.append("action", "whm_run_scan");
    body.append("nonce", whmData.nonce);

    fetch(whmData.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (!statusNode) {
          return;
        }
        if (data && data.success && data.data) {
          statusNode.innerHTML =
            '<span class="whm-success">' +
            (whmData.strings.completed || "Scan completed.") +
            " " +
            "Scanned " +
            String(data.data.scanned_products || 0) +
            " products, found " +
            String(data.data.issues_count || 0) +
            " issues.</span>";
          window.setTimeout(function () {
            window.location.reload();
          }, 700);
          return;
        }
        var message =
          (data && data.data && data.data.message) ||
          whmData.strings.failed ||
          "Scan failed.";
        statusNode.innerHTML =
          '<span class="whm-error">' + String(message) + "</span>";
      })
      .catch(function () {
        if (statusNode) {
          statusNode.innerHTML =
            '<span class="whm-error">' +
            (whmData.strings.failed || "Scan failed.") +
            "</span>";
        }
      })
      .finally(function () {
        button.disabled = false;
      });
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest(".whm-run-scan");
    if (!button) {
      return;
    }
    event.preventDefault();
    runScan(button);
  });
})();
