#!/usr/bin/env python3
"""Builds logo-sketches-sheet.png: each sketch large, in one colour and two, at 140 / 64 / 32 px,
and as an app-icon tile. Needs Inkscape (SVG to PNG) and Pillow. Run make_sketches.py first."""
import subprocess
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).resolve().parent
PAPER, CARD, INK, MUTED = (250, 248, 243), (243, 238, 228), (36, 29, 21), (111, 101, 85)
ROWS = [("A  A bee leaves its cell", "a-hatching"), ("A (alternate)  Front view, through the cap", "a-front"),
        ("B  Vinculum: two linked hexagons", "b-linked")]
SIZES = (440, 140, 64, 32)


def font(name, size):
    try:
        return ImageFont.truetype(name, size)
    except OSError:
        return ImageFont.load_default()


def main():
    big = font("/System/Library/Fonts/Supplemental/Georgia.ttf", 28)
    small = font("/System/Library/Fonts/Supplemental/Arial.ttf", 17)
    row_h = 440
    im = Image.new("RGB", (1560, 20 + row_h * len(ROWS)), PAPER)
    d = ImageDraw.Draw(im)
    with tempfile.TemporaryDirectory() as tmp:
        def png(name, w):
            out = Path(tmp) / f"{name}-{w}.png"
            if not out.exists():
                subprocess.run(["inkscape", str(HERE / f"{name}.svg"), "--export-type=png",
                                f"--export-filename={out}", "-w", str(w)], check=True, capture_output=True)
            return Image.open(out).convert("RGBA")

        def put(name, w, xy):
            p = png(name, w)
            im.paste(p, xy, p)

        for r, (title, key) in enumerate(ROWS):
            y0 = 12 + r * row_h
            d.text((30, y0), title, fill=INK, font=big)
            put(f"{key}-mono", 380, (30, y0 + 44))
            put(f"{key}-colour", 380, (440, y0 + 44))
            x = 880
            d.text((x, y0 + 50), "one colour, 140 / 64 / 32 px", fill=MUTED, font=small)
            xx = x
            for w in (140, 64, 32):
                put(f"{key}-mono", w, (xx, y0 + 84)); xx += w + 24
            d.text((x, y0 + 236), "two colours, same sizes", fill=MUTED, font=small)
            xx = x
            for w in (140, 64, 32):
                put(f"{key}-colour", w, (xx, y0 + 270)); xx += w + 24
            tile = Image.new("RGBA", (180, 180), (0, 0, 0, 0))
            ImageDraw.Draw(tile).rounded_rectangle([0, 0, 179, 179], radius=40, fill=CARD + (255,),
                                                   outline=(231, 224, 209, 255), width=2)
            icon = png(f"{key}-colour", 140).resize((132, 132))
            tile.paste(icon, (24, 24), icon)
            im.paste(tile, (1330, y0 + 84), tile)
            d.text((1330, y0 + 276), "app icon tile", fill=MUTED, font=small)
    im.save(HERE / "logo-sketches-sheet.png")


if __name__ == "__main__":
    main()
