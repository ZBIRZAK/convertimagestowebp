# Bulk Image to WebP Converter

Static web app that bulk-converts images (PNG/JPG/JPEG) to WebP in the browser. No uploads, no backend processing. Your images never leave your device.

## Features
- **Fast Processing:** Convert images instantly in your browser
- **Secure & Private:** Images never leave your device
- **No Registration:** Free to use without account
- **High Quality:** Adjustable quality settings (0-100%)
- **Batch Processing:** Convert multiple images at once
- **SEO Optimized:** Fully optimized for search engines

## Deploy to Vercel (recommended)

### Option 1: One-click deployment
[![Deploy to Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https://github.com/ZBIRZAK/ConvWebp)

### Option 2: Manual deployment
1. Install Vercel CLI: `npm i -g vercel`
2. Run `vercel` in your project directory
3. Follow the prompts to deploy

### Option 3: Connect to GitHub
1. Push this repository to GitHub
2. Go to [Vercel dashboard](https://vercel.com/dashboard)
3. Click "Add New..." > "Project"
4. Import your GitHub repository
5. Configure as a static site (no build command needed)

## Deploy (other static hosts)
Deploy the files below to any static host (repeat for each site/domain):
- `index.html`
- `styles.css`
- `app.js`

Because it is fully static, the same build can be hosted on multiple sites with no changes.

## Local preview
Open `index.html` in a browser, or use a simple static server:
```bash
python -m http.server 8080
```
Then open `http://localhost:8080`.

## Technical Notes
- Conversion uses the browser WebP encoder (`canvas.toBlob`).
- Quality slider controls lossy output.
- Files never leave the device.
- SEO optimized with meta tags, structured data and semantic HTML.
- Responsive design works on all devices.

## Optional (CLI version)
If you still want the Python CLI converter, it remains in `convert.py` with `requirements.txt`.
