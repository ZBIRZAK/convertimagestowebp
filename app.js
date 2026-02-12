const tool = document.body?.dataset.tool;

const fileInput = document.getElementById("fileInput");
const dropZone = document.getElementById("dropZone");
const list = document.getElementById("list");
const convertBtn = document.getElementById("convertBtn");
const downloadAllBtn = document.getElementById("downloadAllBtn");
const clearBtn = document.getElementById("clearBtn");
const qualityInput = document.getElementById("quality");
const qualityValue = document.getElementById("qualityValue");
const maxWidthInput = document.getElementById("maxWidth");
const outputFormat = document.getElementById("outputFormat");
const analyzeBtn = document.getElementById("analyzeBtn");
const compareSection = document.getElementById("compareSection");
const beforePreview = document.getElementById("beforePreview");
const afterPreview = document.getElementById("afterPreview");
const compareAfterWrap = document.getElementById("compareAfterWrap");
const compareHandle = document.getElementById("compareHandle");
const compareRange = document.getElementById("compareRange");
const compareMeta = document.getElementById("compareMeta");
const compareSelect = document.getElementById("compareSelect");

const ADSENSE_CLIENT = "ca-pub-4424746392677797";
const GA_MEASUREMENT_ID = "G-QPK5DDBC3G";
let thirdPartyBooted = false;
let analyticsLoaded = false;
let adsenseLoaded = false;

const lazySummary = document.getElementById("lazySummary");
const lazyResults = document.getElementById("lazyResults");
const htmlInput = document.getElementById("htmlInput");

const psSummary = document.getElementById("psSummary");
const psTable = document.getElementById("psTable");

const allowedTypes = new Set(["image/png", "image/jpeg", "image/webp", "image/heic", "image/heif"]);
let items = [];
let itemSeq = 0;

function loadExternalScript(src, attributes = {}) {
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    script.async = true;
    Object.entries(attributes).forEach(([key, value]) => {
      script.setAttribute(key, value);
    });
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

function pageHasVisibleAdSlots() {
  const slots = document.querySelectorAll("ins.adsbygoogle");
  if (slots.length === 0) return false;
  return Array.from(slots).some((slot) => {
    const wrap = slot.closest(".ad-wrap");
    if (!wrap) return true;
    return getComputedStyle(wrap).display !== "none";
  });
}

async function initAnalytics() {
  if (analyticsLoaded) return;
  analyticsLoaded = true;
  try {
    await loadExternalScript(
      `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`
    );
    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
      window.dataLayer.push(arguments);
    };
    window.gtag("js", new Date());
    window.gtag("config", GA_MEASUREMENT_ID);
  } catch (_) {
    // Ignore third-party load failures to avoid blocking core app behavior.
  }
}

async function initAdsense() {
  if (adsenseLoaded || !pageHasVisibleAdSlots()) return;
  adsenseLoaded = true;
  try {
    await loadExternalScript(
      `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${ADSENSE_CLIENT}`,
      { crossorigin: "anonymous" }
    );
    const slots = document.querySelectorAll("ins.adsbygoogle");
    slots.forEach((slot) => {
      if (slot.dataset.adActivated === "true") return;
      slot.dataset.adActivated = "true";
      (window.adsbygoogle = window.adsbygoogle || []).push({});
    });
  } catch (_) {
    // Ignore third-party load failures to avoid blocking core app behavior.
  }
}

function bootThirdParty() {
  if (thirdPartyBooted) return;
  thirdPartyBooted = true;
  initAnalytics();
  initAdsense();
}

function scheduleThirdPartyBoot() {
  const onFirstInteraction = () => {
    bootThirdParty();
    window.removeEventListener("pointerdown", onFirstInteraction, true);
    window.removeEventListener("keydown", onFirstInteraction, true);
    window.removeEventListener("scroll", onFirstInteraction, true);
    window.removeEventListener("touchstart", onFirstInteraction, true);
  };

  window.addEventListener("pointerdown", onFirstInteraction, true);
  window.addEventListener("keydown", onFirstInteraction, true);
  window.addEventListener("scroll", onFirstInteraction, true);
  window.addEventListener("touchstart", onFirstInteraction, true);

  if ("requestIdleCallback" in window) {
    window.requestIdleCallback(() => bootThirdParty(), { timeout: 4000 });
  } else {
    window.setTimeout(() => bootThirdParty(), 4000);
  }
}

function applyComparePosition(value) {
  if (!compareAfterWrap || !compareHandle) return;
  const clamped = Math.max(0, Math.min(100, Number(value) || 50));
  compareAfterWrap.style.clipPath = `inset(0 ${100 - clamped}% 0 0)`;
  compareHandle.style.left = `${clamped}%`;
}

function updateComparePreview(item) {
  if (!compareSection || !beforePreview || !afterPreview || !compareRange) return;
  if (!item?.beforeUrl || !item?.url || !item?.blob || item.error) {
    compareSection.hidden = true;
    if (compareMeta) compareMeta.textContent = "";
    return;
  }
  beforePreview.src = item.beforeUrl;
  afterPreview.src = item.url;
  if (compareMeta) {
    const beforeSize = item.file?.size || 0;
    const afterSize = item.blob?.size || 0;
    if (beforeSize > 0) {
      const diff = beforeSize - afterSize;
      const pct = Math.round((Math.abs(diff) / beforeSize) * 100);
      const direction = diff >= 0 ? "smaller" : "larger";
      compareMeta.textContent = `Original: ${bytesToSize(beforeSize)} | Converted: ${bytesToSize(afterSize)} (${pct}% ${direction})`;
    } else {
      compareMeta.textContent = `Converted size: ${bytesToSize(afterSize)}`;
    }
  }
  compareSection.hidden = false;
  applyComparePosition(compareRange.value);
}

function refreshCompareFromItems() {
  const readyItems = items.filter((item) => item.blob && !item.error);
  if (compareSelect) {
    const selectedId = compareSelect.value;
    compareSelect.innerHTML = "";

    if (readyItems.length === 0) {
      const option = document.createElement("option");
      option.value = "";
      option.textContent = "Convert images to enable preview";
      compareSelect.appendChild(option);
      compareSelect.disabled = true;
      updateComparePreview(null);
      return;
    }

    for (const item of readyItems) {
      const option = document.createElement("option");
      option.value = item.id;
      option.textContent = item.file.name;
      compareSelect.appendChild(option);
    }

    compareSelect.disabled = false;
    const selectedItem =
      readyItems.find((item) => item.id === selectedId) || readyItems[0];
    compareSelect.value = selectedItem.id;
    updateComparePreview(selectedItem);
    return;
  }

  updateComparePreview(readyItems[0] || null);
}

function setQualityLabel() {
  if (qualityInput && qualityValue) {
    qualityValue.textContent = qualityInput.value;
  }
}

function bytesToSize(bytes) {
  if (!bytes) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

function inferImageMime(file) {
  if (file.type && file.type.startsWith("image/")) return file.type;
  const match = file.name.match(/\.([^.]+)$/);
  if (!match) return "image/jpeg";
  const ext = match[1].toLowerCase();
  if (ext === "png") return "image/png";
  if (ext === "webp") return "image/webp";
  if (ext === "jpg" || ext === "jpeg") return "image/jpeg";
  return "image/jpeg";
}

function inferImageExtension(mime) {
  if (mime === "image/png") return "png";
  if (mime === "image/webp") return "webp";
  return "jpg";
}

function renderList() {
  if (!list || !convertBtn || !downloadAllBtn) return;
  list.innerHTML = "";

  for (const item of items) {
    const nameCell = document.createElement("div");
    nameCell.className = "cell";
    nameCell.textContent = item.file.name;

    const statusCell = document.createElement("div");
    statusCell.className = "cell";
    const badge = document.createElement("span");
    badge.className = `badge ${item.error ? "error" : ""}`;
    badge.textContent = item.error
      ? "Failed"
      : item.blob
      ? "Ready"
      : "Pending";
    statusCell.appendChild(badge);

    const sizeCell = document.createElement("div");
    sizeCell.className = "cell";
    sizeCell.textContent = item.blob ? bytesToSize(item.blob.size) : "-";

    const dlCell = document.createElement("div");
    dlCell.className = "cell";
    if (item.blob) {
      const link = document.createElement("a");
      link.textContent = "Download";
      link.href = item.url;
      link.download = item.outputName;
      link.className = "badge";
      dlCell.appendChild(link);
    } else {
      dlCell.textContent = "-";
    }

    list.appendChild(nameCell);
    list.appendChild(statusCell);
    list.appendChild(sizeCell);
    list.appendChild(dlCell);
  }

  convertBtn.disabled = items.length === 0;
  downloadAllBtn.disabled = !items.some((item) => item.blob);
}

function addFiles(fileList) {
  let added = false;
  for (const file of fileList) {
    const isHeic =
      /\.heic$|\.heif$/i.test(file.name) ||
      ["image/heic", "image/heif"].includes(file.type);

    if (tool === "heic-to-webp" && isHeic) {
      // allow HEIC even if file.type is empty
    } else if (!allowedTypes.has(file.type)) {
      continue;
    }

    if (tool === "webp-converter" && file.type === "image/webp") continue;
    if (tool === "webp-to-jpg" && file.type !== "image/webp") continue;
    if (tool === "heic-to-webp" && !isHeic) continue;

    items.push({
      id: `item-${++itemSeq}`,
      file,
      blob: null,
      url: null,
      beforeUrl: URL.createObjectURL(file),
      outputName: getOutputName(file),
      error: null,
    });
    added = true;
  }
  if (added) updateComparePreview(null);
  renderList();
}

function getOutputName(file) {
  const name = file.name;
  if (tool === "webp-to-jpg") return name.replace(/\.[^.]+$/, "") + ".jpg";
  if (tool === "webp-to-avif") return name.replace(/\.[^.]+$/, "") + ".avif";
  if (tool === "image-compressor") {
    if (/\.[^.]+$/.test(name)) return name;
    const ext = inferImageExtension(inferImageMime(file));
    return `${name}.${ext}`;
  }
  return name.replace(/\.[^.]+$/, "") + ".webp";
}

function clearAll() {
  for (const item of items) {
    if (item.url) URL.revokeObjectURL(item.url);
    if (item.beforeUrl) URL.revokeObjectURL(item.beforeUrl);
  }
  items = [];
  if (fileInput) fileInput.value = "";
  updateComparePreview(null);
  refreshCompareFromItems();
  renderList();
}

async function convertFile(item, quality) {
  if (tool === "heic-to-webp") {
    if (typeof heic2any !== "function") {
      throw new Error("HEIC converter not loaded");
    }
    const heicResult = await heic2any({ blob: item.file, toType: "image/jpeg" });
    const heicBlob = Array.isArray(heicResult) ? heicResult[0] : heicResult;
    const imageBitmap = await createImageBitmap(heicBlob);
    const canvas = document.createElement("canvas");
    canvas.width = imageBitmap.width;
    canvas.height = imageBitmap.height;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(imageBitmap, 0, 0);
    const blob = await new Promise((resolve) =>
      canvas.toBlob(resolve, "image/webp", quality)
    );
    if (!blob) throw new Error("Conversion failed");
    return blob;
  }

  const imageBitmap = await createImageBitmap(item.file);
  const canvas = document.createElement("canvas");

  if (tool === "image-compressor" && maxWidthInput) {
    const maxWidth = Number(maxWidthInput.value) || imageBitmap.width;
    const ratio = Math.min(1, maxWidth / imageBitmap.width);
    canvas.width = Math.round(imageBitmap.width * ratio);
    canvas.height = Math.round(imageBitmap.height * ratio);
  } else {
    canvas.width = imageBitmap.width;
    canvas.height = imageBitmap.height;
  }

  const ctx = canvas.getContext("2d");
  ctx.drawImage(imageBitmap, 0, 0, canvas.width, canvas.height);

  let mime = "image/webp";
  if (tool === "webp-to-jpg") {
    mime = "image/jpeg";
  } else if (tool === "webp-to-avif") {
    mime = "image/avif";
  } else if (tool === "image-compressor") {
    mime = inferImageMime(item.file);
  }

  const blob = await new Promise((resolve) =>
    canvas.toBlob(resolve, mime, quality)
  );

  if (!blob) {
    throw new Error("Conversion failed");
  }

  return blob;
}

async function convertAll() {
  const quality = qualityInput ? Number(qualityInput.value) / 100 : 0.85;
  const originalText = convertBtn ? convertBtn.textContent : null;
  if (convertBtn) {
    convertBtn.textContent = "Processing...";
    convertBtn.disabled = true;
    convertBtn.classList.add("is-loading");
  }

  for (const item of items) {
    item.error = null;
    if (item.url) URL.revokeObjectURL(item.url);
    item.url = null;
    item.blob = null;
  }
  updateComparePreview(null);
  renderList();

  for (const item of items) {
    try {
      const blob = await convertFile(item, quality);
      item.blob = blob;
      item.url = URL.createObjectURL(blob);
      item.outputName = getOutputName(item.file);
    } catch (err) {
      item.error = err.message || "Failed";
    }
    renderList();
  }

  if (convertBtn && originalText) {
    convertBtn.textContent = originalText;
    convertBtn.classList.remove("is-loading");
  }
  refreshCompareFromItems();
  renderList();
}

async function downloadAll() {
  const readyItems = items.filter((item) => item.blob);
  if (readyItems.length === 0) return;

  if (typeof JSZip !== "function") {
    for (const item of readyItems) {
      const a = document.createElement("a");
      a.href = item.url;
      a.download = item.outputName;
      document.body.appendChild(a);
      a.click();
      a.remove();
    }
    return;
  }

  const originalText = downloadAllBtn ? downloadAllBtn.textContent : null;
  if (downloadAllBtn) {
    downloadAllBtn.textContent = "Preparing ZIP...";
    downloadAllBtn.disabled = true;
    downloadAllBtn.classList.add("is-loading");
  }

  const zip = new JSZip();
  const nameCounts = new Map();

  for (const item of readyItems) {
    let name = item.outputName;
    const count = nameCounts.get(name) || 0;
    if (count > 0) {
      const dot = name.lastIndexOf(".");
      const base = dot > 0 ? name.slice(0, dot) : name;
      const ext = dot > 0 ? name.slice(dot) : "";
      name = `${base} (${count})${ext}`;
    }
    nameCounts.set(item.outputName, count + 1);
    zip.file(name, item.blob);
  }

  const zipBlob = await zip.generateAsync({ type: "blob" });
  const zipUrl = URL.createObjectURL(zipBlob);
  const a = document.createElement("a");
  a.href = zipUrl;
  a.download = "converted-images.zip";
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(zipUrl), 1000);

  if (downloadAllBtn && originalText) {
    downloadAllBtn.textContent = originalText;
    downloadAllBtn.classList.remove("is-loading");
    downloadAllBtn.disabled = false;
  }
}

function initFileTool() {
  if (qualityInput) qualityInput.addEventListener("input", setQualityLabel);
  if (outputFormat) outputFormat.addEventListener("change", renderList);
  if (fileInput) fileInput.addEventListener("change", (e) => addFiles(e.target.files));
  if (clearBtn) clearBtn.addEventListener("click", clearAll);
  if (convertBtn) convertBtn.addEventListener("click", convertAll);
  if (downloadAllBtn) downloadAllBtn.addEventListener("click", downloadAll);

  if (dropZone) {
    ["dragenter", "dragover"].forEach((evt) =>
      dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.add("dragover");
      })
    );

    ["dragleave", "drop"].forEach((evt) =>
      dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.remove("dragover");
      })
    );

    dropZone.addEventListener("drop", (e) => {
      if (e.dataTransfer?.files) {
        addFiles(e.dataTransfer.files);
      }
    });
  }

  if (compareRange) {
    compareRange.addEventListener("input", (event) => {
      applyComparePosition(event.target.value);
    });
    applyComparePosition(compareRange.value);
  }
  if (compareSelect) {
    compareSelect.addEventListener("change", () => {
      const selected = items.find((item) => item.id === compareSelect.value);
      updateComparePreview(selected || null);
    });
    refreshCompareFromItems();
  }

  setQualityLabel();
  renderList();
}

function initLazyLoadTester() {
  if (!analyzeBtn || !htmlInput) return;

  const clear = () => {
    htmlInput.value = "";
    lazySummary.textContent = "";
    lazyResults.textContent = "";
  };

  const analyze = () => {
    lazySummary.textContent = "";
    lazyResults.textContent = "";

    const doc = new DOMParser().parseFromString(htmlInput.value, "text/html");
    const images = Array.from(doc.querySelectorAll("img"));

    if (images.length === 0) {
      lazySummary.textContent = "No <img> tags found.";
      return;
    }

    let lazyCount = 0;
    let missingSize = 0;
    let missingSrcset = 0;
    let firstIsLazy = false;

    images.forEach((img, index) => {
      const loading = img.getAttribute("loading");
      if (loading === "lazy") lazyCount += 1;
      if (!img.getAttribute("width") || !img.getAttribute("height")) missingSize += 1;
      if (!img.getAttribute("srcset")) missingSrcset += 1;
      if (index === 0 && loading === "lazy") firstIsLazy = true;
    });

    lazySummary.innerHTML = `Found ${images.length} images. Lazy-loaded: ${lazyCount}. Missing dimensions: ${missingSize}. Missing srcset: ${missingSrcset}.`;

    const issues = [];
    if (firstIsLazy) issues.push("First image should load eagerly (remove loading=lazy)." );
    if (missingSize > 0) issues.push("Add width and height to prevent layout shift.");
    if (missingSrcset > 0) issues.push("Add srcset for responsive sizes.");

    lazyResults.innerHTML = issues.length
      ? `<ul>${issues.map((i) => `<li>${i}</li>`).join("")}</ul>`
      : "Looks good. Lazy loading and dimensions are set.";
  };

  analyzeBtn.addEventListener("click", analyze);
  if (clearBtn) clearBtn.addEventListener("click", clear);
}

function initPageSpeedChecker() {
  if (!fileInput || !analyzeBtn || !clearBtn) return;
  let psItems = [];

  const render = () => {
    if (!psTable || !psSummary) return;
    if (psItems.length === 0) {
      psSummary.textContent = "";
      psTable.textContent = "";
      analyzeBtn.disabled = true;
      clearBtn.disabled = true;
      return;
    }

    analyzeBtn.disabled = false;
    clearBtn.disabled = false;

    const rows = psItems
      .map((item) => {
        const warn = [];
        if (item.sizeKB > 300) warn.push("Large (>300 KB)");
        if (item.format !== "webp") warn.push("Consider WebP");
        return `<tr>
          <td>${item.name}</td>
          <td>${item.dimensions}</td>
          <td>${item.sizeKB.toFixed(1)} KB</td>
          <td>${item.format.toUpperCase()}</td>
          <td>${warn.length ? warn.join(", ") : "OK"}</td>
        </tr>`;
      })
      .join("");

    psSummary.textContent = `Analyzed ${psItems.length} images.`;
    psTable.innerHTML = `<table class="data-table">
      <thead>
        <tr>
          <th>File</th>
          <th>Dimensions</th>
          <th>Size</th>
          <th>Format</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`;
  };

  fileInput.addEventListener("change", (e) => {
    psItems = Array.from(e.target.files || []).filter((f) => allowedTypes.has(f.type));
    render();
  });

  if (dropZone) {
    ["dragenter", "dragover"].forEach((evt) =>
      dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.add("dragover");
      })
    );

    ["dragleave", "drop"].forEach((evt) =>
      dropZone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropZone.classList.remove("dragover");
      })
    );

    dropZone.addEventListener("drop", (e) => {
      if (e.dataTransfer?.files) {
        fileInput.files = e.dataTransfer.files;
        psItems = Array.from(e.dataTransfer.files).filter((f) => allowedTypes.has(f.type));
        render();
      }
    });
  }

  analyzeBtn.addEventListener("click", async () => {
    const analyzed = [];
    for (const file of psItems) {
      const bitmap = await createImageBitmap(file);
      analyzed.push({
        name: file.name,
        sizeKB: file.size / 1024,
        format: file.type.split("/")[1] || "unknown",
        dimensions: `${bitmap.width}×${bitmap.height}`,
      });
    }
    psItems = analyzed;
    render();
  });

  clearBtn.addEventListener("click", () => {
    fileInput.value = "";
    psItems = [];
    render();
  });
}

if (["webp-converter", "webp-to-jpg", "webp-to-avif", "image-compressor", "heic-to-webp"].includes(tool)) {
  initFileTool();
}

if (tool === "lazyload-tester") {
  initLazyLoadTester();
}

if (tool === "pagespeed-image-checker") {
  initPageSpeedChecker();
}

document.querySelectorAll(".mobile-toggle").forEach((btn) => {
  btn.addEventListener("click", (event) => {
    event.stopPropagation();
    const menuId = btn.getAttribute("aria-controls");
    const menu = menuId ? document.getElementById(menuId) : null;
    if (!menu) return;
    const open = menu.classList.toggle("open");
    btn.setAttribute("aria-expanded", open ? "true" : "false");
  });
});

document.addEventListener("click", (event) => {
  document.querySelectorAll(".mobile-menu.open").forEach((menu) => {
    const toggle = document.querySelector(`.mobile-toggle[aria-controls=\"${menu.id}\"]`);
    if (!toggle) return;
    if (menu.contains(event.target) || toggle.contains(event.target)) return;
    menu.classList.remove("open");
    toggle.setAttribute("aria-expanded", "false");
  });
});

scheduleThirdPartyBoot();

