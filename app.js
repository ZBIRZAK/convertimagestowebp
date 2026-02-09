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

const lazySummary = document.getElementById("lazySummary");
const lazyResults = document.getElementById("lazyResults");
const htmlInput = document.getElementById("htmlInput");

const psSummary = document.getElementById("psSummary");
const psTable = document.getElementById("psTable");

const allowedTypes = new Set(["image/png", "image/jpeg", "image/webp"]);
let items = [];

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
  for (const file of fileList) {
    if (!allowedTypes.has(file.type)) {
      continue;
    }
    if (tool === "webp-converter" && file.type === "image/webp") continue;
    if (tool === "webp-to-jpg" && file.type !== "image/webp") continue;

    items.push({
      file,
      blob: null,
      url: null,
      outputName: getOutputName(file.name),
      error: null,
    });
  }
  renderList();
}

function getOutputName(name) {
  if (tool === "webp-to-jpg") return name.replace(/\.[^.]+$/, "") + ".jpg";
  if (tool === "image-compressor" && outputFormat?.value === "jpg") {
    return name.replace(/\.[^.]+$/, "") + ".jpg";
  }
  return name.replace(/\.[^.]+$/, "") + ".webp";
}

function clearAll() {
  for (const item of items) {
    if (item.url) URL.revokeObjectURL(item.url);
  }
  items = [];
  if (fileInput) fileInput.value = "";
  renderList();
}

async function convertFile(item, quality) {
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
  if (tool === "webp-to-jpg" || (tool === "image-compressor" && outputFormat?.value === "jpg")) {
    mime = "image/jpeg";
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

  for (const item of items) {
    item.error = null;
    if (item.url) URL.revokeObjectURL(item.url);
    item.url = null;
    item.blob = null;
  }
  renderList();

  for (const item of items) {
    try {
      const blob = await convertFile(item, quality);
      item.blob = blob;
      item.url = URL.createObjectURL(blob);
      item.outputName = getOutputName(item.file.name);
    } catch (err) {
      item.error = err.message || "Failed";
    }
    renderList();
  }
}

function downloadAll() {
  for (const item of items) {
    if (!item.blob || !item.url) continue;
    const a = document.createElement("a");
    a.href = item.url;
    a.download = item.outputName;
    document.body.appendChild(a);
    a.click();
    a.remove();
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

if (["webp-converter", "webp-to-jpg", "image-compressor"].includes(tool)) {
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
