---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[ios-field-app]]"
area: app
created: 2026-10-06
branch: feature/0214-ios-app-illustrations-round-2
release:
depends-on: ["[[0211-ios-app-illustrations]]"]
blocked-by:
---
# Task: iOS app: illustrations for the remaining empty and failure screens

## Context
[[0211-ios-app-illustrations]] gave ten screens a picture. Run on a real phone (2026-10-06)
the owner liked them and asked for more. This note surveys every state the app can show,
decides which still deserve a picture, and drafts a concept and a ready-to-paste Gemini prompt
for each, using what 0211 learned. Part of [[ios-field-app]].

The 0211 rule stands: a picture belongs on a screen that has **a message and no data**, never
on a screen with data and never on a banner over something else.

## The survey
| State | Today | Decision |
|---|---|---|
| Connect, sign-in, no apiaries, no hives, no inspections, all clear, outbox empty, outbox offline, scanner without camera, server unreachable | picture (0211) | done |
| **Map with no apiary positions** | hexagon badge | **new `mapEmpty`** |
| **Hive page, no queen recorded** (an inline card, like "no inspections") | one line of text | **new `noQueen`** (small `.spot`) |
| **A load that failed for any reason but "cannot reach the server"** (apiaries, hives, alerts, the record form's schema) | hexagon badge | **new `serverTrouble`** |
| **Connect: the server answered but is not set up for the app** (`notHiveLog`, `inconsistent`) | banner under the `connect` picture | **`serverTrouble`**, swapped in for the hero |
| **Connect: the server is newer than the app** (`unsupportedVersion`), and a load whose answer the app cannot read (`malformed`) | banner / badge | **new `updateApp`** |
| Scanner: not a hive label, a label for another server, hive not found | banner over the camera | none: a banner, not an empty screen |
| Outbox "needs attention", sign-in refused, session ended, signed out | list and banners | none; session ended and signed out land on the connect screen, which has its picture |
| Photos, saving, validation | inline | none |

So **four new pictures** cover five states: `mapEmpty`, `noQueen`, `serverTrouble` (used twice)
and `updateApp`. That makes fourteen in all.

## Acceptance criteria
- [x] Concepts agreed (below); the four pictures made, reviewed against the 0211 checklist
      (one hand, one line weight, one colour in two strengths, fits the hexagon) and processed
- [x] `process.py` can draw a crown (and any one-off polyline) from the manifest, for `noQueen`
- [x] `Illustration` gains `mapEmpty`, `noQueen`, `serverTrouble`, `updateApp`; each used where
      the survey says; the `.spot` size for `noQueen`
- [x] Hidden at accessibility text sizes and in landscape, as the others are (the same code)
- [x] Tests: every case still has its asset, size budget, inside the hexagon, centred
- [x] README's list of pictures updated
- [ ] Seen on a simulator and a phone

## Concepts
All use the sheet's objects only (side and front bee, the three-box hive, a comb frame, a flower,
a hexagon) plus the lantern from `connect`, and geometry the script can draw. The 0211 lessons
apply: **at most two corrections, then change the concept or build a composite; nothing that must
stay inside the frame (a ground, a path to the edge); geometry (a crown, a dashed hexagon) is
the script's job, not the generator's.**

1. **`mapEmpty`: "None of your apiaries has a position yet."** One side-view bee flying a
   dashed loop around an empty middle: *searching, nowhere to land yet.* The dashed line worked
   in `welcome` and `unreachable`.
2. **`noQueen`: "No queen recorded for this hive."** A double-outlined hexagon like `noHives`
   with a **crown** above it: *an empty cell waiting for a queen.* **Drawn by the script, no
   generator at all** (previewed on 2026-10-06: it sits in the family in light and dark and is
   clearly distinct from `noHives`, whose hexagon has a bee above it).
3. **`serverTrouble`: "the server's lantern is out."** The `connect` picture's hive and lantern,
   with the lantern **unlit** (no flame, no rays). Used for a failed load that is not a network
   failure, and for a server that answered but is not set up for the app.
4. **`updateApp`: "this server is newer than the app."** Two comb frames side by side, the
   right one a little larger with more cells, and one side bee resting on the left frame looking
   toward it: *a newer frame than the one you have.*

## Prompts (for Gemini: a new chat each, with the model sheet attached)
The block is the 0211 step 2 prompt. Substitute the scene; for `serverTrouble` also attach the
`connect` picture.

### `mapEmpty`
```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: One bee, drawn exactly like the sheet's side bee (simple round body, two stripes, two oval wings, dot eye, two short antennae; no legs, no neck, no extra detail), flying clockwise along a dashed circular line. The dashed circle is about 40 % of the picture wide, made of about ten dashes, drawn with the same line weight as everything else. The bee is on the circle at the upper left. The middle of the circle is empty. Nothing else.
Composition: The circle centred; the bee about a fifth of the picture wide.
Mood: Curious, searching.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; a single bee only; there is no hive, no flower, no ground and
no arrow heads; no more than four bees; no colour, gradients, shading or shadows; no text,
letters, numbers, logos or signature; no people, hands or faces. Return only the image.
```

### `serverTrouble` (attach the sheet **and** the `connect` picture)
```text
Attached are a model sheet and an earlier picture of a hive with a lit lantern beside it. Draw
the same hive and the same lantern again, in exactly the same style, line weight and ink values,
and with the same composition (the hive on the left, the lantern on the right), but with the
lantern NOT lit: no flame inside it and no rays around it. The lantern's glass is empty. Nothing
else.

Mood: Quiet, a little concerned.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what is described and
nothing else; the hive is the sheet's hive, three boxes under a gabled lid, never a single box;
no bees; no colour, gradients, shading or shadows; no text, letters, numbers, logos or
signature; no people, hands or faces. Return only the image.
```
If the lantern comes back lit twice, composite instead: the hive from `connect` with a lantern
drawn by the script (a cage outline, no flame), or reuse `connect` cropped without its rays.

### `updateApp`
```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: Two comb frames standing side by side, each drawn exactly like the sheet's frame (a rectangle filled with a regular hexagonal comb pattern in the grey value). The left frame is a little smaller, with about 25 cells. The right frame is a little larger, with about 50 cells, in the same pattern. One bee, drawn exactly like the sheet's side bee (simple round body, two stripes, two oval wings, dot eye, two short antennae; no legs, no neck, no extra detail), rests on top of the left frame and faces the right frame. Nothing else.
Composition: The two frames side by side with a small gap, centred as a group; the bee on the upper edge of the left frame.
Mood: Curious, hopeful.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; exactly two frames and one bee; no more than four bees; no
colour, gradients, shading or shadows; no text, letters, numbers, logos or signature; no people,
hands or faces. Return only the image.
```

### `noQueen` (no prompt: the script draws it)
A manifest entry, no source file:
```json
{"case": "noQueen", "compose": {
  "hexagon": {"cx": 512, "cy": 700, "radius": 190, "gap": 38, "line": 11},
  "polylines": [{"line": 11, "closed": true, "points": [[402,430],[402,330],[457,380],[512,300],[567,380],[622,330],[622,430]]}]}}
```
`process.py`'s `composed()` already draws the double hexagon (for `noHives`); it needs a
`source` that is optional (a blank canvas) and a `polylines` list.

## Implementation notes
- App wiring: `ApiaryMap`'s empty state (`mapEmpty`); the hive page's "No queen recorded"
  card (`noQueen`, `.spot`, as the "no inspections" card does it); `ErrorView` (`serverTrouble`
  unless the message is the unreachable one, or `updateApp` for the unreadable-answer message);
  the connect screen's hero by the error shown (`notHiveLog` or `inconsistent` →
  `serverTrouble`, `unsupportedVersion` → `updateApp`).
- This is the same pipeline as 0211: `Tools/illustrations/` (add the sources and manifest
  entries, run `process.py`), `Resources/Illustrations`, `Illustration.swift`, the tests.
- Before generating, decide whether four is enough or if the owner wants more states
  pictured; each extra one costs a generator round and a review.

## What was built (app repo, branch `feature/0214-illustrations-round-2`)
- **Three generated pictures, each right on the first try** (against 0211's several rounds: the
  closed vocabulary, the model sheet and the "draw only what the scene names" lines worked): 
  `mapEmpty` (a bee and a dashed loop; the bee sits on the loop's upper left, which reads as
  "searching"), `serverTrouble` (the hive and an unlit lantern; a larger, plainer lantern than
  `connect`'s, unlit as asked, same style), `updateApp` (two frames, the right one larger, a bee
  on the left one). The prompts in this note were used as written. No correction was needed.
- **`noQueen` drawn by the script**: `process.py`'s `composed()` now takes an optional source
  (a blank canvas when absent) and a `polylines` list beside the hexagon; the manifest entry
  has no source picture. It sits in the family in light and dark.
- `Illustration` has four more cases (fourteen). Where each is used:
  `mapEmpty` on the map's empty state; `noQueen` in the hive page's "no queen" card, the small
  size, as "no inspections" does it; `ErrorView` chooses by what went wrong
  (`Messages.illustration(forFailure:)`: cannot reach the server → `unreachable`, an answer the
  app cannot read → `updateApp`, the server failed (5xx) or a catch-all → `serverTrouble`; a
  refusal of the person, a firewall or a vanished record keep the badge); the connect screen's
  picture follows why connecting failed (`AppModel.connectFailure`: not HiveLog or inconsistent
  settings → `serverTrouble`, a server newer than the app → `updateApp`, otherwise the lit
  lantern).
- Tests (4 new, plus the existing picture tests which run over all fourteen): the failure to
  picture mapping, the plain badge kept for the others, the connect screen's choice, the model
  remembering and forgetting why connecting failed. Mac 227 tests pass. Sizes: the largest is
  `updateApp` at 60 KB; the set is under the 600 KB budget.
- Seen in the Mac render (`AssetRenderTests`, opt-in): the contact sheet of all fourteen and the
  empty and failure screens, light and dark.

### Not verified
- On a simulator or a phone. These states are hard to reach with the dev site (a server that is
  not HiveLog, a newer one, one that fails); the render and the unit tests cover them.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
