#!/usr/bin/env python3
"""Clean vector redraw of the owner's edited logo candidate (task 0209, "Trademark: the name and the logo").

    python3 make_candidate.py       # writes the candidate-*.svg files next to this script

The owner's picture is a bee leaving a point-up hexagonal cell, in the app's own palette and its naive
line style. This redraws it as plain geometry (a hexagon outline, ellipses, Beziers; no embedded raster,
no clipping artefacts) so there is one owned, scalable source file. It is a faithful trace of the shapes,
not a new design, so the proportions are the owner's. Canvas 1024 x 1024.

Files:
  candidate-colour.svg   the mark in the picture's colours, on a transparent background
  candidate-mono.svg     one ink colour (the mark must work in one colour; this is also what a
                         black-and-white filing would use)
  candidate-lockup.svg   mark above the name, "Vinculum Apis". The name is live text, NOT outlines:
                         it is set in a generic bold sans-serif as a placeholder. Before it is filed or
                         printed, have it drawn or set in a font whose licence allows a logo.
"""
from pathlib import Path

HERE = Path(__file__).resolve().parent

# Colours sampled from the owner's picture.
TEAL = "#01736F"
WAX = "#F0AA25"
INK = "#111818"
WHITE = "#FDFDFB"
GREEN = "#317E64"      # "Vinculum" in the picture
GOLD = "#FBB117"       # "Apis" in the picture

HEX_CX, HEX_CY, HEX_R, HEX_W = 512, 621, 335, 60      # centre line of the cell wall
LINE = 15                                              # the bee's outline weight


def hexagon_path(cx, cy, r):
    import math
    p = [(cx + r * math.cos(math.radians(60 * i - 90)), cy + r * math.sin(math.radians(60 * i - 90))) for i in range(6)]
    return "M " + " L ".join(f"{x:.1f} {y:.1f}" for x, y in p) + " Z"


def mark(cell=TEAL, ink=INK, wax=WAX, white=WHITE, mono=False):
    """The bee and its cell. In mono the only colour is the ink: white wings and head, solid ink stripes,
    so it prints in one colour (a white fill is paper, not a second ink)."""
    s = f'stroke="{ink}" stroke-width="{LINE}" stroke-linejoin="round" stroke-linecap="round"'
    fill_w = "#FFFFFF" if mono else wax       # wings
    fill_p = "#FFFFFF" if mono else white     # head
    # the body: abdomen with a tapering sting at the lower right
    body = ("M 395 742 C 430 650 500 615 560 612 L 690 612 C 742 650 776 742 766 815 "
            "L 796 850 C 762 858 732 862 702 876 C 640 906 520 900 455 850 C 425 825 405 790 395 742 Z")
    o = []
    o.append(f'<path d="{hexagon_path(HEX_CX, HEX_CY, HEX_R)}" fill="none" stroke="{cell}" stroke-width="{HEX_W}" stroke-linejoin="miter"/>')
    # wings (the far one first)
    o.append(f'<path d="M 560 592 C 535 470 585 320 680 292 C 725 282 755 320 768 352 C 700 380 620 480 560 592 Z" fill="{fill_w}" {s}/>')
    o.append(f'<path d="M 556 602 C 590 470 700 372 790 364 C 842 362 862 412 848 456 C 830 536 720 604 556 602 Z" fill="{fill_w}" {s}/>')
    # the body, with its stripes: wax bands outlined in ink, clipped to the body shape
    o.append(f'<clipPath id="body"><path d="{body}"/></clipPath>')
    o.append(f'<path d="{body}" fill="{white if not mono else "none"}"/>')
    bands = []
    for x_top, x_bot in ((588, 452), (694, 566)):
        d = f"M {x_top} 600 Q {x_bot + 105} {760} {x_bot} 900"
        bands.append((d, 52))
    if mono:
        for d, w in bands:
            o.append(f'<path d="{d}" fill="none" stroke="{ink}" stroke-width="{w + 2 * LINE}" clip-path="url(#body)"/>')
    else:
        for d, w in bands:
            o.append(f'<path d="{d}" fill="none" stroke="{ink}" stroke-width="{w + 2 * LINE}" clip-path="url(#body)"/>')
            o.append(f'<path d="{d}" fill="none" stroke="{wax}" stroke-width="{w}" clip-path="url(#body)"/>')
    o.append(f'<path d="{body}" fill="none" {s}/>')
    # head, neck, antennae, eye
    o.append(f'<path d="M 497 553 Q 522 540 550 549" fill="none" {s}/>')
    o.append(f'<path d="M 342 497 Q 300 432 247 413 M 392 480 Q 386 412 326 374" fill="none" {s}/>')
    o.append(f'<circle cx="245" cy="412" r="22" fill="{ink}"/><circle cx="325" cy="372" r="22" fill="{ink}"/>')
    o.append(f'<ellipse cx="372" cy="616" rx="124" ry="142" transform="rotate(-8 372 616)" fill="{fill_p}" {s}/>')
    o.append(f'<circle cx="346" cy="592" r="30" fill="{ink}"/>')
    return "".join(o)


def svg(body, title, w=1024, h=1024, vb=None):
    vb = vb or f"0 0 {w} {h}"
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" width="{w}" height="{h}">'
            f'<title>{title}</title>{body}</svg>\n')


def lockup():
    """The mark above the name, on a 1024 x 1360 canvas. Live text; see the module docstring."""
    text = (f'<text x="512" y="1250" text-anchor="middle" font-family="Helvetica Neue, Helvetica, Arial, sans-serif" '
            f'font-weight="800" font-size="128" letter-spacing="-2">'
            f'<tspan fill="{GREEN}">Vinculum</tspan><tspan fill="{GOLD}" dx="34">Apis</tspan></text>')
    return svg(f'<g transform="translate(0 20)">{mark()}</g>{text}', "Vinculum Apis: bee leaving its cell, with the name", 1024, 1360)


def main():
    (HERE / "candidate-colour.svg").write_text(svg(mark(), "Vinculum: a bee leaving its cell, colour"))
    (HERE / "candidate-mono.svg").write_text(svg(mark(cell=INK, mono=True), "Vinculum: a bee leaving its cell, one colour"))
    (HERE / "candidate-lockup.svg").write_text(lockup())


if __name__ == "__main__":
    main()
