#!/usr/bin/env python3
"""Vector sketches of two logo ideas for Vinculum (task 0209, "Trademark: the name and the logo").

    python3 make_sketches.py        # writes the .svg files next to this script

These are sketches to brief a designer with, not finished artwork: every shape is plain geometry
with round joins, in one ink colour (the mark must work in one colour first), plus a two-colour
version in the app's pine green and wax. Canvas 1024 x 1024.

A: a bee flying out of its hexagonal cell, through the torn wall ("hatching"); the alternate,
   a-front-*, is the same idea seen from the front, head and antennae breaking through the cap.
B: two hexagonal links, one through the other ("vinculum" is Latin for a bond or link).
"""
import math
from pathlib import Path

HERE = Path(__file__).resolve().parent
INK = "#241D15"
GREEN = "#2F6B4F"
WAX = "#A97C1A"
WAX_TINT = "#EBDDB4"
PAPER = "#FAF8F3"


def pts(points):
    return " ".join(f"{x:.1f},{y:.1f}" for x, y in points)


def svg(body, title, bg=None):
    rect = f'<rect width="1024" height="1024" fill="{bg}"/>' if bg else ""
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">'
            f'<title>{title}</title>{rect}{body}</svg>\n')


# ---------------------------------------------------------------- A: hatching

def front_view(ink=INK, line=INK, accent=None, fill=None, tint=None, w=28):
    """A bee sits head-up in a hexagonal cell. The cell's wax cap has been broken at the top: the
    outline is open there, two torn flaps are folded outward, and the bee's head and antennae come
    through. The body stays inside, striped, with its wings folded."""
    accent = accent or ink
    fill = fill or PAPER
    tint = tint or fill
    cx, cy, R = 512, 568, 350
    V = [(cx + R * math.cos(math.radians(60 * i - 90)), cy + R * math.sin(math.radians(60 * i - 90))) for i in range(6)]
    f = 0.27
    lerp = lambda p, q, t: (p[0] + (q[0] - p[0]) * t, p[1] + (q[1] - p[1]) * t)
    e_left, e_right = lerp(V[0], V[5], f), lerp(V[0], V[1], f)
    s = f'stroke="{line}" stroke-width="{w}" stroke-linejoin="round" stroke-linecap="round"'
    si = f'stroke="{ink}" stroke-width="{w}" stroke-linejoin="round" stroke-linecap="round"'
    o = []
    # the cell: tinted inside, outline open at the top
    o.append(f'<polygon points="{pts(V)}" fill="{tint}" stroke="none"/>')
    o.append(f'<path d="M {e_right[0]:.1f} {e_right[1]:.1f} L {pts([V[1], V[2], V[3], V[4], V[5]])} L {e_left[0]:.1f} {e_left[1]:.1f}" fill="none" {s}/>')
    # the torn cap: two flaps folded outward from the ends of the break
    for sign, e, far in ((1, e_right, V[1]), (-1, e_left, V[5])):
        dx, dy = far[0] - e[0], far[1] - e[1]
        ln = math.hypot(dx, dy)
        ux, uy = dx / ln, dy / ln
        nx, ny = -uy * sign, ux * sign          # outward (up and away from the centre)
        if ny > 0:
            nx, ny = -nx, -ny
        p1 = (e[0] + ux * 0.04 * R, e[1] + uy * 0.04 * R)
        p2 = (e[0] + ux * 0.20 * R + nx * 0.17 * R, e[1] + uy * 0.20 * R + ny * 0.17 * R)
        p3 = (e[0] + ux * 0.02 * R + nx * 0.10 * R, e[1] + uy * 0.02 * R + ny * 0.10 * R)
        o.append(f'<polygon points="{pts([e, p3, p2, p1])}" fill="{fill}" {s}/>')
    # abdomen with stripes and a sting, clipped stripes
    ab = (cx, cy + 66, 168, 214)
    o.append(f'<clipPath id="abdomen"><ellipse cx="{ab[0]}" cy="{ab[1]}" rx="{ab[2]}" ry="{ab[3]}"/></clipPath>')
    o.append(f'<ellipse cx="{ab[0]}" cy="{ab[1]}" rx="{ab[2]}" ry="{ab[3]}" fill="{fill}" {si}/>')
    o.append(f'<g clip-path="url(#abdomen)">' + "".join(
        f'<path d="M {cx - 170} {y} Q {cx} {y + 34} {cx + 170} {y}" fill="none" stroke="{accent}" stroke-width="46"/>'
        for y in (cy + 18, cy + 118)) + '</g>')
    o.append(f'<ellipse cx="{ab[0]}" cy="{ab[1]}" rx="{ab[2]}" ry="{ab[3]}" fill="none" {si}/>')
    o.append(f'<path d="M {cx - 26} {cy + 262} L {cx} {cy + 318} L {cx + 26} {cy + 262}" fill="{ink}" {si}/>')
    # wings, folded along the sides
    for sign in (-1, 1):
        wx = cx + sign * 140
        o.append(f'<ellipse cx="{wx}" cy="{cy - 36}" rx="42" ry="132" fill="{fill}" {s} transform="rotate({sign * -16} {wx} {cy - 36})"/>')
    # thorax and head
    o.append(f'<ellipse cx="{cx}" cy="{cy - 108}" rx="112" ry="84" fill="{fill}" {si}/>')
    o.append(f'<path d="M {cx - 24} {cy - 308} Q {cx - 70} {cy - 400} {cx - 118} {cy - 438} M {cx + 24} {cy - 308} Q {cx + 70} {cy - 400} {cx + 118} {cy - 438}" fill="none" {si}/>')
    o.append(f'<circle cx="{cx - 118}" cy="{cy - 438}" r="19" fill="{ink}"/><circle cx="{cx + 118}" cy="{cy - 438}" r="19" fill="{ink}"/>')
    o.append(f'<circle cx="{cx}" cy="{cy - 244}" r="92" fill="{fill}" {si}/>')
    o.append(f'<circle cx="{cx - 36}" cy="{cy - 250}" r="16" fill="{ink}"/><circle cx="{cx + 36}" cy="{cy - 250}" r="16" fill="{ink}"/>')
    return "".join(o)


# ---------------------------------------------------------------- A: a bee leaving its cell

def flying(ink=INK, line=INK, accent=None, fill=None, tint=None, w=26, heading=-42):
    """A bee in three-quarter view leaves its hexagonal cell, heading up and to the right. It has
    gone through the wall: the outline is simply open where it passed. Its wings are raised, its
    antennae lead, and short speed lines trail behind."""
    accent = accent or ink
    fill = fill or PAPER
    tint = tint or fill
    cx, cy, R = 470, 580, 330
    V = [(cx + R * math.cos(math.radians(60 * i - 90)), cy + R * math.sin(math.radians(60 * i - 90))) for i in range(6)]
    lerp = lambda p, q, t: (p[0] + (q[0] - p[0]) * t, p[1] + (q[1] - p[1]) * t)
    s = f'stroke="{line}" stroke-width="{w}" stroke-linejoin="round" stroke-linecap="round"'
    o = []
    # the cell, tinted inside, with its upper right wall open where the bee went through
    e_in, e_out = lerp(V[0], V[1], 0.20), lerp(V[0], V[1], 0.80)
    o.append(f'<polygon points="{pts(V)}" fill="{tint}" stroke="none"/>')
    o.append(f'<path d="M {e_out[0]:.1f} {e_out[1]:.1f} L {pts([V[1], V[2], V[3], V[4], V[5], V[0]])} L {e_in[0]:.1f} {e_in[1]:.1f}" fill="none" {s}/>')
    # the bee, drawn facing right in its own frame, then turned to its heading
    k = 0.92
    wl = w / k
    sb = f'stroke="{ink}" stroke-width="{wl:.1f}" stroke-linejoin="round" stroke-linecap="round"'
    sl = f'stroke="{line}" stroke-width="{wl:.1f}" stroke-linejoin="round" stroke-linecap="round"'
    b = []
    # speed lines behind the bee
    for dy, ln in ((-60, 90), (6, 140), (72, 90)):
        b.append(f'<path d="M {-300 - ln} {dy} H {-300}" fill="none" stroke="{accent}" stroke-width="{wl * 0.8:.1f}" stroke-linecap="round"/>')
    # far wing, then near wing (swept back and up)
    b.append(f'<ellipse cx="44" cy="-228" rx="54" ry="150" fill="{tint}" {sl} transform="rotate(6 44 -228)"/>')
    # abdomen with stripes and a sting
    b.append('<clipPath id="belly"><ellipse cx="-120" cy="0" rx="190" ry="118"/></clipPath>')
    b.append(f'<ellipse cx="-120" cy="0" rx="190" ry="118" fill="{fill}" {sb}/>')
    b.append('<g clip-path="url(#belly)">' + "".join(
        f'<path d="M {x} -140 Q {x + 44} 0 {x} 140" fill="none" stroke="{accent}" stroke-width="{wl * 1.5:.1f}"/>'
        for x in (-165, -85)) + '</g>')
    b.append(f'<ellipse cx="-120" cy="0" rx="190" ry="118" fill="none" {sb}/>')
    b.append(f'<path d="M -300 -22 L -366 6 L -300 30" fill="{ink}" {sb}/>')
    # thorax, near wing, head
    b.append(f'<circle cx="62" cy="-8" r="84" fill="{fill}" {sb}/>')
    b.append(f'<ellipse cx="112" cy="-212" rx="60" ry="166" fill="{fill}" {sl} transform="rotate(24 112 -212)"/>')
    b.append(f'<path d="M 190 -52 Q 252 -116 332 -128 M 214 -40 Q 292 -76 360 -50" fill="none" {sb}/>')
    b.append(f'<circle cx="332" cy="-128" r="{19 / k:.1f}" fill="{ink}"/><circle cx="360" cy="-50" r="{19 / k:.1f}" fill="{ink}"/>')
    b.append(f'<ellipse cx="206" cy="12" rx="82" ry="88" fill="{fill}" {sb}/>')
    b.append(f'<circle cx="232" cy="-4" r="{16 / k:.1f}" fill="{ink}"/>')
    o.append(f'<g transform="translate({cx - 35} {cy - 103}) rotate({heading}) scale({k})">' + "".join(b) + '</g>')
    return '<g transform="translate(38 -26)">' + "".join(o) + '</g>'


# ---------------------------------------------------------------- B: linked hexagons

def hexagon(cx, cy, r):
    return [(cx + r * math.cos(math.radians(60 * i - 90)), cy + r * math.sin(math.radians(60 * i - 90))) for i in range(6)]


def linked(c1=INK, c2=INK, w=44, gap=26, r=270, d=165):
    """Two point-up hexagon rings side by side, overlapping. They cross twice, at the top and the
    bottom of the overlap; at the top the left ring passes over, at the bottom the right one does.
    The gap where a ring passes under is cut with a mask, so the art has no background colour."""
    cy = 512
    ax, bx = 512 - d, 512 + d
    A, B = hexagon(ax, cy, r), hexagon(bx, cy, r)
    t = d / (0.866 * r)
    y_top = cy - r + t * r / 2
    y_bot = cy + r - t * r / 2
    reach = w * 1.7
    poly = lambda P, c, sw: (f'<polygon points="{pts(P)}" fill="none" stroke="{c}" stroke-width="{sw}" '
                             f'stroke-linejoin="round"/>')
    cut = w + gap * 2
    defs = (
        f'<defs>'
        f'<clipPath id="top"><circle cx="512" cy="{y_top:.1f}" r="{reach:.1f}"/></clipPath>'
        f'<clipPath id="bottom"><circle cx="512" cy="{y_bot:.1f}" r="{reach:.1f}"/></clipPath>'
        # left ring: hidden near the bottom crossing, where the right ring passes over it
        f'<mask id="maskA" maskUnits="userSpaceOnUse" x="0" y="0" width="1024" height="1024">'
        f'<rect width="1024" height="1024" fill="white"/>'
        f'<g clip-path="url(#bottom)">{poly(B, "black", cut)}</g></mask>'
        # right ring: hidden near the top crossing, where the left ring passes over it
        f'<mask id="maskB" maskUnits="userSpaceOnUse" x="0" y="0" width="1024" height="1024">'
        f'<rect width="1024" height="1024" fill="white"/>'
        f'<g clip-path="url(#top)">{poly(A, "black", cut)}</g></mask>'
        f'</defs>'
    )
    return (defs + f'<g mask="url(#maskA)">{poly(A, c1, w)}</g>'
            + f'<g mask="url(#maskB)">{poly(B, c2, w)}</g>')


def write(name, body, title, bg=None):
    (HERE / name).write_text(svg(body, title, bg))


def main():
    write("a-hatching-mono.svg", flying(), "Sketch A: a bee flying out of its cell, one colour")
    write("a-hatching-colour.svg", flying(line=GREEN, accent=WAX, tint=WAX_TINT),
          "Sketch A: a bee flying out of its cell, pine green and wax")
    write("a-front-mono.svg", front_view(), "Sketch A, front view: a bee breaking through the cell cap, one colour")
    write("a-front-colour.svg", front_view(ink=INK, line=GREEN, accent=WAX, tint=WAX_TINT),
          "Sketch A, front view: a bee breaking through the cell cap, pine green and wax")
    write("b-linked-mono.svg", linked(), "Sketch B: two linked hexagons, one colour")
    write("b-linked-colour.svg", linked(GREEN, WAX), "Sketch B: two linked hexagons, pine green and wax")


if __name__ == "__main__":
    main()
