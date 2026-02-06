from __future__ import annotations

import argparse
from pathlib import Path
from typing import Iterable

from PIL import Image

ALLOWED_EXTS = {".png", ".jpg", ".jpeg", ".jpag"}


def iter_images(root: Path, recursive: bool) -> Iterable[Path]:
    pattern = "**/*" if recursive else "*"
    for path in root.glob(pattern):
        if path.is_file() and path.suffix.lower() in ALLOWED_EXTS:
            yield path


def build_output_path(src: Path, input_dir: Path, output_dir: Path, keep_structure: bool) -> Path:
    if keep_structure:
        rel = src.relative_to(input_dir)
        out = output_dir / rel
    else:
        out = output_dir / src.name
    return out.with_suffix(".webp")


def prepare_image(img: Image.Image) -> Image.Image:
    if img.mode in ("RGBA", "LA", "P"):
        return img.convert("RGBA")
    return img.convert("RGB")


def main() -> int:
    script_dir = Path(__file__).resolve().parent

    parser = argparse.ArgumentParser(description="Bulk convert images to WebP.")
    parser.add_argument("--input", type=Path, default=script_dir / "input", help="Input folder")
    parser.add_argument("--output", type=Path, default=script_dir / "output", help="Output folder")
    parser.add_argument("--quality", type=int, default=85, help="Quality 0-100 (lossy)")
    parser.add_argument("--lossless", action="store_true", help="Use lossless WebP")
    parser.add_argument("--recursive", action="store_true", help="Scan subfolders")
    parser.add_argument("--keep-structure", action="store_true", help="Mirror input folders under output")
    parser.add_argument("--overwrite", action="store_true", help="Overwrite existing outputs")

    args = parser.parse_args()

    input_dir = args.input.resolve()
    output_dir = args.output.resolve()

    if not input_dir.exists() or not input_dir.is_dir():
        print(f"Input folder not found: {input_dir}")
        return 1

    output_dir.mkdir(parents=True, exist_ok=True)

    converted = 0
    skipped = 0
    failed = 0

    for src in iter_images(input_dir, args.recursive):
        out = build_output_path(src, input_dir, output_dir, args.keep_structure)
        if out.exists() and not args.overwrite:
            skipped += 1
            continue

        out.parent.mkdir(parents=True, exist_ok=True)

        try:
            with Image.open(src) as img:
                prepared = prepare_image(img)
                prepared.save(
                    out,
                    "WEBP",
                    quality=max(0, min(100, args.quality)),
                    lossless=args.lossless,
                    method=6,
                )
            converted += 1
        except Exception as exc:
            failed += 1
            print(f"Failed: {src} ({exc})")

    print(f"Converted: {converted}, Skipped: {skipped}, Failed: {failed}")
    return 0 if failed == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
