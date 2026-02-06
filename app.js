const fileInput = document.getElementById("fileInput");
const dropZone = document.getElementById("dropZone");
const list = document.getElementById("list");
const convertBtn = document.getElementById("convertBtn");
const downloadAllBtn = document.getElementById("downloadAllBtn");
const clearBtn = document.getElementById("clearBtn");
const qualityInput = document.getElementById("quality");
const qualityValue = document.getElementById("qualityValue");

const allowedTypes = new Set(["image/png", "image/jpeg"]);

let items = [];

function updateQualityLabel() {
  qualityValue.textContent = qualityInput.value;
}

function bytesToSize(bytes) {
  if (!bytes) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

function renderList() {
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
    items.push({
      file,
      blob: null,
      url: null,
      outputName: file.name.replace(/\.[^.]+$/, "") + ".webp",
      error: null,
    });
  }
  renderList();
}

function clearAll() {
  for (const item of items) {
    if (item.url) URL.revokeObjectURL(item.url);
  }
  items = [];
  fileInput.value = "";
  renderList();
}

async function convertFile(item, quality) {
  const imageBitmap = await createImageBitmap(item.file);
  const canvas = document.createElement("canvas");
  canvas.width = imageBitmap.width;
  canvas.height = imageBitmap.height;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(imageBitmap, 0, 0);

  const blob = await new Promise((resolve) =>
    canvas.toBlob(resolve, "image/webp", quality)
  );

  if (!blob) {
    throw new Error("WebP conversion failed");
  }

  return blob;
}

async function convertAll() {
  const quality = Number(qualityInput.value) / 100;

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

qualityInput.addEventListener("input", updateQualityLabel);
fileInput.addEventListener("change", (e) => addFiles(e.target.files));
clearBtn.addEventListener("click", clearAll);
convertBtn.addEventListener("click", convertAll);
downloadAllBtn.addEventListener("click", downloadAll);

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

updateQualityLabel();
renderList();
