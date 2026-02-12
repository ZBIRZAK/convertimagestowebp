# WebP Commerce Toolkit (Multisite Static)

An e-commerce focused image performance micro-platform.

## Pages
- `index.html`
- `tools/webp-converter.html`
- `tools/webp-to-jpg.html`
- `tools/image-compressor.html`
- `tools/lazyload-tester.html`
- `tools/pagespeed-image-checker.html`
- `guides/what-is-webp.html`
- `guides/webp-vs-jpg-png-2026.html`
- `guides/webp-seo.html`
- `guides/webp-for-wordpress.html`
- `guides/wordpress-webp-plugin.html`
- `guides/webp-for-woocommerce.html`
- `guides/webp-for-ecommerce.html`
- `guides/product-image-optimization.html`
- `wordpress-plugin/webp-migrator/`

## Deploy (multisite)
Deploy the entire folder to any static host. Repeat for each site/domain. No backend required.

## Local preview
```powershell
cd "c:\Users\PC\Documents\test ide chatgtp\img-to-webp"
python -m http.server 8080
```
Then open `http://localhost:8080`.

## Notes
- Conversion uses the browser WebP encoder (`canvas.toBlob`).
- Files never leave the device.

