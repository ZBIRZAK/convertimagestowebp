# img-to-webp (Web App)

Static web app that bulk-converts images (PNG/JPG/JPEG/JPAG) to WebP in the browser. No uploads, no backend.

## Deploy (multisite ready)
Deploy the files below to any static host (repeat for each site/domain):
- `index.html`
- `styles.css`
- `app.js`

Because it is fully static, the same build can be hosted on multiple sites with no changes.

## Local preview
Open `index.html` in a browser, or use a simple static server:
```powershell
python -m http.server 8080
```
Then open `http://localhost:8080`.

## Notes
- Conversion uses the browser WebP encoder (`canvas.toBlob`).
- Quality slider controls lossy output.
- Files never leave the device.

## Optional (CLI version)
If you still want the Python CLI converter, it remains in `convert.py` with `requirements.txt`.
