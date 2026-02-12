(function () {
  var scanBtn = document.getElementById("wim-scan-btn");
  var convertBtn = document.getElementById("wim-convert-btn");
  var restoreBtn = document.getElementById("wim-restore-btn");
  var retryFailedBtn = document.getElementById("wim-retry-failed-btn");
  var logBox = document.getElementById("wim-log");

  function setStats(stats) {
    if (!stats) return;
    var total = document.getElementById("wim-total");
    var converted = document.getElementById("wim-converted");
    var pending = document.getElementById("wim-pending");
    var failed = document.getElementById("wim-failed");
    var backupDir = document.getElementById("wim-backup-dir");
    var lastRun = document.getElementById("wim-last-run");

    if (total) total.textContent = String(stats.total_convertible || 0);
    if (converted) converted.textContent = String(stats.converted || 0);
    if (pending) pending.textContent = String(stats.pending || 0);
    if (failed) failed.textContent = String(stats.failed || 0);
    if (backupDir) backupDir.textContent = stats.backup_dir || "Not created yet";
    if (lastRun) lastRun.textContent = stats.last_run || "No run yet";
  }

  function log(message, type) {
    if (!logBox) return;
    var stamp = new Date().toLocaleTimeString();
    var p = document.createElement("p");
    p.className = "wim-log-line" + (type ? " is-" + type : "");
    p.textContent = "[" + stamp + "] " + message;
    logBox.prepend(p);
  }

  async function callAjax(action) {
    var body = new URLSearchParams();
    body.set("action", action);
    body.set("nonce", wimData.nonce);

    var response = await fetch(wimData.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    });

    if (!response.ok) {
      throw new Error("Request failed: " + response.status);
    }

    var payload = await response.json();
    if (!payload.success) {
      throw new Error((payload.data && payload.data.message) || "Action failed.");
    }

    return payload.data || {};
  }

  async function scan() {
    log("Scanning media library...");
    var data = await callAjax("wim_scan");
    setStats(data);
    log("Scan complete.");
  }

  async function runLoop(action, label) {
    var finished = false;
    var totalSuccess = 0;
    var totalFailed = 0;

    while (!finished) {
      var data = await callAjax(action);
      var successInBatch = Number(data.converted_in_batch || data.restored_in_batch || 0);
      var failedInBatch = Number(data.failed_in_batch || 0);
      totalSuccess += successInBatch;
      totalFailed += failedInBatch;
      setStats(data.stats);
      finished = !!data.completed;
      log(label + " batch: " + successInBatch + " success, " + failedInBatch + " failed.");
    }

    log(label + " completed. Success: " + totalSuccess + ", failed: " + totalFailed + ".", totalFailed > 0 ? "warning" : "ok");
  }

  async function guardedRun(button, action, label) {
    if (!button) return;
    button.disabled = true;
    if (scanBtn) scanBtn.disabled = true;
    if (convertBtn) convertBtn.disabled = true;
    if (restoreBtn) restoreBtn.disabled = true;
    if (retryFailedBtn) retryFailedBtn.disabled = true;

    try {
      await runLoop(action, label);
    } catch (error) {
      log(error.message || "Unexpected error", "error");
    } finally {
      if (scanBtn) scanBtn.disabled = false;
      if (convertBtn) convertBtn.disabled = !wimData.hasWebpSupport;
      if (restoreBtn) restoreBtn.disabled = false;
      if (retryFailedBtn) retryFailedBtn.disabled = false;
    }
  }

  if (scanBtn) {
    scanBtn.addEventListener("click", async function () {
      scanBtn.disabled = true;
      try {
        await scan();
      } catch (error) {
        log(error.message || "Scan failed.", "error");
      } finally {
        scanBtn.disabled = false;
      }
    });
  }

  if (convertBtn) {
    convertBtn.addEventListener("click", function () {
      guardedRun(convertBtn, "wim_convert_batch", "Conversion");
    });
  }

  if (restoreBtn) {
    restoreBtn.addEventListener("click", function () {
      var ok = window.confirm("Restore all converted images from backup?");
      if (!ok) return;
      guardedRun(restoreBtn, "wim_restore_batch", "Restore");
    });
  }

  if (retryFailedBtn) {
    retryFailedBtn.addEventListener("click", async function () {
      retryFailedBtn.disabled = true;
      try {
        var data = await callAjax("wim_reset_failed");
        setStats(data.stats);
        log("Reset failed flags for " + Number(data.reset || 0) + " image(s).", "ok");
      } catch (error) {
        log(error.message || "Failed reset failed.", "error");
      } finally {
        retryFailedBtn.disabled = false;
      }
    });
  }

  if (!wimData.hasWebpSupport) {
    if (convertBtn) convertBtn.disabled = true;
    log("WebP conversion is disabled: server support is missing (Imagick/GD WebP).", "warning");
  }
})();
