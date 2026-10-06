---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ios-field-app]]"
area: app
created: 2026-10-05
branch: feature/0211-ios-app-illustrations
release:
depends-on: ["[[0210-ios-app-hivelog-look-and-feel]]"]
blocked-by:
---
# Task: iOS app: a small set of AI-generated illustrations in the beeswax style

## Context
Asked for after the look-and-feel work ([[0210-ios-app-hivelog-look-and-feel]]): add a few
selected AI-generated images that fit the app's style and the message of the page they sit on,
while the app stays visually coherent. The generator is **Gemini** (first planned with
DALL·E, which was not working); the images are tinted with a **wax colour** in the app
rather than used in the colours the generator picks.
Part of [[ios-field-app]].

The approach, in one paragraph: the images are a small *closed system*, like the fonts and
the hexagon were. A written style spec and one prompt template produce single-ink line art;
a script turns each chosen image into a vector; the app draws it as a template image in a wax
tint taken from the theme's tokens, inside the same hexagon frame. Pictures appear only on
screens that have a message and no data (first run, empty, all clear, offline), never on
working screens. About ten images in all.

## Acceptance criteria
- [x] Style spec and prompt template (below) agreed; wax token decided (see Decisions)
- [x] **Model sheet** generated and chosen: one image of the recurring objects that anchors
      the whole set (see "Prompts for Gemini")
- [x] **Pilot of three** (welcome, all clear, offline) generated, processed and looked at
      together in light and dark; go / no-go recorded here before the rest are made
      (round 2 passes, with the size and line-weight normalisation below; see "Pilot findings")
- [x] The remaining images generated and chosen (`noHives` composed, see Round 8); a manifest
      records where each came from (the model name Gemini showed was not recorded for these, and
      the exact prompts are in this note)
- [x] Processing script in the app repo (`Tools/illustrations/`): raw PNG to vector, repeatable
- [x] `Illustration` enum and one `IllustrationView` in `Design/`; `EmptyState` takes an
      illustration and the first screens use `IllustrationHero`; nothing else places one
- [x] Light and dark both read well; decorative to VoiceOver; hidden in landscape and at the
      largest text sizes so the page content is never pushed off screen
- [x] Tests: every case has its asset, size budget held, wax tint contrast on both grounds,
      no colour outside the tokens (see Verification)
- [x] README "Look and feel" gains an "Illustrations" section; licensing and provenance noted
- [x] Looked at on a physical phone (2026-10-06: "illustrations OK"; more of them wanted)

## Implementation notes

### Where pictures go, and where they do not
One picture per message, only where there is no data to show:

| Case            | Screen                            | What it says                                |
| --------------- | --------------------------------- | ------------------------------------------- |
| `welcome`       | Welcome                           | Your hives, in your pocket                  |
| `connect`       | Connect to a server               | Find your HiveLog                           |
| `unreachable`   | Server not reachable              | Cannot reach it just now                    |
| `noApiaries`    | Apiaries, empty                   | Start by adding an apiary on the website    |
| `noHives`       | Apiary with no hives              | An empty cell waiting for a hive            |
| `noInspections` | Hive with no inspections          | Nothing logged yet                          |
| `allClear`      | Alerts, empty                     | Nothing needs attention                     |
| `outboxEmpty`   | Outbox, empty                     | Everything has been sent                    |
| `offline`       | Outbox with items, no signal      | Held safely, will send when there is signal |
| `cameraNeeded`  | Scanner without camera permission | Allow the camera to scan labels             |

Never on forms, lists with data, the hive page or the map. In the field people wear gloves
and squint in sun; data has to be above the fold. The beekeeper's own photos remain the only
photographs in the app, so the two are never mistaken for each other.

### Style spec (the contract for every image)
**Look.** Flat, friendly, slightly old-fashioned apiculture print simplified into clean line
art. Not photographic, not 3D, not painterly, not cartoonish.

**Ink.** Black only, in exactly two values: solid black for outlines and small solids, one
mid-grey (about 50 %) for fills such as comb and wings. No gradients, shading, hatching,
texture or halftone. The app turns black into full wax tint and the grey into a lighter wax
tint, so the picture is one colour in two strengths.

**Line.** One uniform, medium weight with rounded ends: about 1.2 % of the canvas width (about
12 px on 1024). Tuned in the pilot until it sits comfortably beside the hexagon stroke at
display size.

**Canvas.** Square, plain pure white background, nothing behind the subject (no ground line,
horizon, frame, border or vignette: the hexagon frame supplies the edge). The subject's whole
bounding box stays inside the **middle 60 %** of the canvas. (The first pilot showed that 80 %
is too wide: a hexagon's corners are cut off, so a wide subject touches or crosses its slanted
sides. 60 % is the largest box whose corners stay inside a pointy-top hexagon of the size
the app uses.)

**Recurring objects** (drawn the same way in every image; this is what makes the set read as
one hand):
- **Bee:** round body with two stripes, small round head, two oval wings seen from the side,
  two short antennae. Never more than four in a picture. No faces, no expressions beyond a
  dot eye.
- **Hive:** a stack of three plain wooden boxes under a gabled lid, with one narrow entrance
  slot at the bottom. Never a straw skep.
- **Frame:** a rectangle with a hexagonal comb pattern in the grey value.
- **Flower:** five rounded petals around a round centre, on a straight stem with two leaves.
- **Comb cell:** a regular hexagon, matching the app's own mark.
- **Stand:** a flat board on four straight legs. **Lantern:** as drawn in the `connect` picture
  (a small cage lantern with a flame and six short rays). **Path:** one curving line pair that
  narrows toward the hive.
- The vocabulary is closed: nothing else appears (no ground line, rocks, grass, clouds, sun).
  Gemini adds such things unprompted, and each is a new drawing style in the set.

**Never:** text, letters, numbers, logos, watermarks, people, hands, faces, smoke effects,
colour, shadows, brand names, the style of any named artist.

**Mood.** Calm, warm, unhurried. The "attention" and "unreachable" pictures are concerned, not
alarming: no red-alert imagery, no sad faces.

### Prompts for Gemini
Gemini is conversational and, unlike DALL·E, **accepts a reference image**, so consistency does
not rest on wording alone. The method is a *model sheet*: one picture of the recurring objects,
chosen once, attached to every later request as the thing to copy. It has no negative-prompt
field, so exclusions are written as plain sentences; it does not rewrite the prompt, so the
prompt you send is the record (keep it with the model name the app shows, since model names
change). Use a **new chat for every picture** with the model sheet attached, so one picture's
drift cannot leak into the next. Ask for the image only, in the text of the prompt.

**Step 1: the model sheet (once).** Ask for it in a fresh chat, ask again for variants until
the objects are right, and keep one. Save it as `Tools/illustrations/source/model-sheet.png`.

```text
Create a model sheet for a set of illustrations, as a single image: a 3 by 2 grid on a plain
pure white background, each item drawn once, large, with plenty of space around it, and no
labels or text of any kind.

Style: a flat, friendly line illustration, like a simplified vintage apiculture print, drawn
as clean vector-style line art. Pure black ink only, plus exactly one mid-grey fill value used
for comb and wings. Uniform medium-weight outlines with rounded ends. No colour, no
gradients, no shading, no hatching, no texture, no shadows.

The six items, left to right and top to bottom:
1. A bee seen from the side: a round body with two stripes, a small round head with a dot eye,
   two oval wings, two short antennae.
2. The same bee seen from the front, its two small eyes drawn as regular hexagons.
3. A hive: a stack of three plain wooden boxes under a gabled lid, with one narrow entrance
   slot at the bottom.
4. A frame: a rectangle filled with a regular hexagonal comb pattern in the grey value.
5. A flower: five rounded petals around a round centre, on a straight stem with two leaves.
6. A single regular hexagon, outlined, with a grey fill.

Return only the image.
```

**Step 2: each picture.** New chat, attach the model sheet, paste the block below with the
three placeholders filled in (the table after it has the fills).

```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: {SUBJECT}
Composition: {COMPOSITION}
Mood: {MOOD}

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; a hive is always the sheet's hive, three boxes under a gabled
lid, never a single box; no more than four bees; no colour, gradients, shading or shadows; no text, letters,
numbers, logos or signature; no people, hands or faces. Return only the image.
```

If a result goes wrong, correct it in the same chat with one instruction ("remove the shadow
under the hive", "make the line weight match the sheet") rather than rewriting the prompt;
start a new chat only when the picture is beyond repair.

Per-case fills (the pilot cases first):

| Case            | `{SUBJECT}`                                                                                                                                       | `{COMPOSITION}`                                                                         | `{MOOD}`                    |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- | --------------------------- |
| `welcome`       | A hive with three bees flying toward its entrance slot and a single flower in front                                                               | Hive centred and slightly above middle, bees in a gentle arc from the left              | Welcoming, bright           |
| `allClear`      | One bee resting on a frame of hexagonal comb, a single flower beside it                                                                           | Frame centred, bee on its upper edge, flower on the right                               | Calm, content               |
| `offline`       | A closed hive with its entrance slot shut and two bees clustered together on the lid, resting                                                     | Hive centred, bees small, large empty margin                                            | Quiet, safe, sheltered      |
| `connect`       | A hive and a small hanging lantern at its entrance, glowing as a circle with short rays                                                           | Hive left of centre, lantern right                                                      | Curious, inviting           |
| `unreachable`   | A hive upper right and one bee lower left facing it, joined by a dashed flight path that stops short of the hive                                  | Hive and bee on a diagonal inside the middle 60 %; one gentle curve of about six dashes | Patient, a little concerned |
| `noApiaries`    | One flower with one bee hovering above it                                                                                                         | Bee above, flower below, on one vertical line                                           | Hopeful, open               |
| `noHives`       | One empty outlined hexagon (an empty comb cell) with one bee hovering above it                                                                    | Bee above, hexagon below, on one vertical line                                          | Hopeful, waiting            |
| `noInspections` | A single empty frame held upright, the hexagonal comb drawn only as outlines, no fill                                                             | Frame centred                                                                           | Blank page, ready           |
| `outboxEmpty`   | One bee landing on the entrance board of a hive, drawn exactly like the sheet's side bee and filled white so no line of the hive shows through it | Hive centred, bee small at the entrance                                                 | Done, satisfied             |
| `cameraNeeded`  | A bee seen from the front with its large eyes drawn as two hexagons                                                                               | Bee centred and large                                                                   | Playful                     |

Ask for several variants per case (the Gemini app offers a redo) and keep at most one.
Things to watch for in Gemini's output: a small visible mark in a corner of images made in
the consumer app (an invisible watermark is added to all of them; the corner mark lies outside
the middle 85 % that the processing crop keeps, so it is removed; check the current terms
before relying on that); an off-white background or faint noise (the levels step absorbs both); and stray
text or a signature (reject, or correct in the chat).

### Processing: from the raw picture to something the app can tint
Gemini returns an opaque raster, normally about 1024 px square (no transparency), so the
white is removed by the script, not the generator:
00. **Composites, where a picture is assembled** from parts of others that already pass (today
   only `noHives`): done by the same script from a manifest entry naming the source pictures and
   the cut-out boxes, so the result can be regenerated, before any of the steps below.
0. Crop to the middle 85 % (drops any corner mark), then scale to 1024 px.
0b. **Normalise size and position.** Crop to the ink's bounding box and scale it to the largest
   size whose four corners stay inside a pointy-top hexagon (the shape it will sit in), with a 10 %
   margin: the box must be no wider than 0.87 of the hexagon's radius, and its half-height plus
   one-sixth-root-three of its half-width no more than the radius. Cap at 1.3 times, so a tiny
   drawing is not blown up, then centre it. Gemini does not place or size a subject reliably (see
   the pilot), and this makes the rule a fact the script guarantees rather than something to
   re-ask for. (The first version fitted a plain 60 % square, which wastes the hexagon's height:
   a tall, narrow picture such as a bee over a flower came out about 20 % smaller than it needed
   to be.)
0c. **Normalise line weight.** Work at twice the resolution (2048 px), measure the median
   outline thickness (the median length of the short black runs, horizontally and vertically),
   and dilate or erode the black one step at a time until it is about 9 px at 1024. At 1024 a
   step is a whole 2 px of line and overshoots (a pilot picture went from 5 to 12 px); at 2048
   it is 1 px, and the ten pictures went from 5.5 to 11 px apart to 8.5 to 10.5. Size matters
   too: a stray long line (a ground line) widens the bounding box and shrinks the real subject,
   which is one more reason to ban them.
1. Levels: map the picture to three values, white, mid-grey and black, by two thresholds
   (tuned once for the set, not per picture, so line weight stays even).
2. Vectorise to two paths per picture: black as one, grey as the other. Candidates:
   `potrace` (needs a 1-bit input, so run twice) or `vtracer`. Neither is installed here;
   install the one chosen and record which in the script's header.
3. Write a PDF (single-scale, vector data preserved) into `App/Assets.xcassets` or
   `HiveLogUI/Resources` as a **template image**, with the grey path at a fixed opacity
   (about 40 %) so it draws as a lighter tint of the same colour.
4. Commit raw PNGs under `Tools/illustrations/source/` (about 1 MB each), not in the app
   bundle, so a better vectoriser can be re-run later.

The app tints with `Theme.wax` (see Decisions) in the image's own colour space, so light and
dark need no second set of pictures.

### In the app
- `Illustration` (enum, one case per row in the table above) and `IllustrationView(_ case:,
  size:)` in `HiveLogUI/Design/`. Sizes: `.hero` (welcome and connect) and `.spot`
  (empty states). Drawn inside the existing `HexagonShape`, on `Theme.surface2`, with a
  hairline hexagon outline, so every picture has the same frame.
- `EmptyState(illustration:)` and the first screens' `IllustrationHero` are the only places that use it. (`Masthead` did not need one: the sign-in and connect screens' frame places the hero under it.)
- `accessibilityHidden(true)`: the title and message beside it carry the meaning.
- Hidden when the vertical size class is compact, or the Dynamic Type size is an
  accessibility size; the message remains.
- Pictures are bundled; nothing is generated or downloaded at runtime (works offline, no
  per-use cost, nothing for App Review to question).

## Pilot findings (round 1)
Three pictures made in Gemini, tinted in wax on the theme's `surface2` in a hexagon, light and
dark, at 420 px and at display size (120 px). The preview is a raster stand-in for the vector
pipeline (ink strength = darkness, so the black outline gets the full wax tint and the grey fill a
lighter one); nothing is vectorised yet.

**The tint approach works.** Black becomes wax outline, grey becomes a pale wax fill, and
three pictures that came out of the generator in plain grey read as one family in both modes.
The grey fill is the right strength.

| Picture | Verdict |
|---|---|
| `allClear` (bee on a comb frame, flower) | **Keep.** Follows the spec: one bee, the comb frame, the flower, a plain background. The flower crowds the frame's corner slightly; tolerable. |
| `offline` (box with two bees on its lid) | **Redo.** The hive is a single box with a flat lid and a blank front, not the sheet's three boxes under a gabled lid, so it would not match any other picture with a hive. Its top right corner also reaches the hexagon's edge. |
| `welcome` (hive, four bees, flower, frame, hexagon) | **Redo.** It is a collage of the model sheet's objects, not a scene: the frame and loose hexagon were not asked for, the hexagon is cut off at the frame's corner, and at 120 px it is a cluster of small marks with no message. It has the right hive, though; the sheet's hive is the one to keep. |

What this changed in the spec and prompt: the subject must stay in the middle 60 % (above); the
scene prompt now says to draw only what the scene names and always to use the sheet's hive.
If a redo still adds objects, correct it in the same chat: "Remove the frame and the loose
hexagon, leave only the hive, the three bees and the flower" for `welcome`, and "Redraw the hive
as in the sheet: three boxes under a gabled lid, with the entrance slot shut" for `offline`.

**Go / no-go: go** on the approach (Gemini with a model sheet, tinted in the app).

### Round 2
The two redos and a fresh `allClear`, regenerated after the corrections above.

| Picture | Verdict |
|---|---|
| `allClear` | **Pass.** Same subject, now a little bigger (64 % x 65 % of the canvas, just over the 60 % rule, but it sits inside the hexagon). |
| `offline` | **Pass.** The sheet's hive, three boxes under a gabled lid, with two bees on the roof. Small (35 % x 52 %), so it is weak at 120 px; the size step above scales it up. |
| `welcome` | **Pass.** A scene now: three bees circling to the hive's entrance on a dashed flight path, a flower below. It sits left of centre (centre at 41 %, 56 %); the centring step fixes that. |

The set now reads as one family: the same bee, the same hive, the same flower, in both modes and
at 120 px. What is left is mechanical, and went into the processing steps: **size** varied from
35 % to 65 % of the canvas, and **line weight** from about 7 to 10 px (the median outline
thickness, 0.7 % to 1.0 % of the width), so the script normalises both (steps 0b and 0c). Nothing
here needs another round of generation.

Not seen yet: a vectorised version (the preview is a raster), and the pictures in the app itself.

### Round 3: the full set
All ten together, run through steps 0b and 0c (a script in the scratch area, not yet the repo's
tool), tinted, light and dark. The family holds: one bee, one hive, one flower, one line weight,
in a single wax colour. The set, in order: `allClear`, `offline`, `welcome`, `connect`,
`unreachable`, `noApiaries`, `noHives`, `noInspections`, `outboxEmpty`, `cameraNeeded`.

| Picture | Verdict |
|---|---|
| `allClear`, `offline`, `welcome` | **Pass** (round 2). |
| `connect` (hive and lantern) | **Pass.** The lantern is in the family's style; it is now the model for the lantern. |
| `noInspections` (empty comb frame, outlines only) | **Pass.** Big but clean. |
| `cameraNeeded` (front bee, hexagon eyes) | **Pass.** The sheet's front bee; heavier than the others, which is acceptable for a single hero mark. |
| `noApiaries` (stand and flower) | **Redo.** It has a ground line (banned), which also shrinks the subject, and the "stand" reads as an empty doorway or easel, not a stand. |
| `noHives` | **Fix in the chat.** The stand has a hive on it; this needs it empty. Otherwise a good picture (bee above, flower). |
| `unreachable` (path, hive, bee) | **Redo.** A different hand: the bee is large with legs gripping a rock and a worried brow, rocks and grass tufts are new objects, there is a stray diagonal line, the line is thin, and the hive is tiny. |
| `outboxEmpty` (bee at the hive) | **Redo.** The bee is drawn in a more realistic style (legs, large eye, a pollen ball) and is transparent, so the hive's lines show through its wing. |

Correction lines to say in the same Gemini chat:
- `noHives`: "Remove the hive from the stand. Leave the stand empty, with the bee above it and the flower beside it."
- `noApiaries`: "Redraw it as a bare stand: a flat board on four straight legs, nothing on it. Remove the ground line. Keep the flower."
- `unreachable`: "Remove the rocks, the grass and the stray diagonal line. Draw the bee small, in exactly the sheet's side-bee style, with no legs and no brow. Make the hive larger, at the end of the single curving path."
- `outboxEmpty`: "Redraw the bee exactly like the sheet's side bee: simple, no legs, no pollen ball. Fill it white so none of the hive's lines show through it. It lands on the entrance board."

### Round 4: the four regenerated
Tinted and normalised with the other six (same script). The family still holds; what is left is
small except for one picture.

| Picture | Verdict |
|---|---|
| `unreachable` | **Redo again.** The bee is now right and the hive is in style, but the path runs off three edges of the picture, so the processing fits the whole canvas into the 60 % box and shrinks the hive to a few pixels; the path line is also much thinner (3.5 px) than the rest and touches the hexagon. Needs a short path that stays inside the picture, and a larger hive. |
| `outboxEmpty` | **Fix in the chat.** The bee is now in the family's style and filled white. The entrance board turned solid black (it is grey in every other hive picture), and the bee is small, so the message is hard to read at display size. |
| `noHives` | **Fix in the chat.** The bee is back to the realistic drawing (legs, a separate neck, a large eye). The stand is a bench in perspective with wood-grain hatching and a cross brace. |
| `noApiaries` | **Acceptable, one small fix.** The stand reads as a small bench, which is true to a hive stand, and the picture is clean; the wood-grain lines break the "no hatching" rule, so remove them. |

Correction lines to say in the same chat:
- `unreachable`: "Redraw it so the whole path stays inside the picture: no line may touch or cross any edge. Make the path short, with one gentle S-bend, starting about a fifth of the way in from the left and bottom edges. Draw the path with the same line weight as the hive and the bee. Make the hive larger, about 40 % of the picture high."
- `outboxEmpty`: "Fill the entrance board mid-grey, like the hive on the sheet, not black. Make the bee about one and a half times larger, still white-filled and still drawn exactly like the sheet's side bee."
- `noHives`: "Redraw the bee exactly like the sheet's side bee: a simple round body, two stripes, two oval wings, a dot eye and two short antennae; no legs, no neck, no extra detail. Draw the stand flat from the front with no perspective, with no wood-grain lines and no cross brace."
- `noApiaries`: "Remove the wood-grain lines and the cross brace from the stand, and draw it flat from the front with no perspective."

The prompts below were updated with the same points, for a fresh chat.

### Round 5: where the set stands
The four were redone again. Gemini fixed some points and kept others, and after two rounds of
corrections on the stand and the bee it stopped changing them, so the decision for those two was
to change what the picture is made of rather than ask a third time.

| Case | Settled as |
|---|---|
| `allClear`, `offline`, `welcome`, `connect`, `noInspections`, `cameraNeeded` | Pass (earlier rounds). |
| `outboxEmpty` | **Pass.** The bee is now in the family's style, larger, white-filled (a pale outline round it hides the hive's lines, like a sticker), and the entrance board is grey. |
| `noApiaries` | *(Replaced in round 6.)* **Use the round 4 picture** (clean stand with a little wood-grain, and a flower). The round 5 regeneration is worse: the whole stand is filled grey. |
| `noHives` | *(Replaced in round 6.)* **Composite.** Neither regeneration gets the bee right (legs, a separate neck), and both fill the stand grey. The picture is the `noApiaries` stand and flower with the sheet-style bee lifted from `allClear` and placed above the stand; in the tinted preview it sits in the family without a seam. This is a documented step in the pipeline (the manifest records the sources), not hand retouching. |
| `unreachable` | **New concept.** Gemini again let the path run off two edges, which shrinks the real subject, and drew the hive with two boxes instead of three. A path is too hard to keep inside a frame. The new picture uses what already worked, the dashed flight path from `welcome`: a hive, a bee facing it, and a dashed line that stops short of the hive. |

Lessons for the rest of the project, so they are not relearned:
- After two failed corrections, change the concept or composite from pictures that already pass;
  do not iterate a third time.
- Anything that has to stay inside the frame (a path, a ground, a landscape) will not. Use
  objects, not scenery.
- Keep to the sheet's objects. The two failures that dragged on were the ones that needed a
  new object (a stand, a path); each one's drawing style drifted from the family.

### Round 6: noApiaries and noHives again
Not satisfied with the stand pictures (a bench in perspective with grain, a grey fill, and a bee that
would not stay in style), so the pair is redone on a different idea, following the lessons above: use
only objects from the sheet, nothing that needs a new drawing.

- **`noApiaries`**: one flower with one bee hovering above it. An open meadow, nothing set up yet.
- **`noHives`**: one empty outlined hexagon, an empty comb cell, with one bee hovering above it. The
  apiary exists, the hive does not; the hexagon is also the app's own mark.

They are a deliberate pair: the same layout (bee above, one object below, one vertical line), so the
two empty states read as siblings and differ by the object alone. Both are small and simple, which
suits an empty state: the text carries the message.

A mock-up was made from pictures that already pass (the bee from `allClear`, a flower from
`welcome`, a hexagon drawn at the family's line weight) and put through the tinting script: it sits
in the set with no seam, in light and dark. So there is a **fallback that needs no more Gemini
rounds**: build both as composites, as `noHives` was in round 5. Try Gemini first with the prompts
below; if either picture needs a third correction, take the composite.

### Round 7: the three new concepts
All three came back usable, and on the second attempt at the concept rather than the picture, which
bears out the round 5 lesson. Tinted and fitted with the new hexagon fit (step 0b).

| Case | Verdict |
|---|---|
| `unreachable` | **Pass.** A hive with three boxes, a plain side-view bee facing it, and a dashed flight path that stops just short of the hive: the message reads at once, and nothing strays off the edge. The dashes are a little thinner than the outlines; the line-weight step evens them. |
| `noApiaries` | **Pass.** Gemini used the sheet's front-view bee (hexagon eyes) with the flower. Clean, one line weight, sits in the family. |
| `noHives` | **Pass with one change.** The same front bee over an empty hexagon, drawn as a double outline like the app's mark. But the hexagon is flat-topped (points left and right), while the app's hexagon, and the frame it sits in, point up and down, so the two orientations clash. Ask once: "Rotate the hexagon 30 degrees so a point faces up, keep the double outline." If that does not work, use a composite: this picture's bee with a hexagon drawn by the script at the app's geometry (a single outline looks plainer than the double one). |

The front bee now appears in three pictures (`cameraNeeded`, `noApiaries`, `noHives`). That is fine:
it is the sheet's second bee, and it gives the pair and the camera screen a "looking at you" feel,
against the side bee that is going about its business elsewhere in the set.

With the hexagon fit the tall, narrow pair and `offline` are about 15 to 20 % larger, and the wide
ones slightly smaller; every picture's corners now clear the hexagon. The full set, tinted, is
consistent in light and dark.

### Round 8: noHives, composed
Gemini could not be made to turn the hexagon from flat-topped to point-up. Several attempts, asked in
several ways, changed nothing (the same outcome as the stand in rounds 4 and 5), so the round 5 rule
applied: stop asking, and build it. A hexagon is a shape the script can draw exactly, which a generator
cannot be relied on to do.

- `noHives` is now **composed** by `process.py` from its manifest entry: the bee is kept from Gemini's
  picture (the box that holds only the bee), and the hexagon is drawn by the script, point-up like the
  app's own, as a double outline (a second outline 38 px inside the first, at the same line weight),
  which echoes the app's mark and is what Gemini's own version looked like. It then goes through the same
  fit, line-weight and tracing steps as the others, so nothing about it is special once composed.
- The point-up hexagon now sits in the point-up tile with matching orientation, which was the whole
  complaint.
- The manifest's `compose` entry (`keep` box, hexagon centre, radius, gap and line) means the picture is
  regenerable and nothing was retouched by hand. If the bee is ever regenerated, replace
  `source/noHives.jpg` and check the `keep` box still holds only the bee.
- Lesson, added to the earlier two: **geometry is for the script, not the generator.** A generator draws
  living things and objects well and regular shapes and orientations badly; if a picture needs an exact
  shape, draw the shape.

### Prompts to recreate
Each of these is the complete step 2 prompt for one picture, ready to paste: a new chat, the model
sheet attached, then the block. **After round 8 nothing needs recreating**; the prompts here stay as the record
of what was asked. The correction lines above are for when the original chat is still open and one
change is enough.

**`noApiaries`** (new concept, see Round 6)

```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: One flower, drawn exactly as on the sheet (five rounded petals around a round centre, on a straight stem with two leaves), standing upright. One bee, drawn exactly like the sheet's side bee (simple round body, two stripes, two oval wings, dot eye, two short antennae; no legs, no neck, no extra detail), hovering above the flower. Nothing else.
Composition: The bee above and the flower below, both centred on one vertical line with a clear gap between them. The flower is about 40 % of the picture high; the bee about a fifth of the picture wide.
Mood: Hopeful, open.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; only one flower and one bee; there is no hive, no stand, no ground and no grass; no more than four bees; no colour, gradients, shading
or shadows; no text, letters, numbers, logos or signature; no people, hands or faces. Return
only the image.
```

**`noHives`** (new concept, see Round 6)

```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: One regular hexagon with a point at the top, drawn as an outline only, with the sheet's line weight and nothing inside it (white inside, no grey): an empty comb cell. One bee, drawn exactly like the sheet's side bee (simple round body, two stripes, two oval wings, dot eye, two short antennae; no legs, no neck, no extra detail), hovering just above it. Nothing else.
Composition: The bee above and the hexagon below, both centred on one vertical line with a clear gap between them. The hexagon is about 40 % of the picture high; the bee about a fifth of the picture wide.
Mood: Hopeful, waiting.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; only one hexagon and one bee; there is no hive, no stand, no frame, no flower, no ground; no more than four bees; no colour, gradients, shading
or shadows; no text, letters, numbers, logos or signature; no people, hands or faces. Return
only the image.
```

**`unreachable`** (new concept, see Round 5)

```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: A hive in the upper right, the sheet's hive (three boxes under a gabled lid). One bee in the lower left, drawn exactly like the sheet's side bee (simple round body, two stripes, two oval wings, dot eye, two short antennae, no legs), facing the hive. Between them, a dashed curved line, the bee's flight path: it starts at the bee and stops short of the hive, leaving a clear gap before it reaches the hive. Nothing else.
Composition: The hive and the bee on a diagonal, both inside the middle 60 % of the image. The dashed line is one gentle curve of about six dashes, drawn with the same line weight as everything else. The hive is about 35 % of the picture high; the bee about a fifth of the picture wide.
Mood: Patient, a little concerned, with no sad face on the bee.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; a single hive and a single bee only; no solid path, road or
ground; no more than four bees; no colour, gradients, shading or shadows; no text, letters,
numbers, logos or signature; no people, hands or faces. Return only the image.
```

**`outboxEmpty`**

```text
Attached is a model sheet. Draw a new illustration in exactly the same style as the sheet,
using the same line weight, the same two ink values (black and one mid-grey) and drawing the
bee, hive, frame, flower and hexagon exactly as they are drawn there. Do not copy the sheet's
layout; compose a single new scene.

Scene: The sheet's hive, with the entrance board at its bottom. One bee, drawn exactly like the sheet's side bee (simple round body, two stripes, two oval wings, dot eye, two short antennae; no legs, no pollen, no extra detail), is landing on the entrance board in front of the entrance slot, about a fifth of the hive's width. The bee is filled solid white inside its outline, so that no line of the hive shows through its body or wings. The entrance board is mid-grey, as on the sheet's hive, not black.
Composition: The hive centred; the bee small, at the entrance, slightly overlapping the hive's lower front.
Mood: Done, satisfied.

Requirements: square image; plain pure white background with nothing behind the subject (no
ground line, horizon, frame, border or vignette); the whole subject centred and kept within the
middle 60 % of the image, with a wide empty margin all round; draw only what the scene names
and nothing else from the sheet; a single hive and a single bee only; no more than four bees; no colour, gradients, shading
or shadows; no text, letters, numbers, logos or signature; no people, hands or faces. Return
only the image.
```

## What was built (task 0211, app repo)
Branch `feature/0211-illustrations` in `~/Development/vinculum`, from `main` at the merge of 0210. All ten
pictures are final (`noHives` is composed, see Round 8).

- **`Tools/illustrations/`**: `process.py` (Python and Pillow, plus `potrace`, installed with
  `brew install potrace`), `manifest.json` (each picture's source file and the round it came from) and
  `source/` (the ten generated pictures, about 1.5 MB). Run `python3 Tools/illustrations/process.py`; it
  does steps 0b and 0c above, then traces two layers per picture: the black as the "ink" and the grey as
  the "fill", grown a little so it tucks under the ink with no gap (the grey layer is traced from the grey
  band of the image, not from everything darker than white, which would put a faint halo round every
  line). The ten came out at 15 to 57 KB each, 370 KB in all, with the line weight within 8 to 9.5 px (from
  5.5 to 12).
- **Vector data in JSON, not an asset catalog.** The package's resources are plain files
  (`Resources/Illustrations/<case>.json`, loaded with `Bundle.module` like the fonts), the paths are
  `potrace`'s own (integers, relative, 10 per pixel), and a small reader (`PotracePath`, about 60 lines,
  tested) turns them into SwiftUI paths. Reason: HiveLogUI is a Swift package that must build and test
  with `swift test` on a Mac with no Xcode project, and the asset-catalog route (PDF or SVG, rendered as a
  template image) needs Xcode's compiler and gives no control of the two strengths. A shape filled in
  `Theme.wax` also follows light and dark mode with no second set.
- **`Illustration`** (ten cases), **`IllustrationView`** (the hexagon tile, `Theme.surface2` with a hairline,
  the two layers in wax at 40 % and 100 %, `accessibilityHidden`), **`IllustrationMark`** (a picture or,
  with no room, the old hexagon badge), **`IllustrationHero`** (a picture or nothing). "Room" is: not an
  accessibility text size and not a compact-height phone (on its side); tested as a pure function.
- **`Theme.wax`**: light `0xA97C1A`, dark `0xD4A545`, a decorative token the app owns (like
  `criticalText` and `warningText`), not yet in `beeswax`. Tested at 3:1 or more on the page and the tile
  in both modes, and not equal to the warning amber.
- **Where each is used:** connect screen (`connect`) and sign-in screen (`welcome`), by the `Welcome`
  frame; apiaries (`noApiaries`) and an apiary's hives (`noHives`); alerts (`allClear`); the outbox
  (`outboxEmpty`, and `offline` above the waiting list when the last stop was "offline"); the scanner with
  no camera (`cameraNeeded`); a failed load whose message is the "couldn't reach the server" one
  (`unreachable`, in `ErrorView`, by comparing with `Messages.cannotReachServer`); and the hive page's
  empty "latest inspection" card (`noInspections`, the small `.spot` size).
- **One change to the rule "never on the hive page":** the empty latest-inspection card on a hive page
  carries a picture, because that is the one place on a fresh hive where a section has nothing to show.
  Anything with data in it still has none.
- **Tests (`IllustrationTests`, 9):** the path reader (absolute and relative commands, numbers run
  together, an unknown command stops it), every picture in the bundle with a fill layer under an ink layer,
  the size budget (each at most 80 KB, all under 600 KB; the plan said 60 KB, but the comb frame, the
  most detailed picture, is 57), every picture's ink inside the hexagon, each picture centred, the wax
  contrast, and the room rule. Mac: 209 tests pass. iOS simulator: 205 pass (the macOS-only render
  tests do not run there).
- **Opt-in render tests (`AssetRenderTests`, `VINCULUM_RENDER_ASSETS=1`):** a contact sheet of the ten drawn
  by the app's own view, and a light and dark sheet of the empty-state screens (the connect and sign-in
  screens come out blank there, because `ImageRenderer` does not draw a `ScrollView`).

### Verification, and what is not verified
- The ten, drawn by the app's own view from the vector data, look like the tinted previews: crisp, no
  seams or stray marks (looked at as a contact sheet).
- On an iPhone 17e simulator (a fresh install, so it shows the connect screen): the connect screen with
  the hive and lantern fits above the fold with the address field and the Connect button, in light and in
  dark; at the "accessibility large" text size the picture is gone and the words take the room.
- The empty states (no apiaries, no hives, all clear, outbox empty, no camera, unreachable) were seen in
  the Mac render in light and dark, not yet on a phone with real empty data.
- **Not seen yet:** the sign-in screen's picture, the "no inspections" card and the offline outbox
  (each needs a state the dev site is not in), landscape, and everything on a physical phone.

### Seen on a phone (2026-10-06)
The owner ran the app on a physical iPhone and found the illustrations good, and would like
**more of them**. Screens that still show only the small hexagon badge, and so could carry one,
are noted for a follow-up: the map with no positions, a failed load that is not "cannot reach
the server", a scanner label the app does not recognise, a record in the outbox that needs
attention, and a hive with no queen yet. The lessons above apply (objects from the sheet, at
most two corrections, composite from pictures that pass).

### Follow-ups
- `--bw-wax` in `beeswax`, if the website is to share the pictures (the module stays palette-free,
  ADR-0060).
- A real-phone look; the licence question for AI-generated images, before 0209 settles whether the
  repository is open.

## Decisions
- **A wax colour needs to exist.** The theme's accent is pine green and its amber is the
  *warning* colour, which should not also mean "decorative". Proposal: add a decorative
  token `wax`, a honey hue that is yellower than the warning amber: light `0xA97C1A`
  (about 3.5:1 on the paper ground, enough for a non-text graphic) and dark `0xD4A545`
  (about 9:1 on the dark ground). Test it with the existing contrast helper before
  settling. The tint belongs in `beeswax` first (`--bw-wax`), then the app, per the rule that the theme is the
  source of truth; **as built it is in the app only** (like the two adjusted text colours), and
  `beeswax` is a follow-up. Pale fills use `surface2`.
- **Full colour instead of one tint was considered and rejected.** The generator's colours drift
  from picture to picture and would not follow dark mode or a later palette change. The cost
  of the tint is a flatter, more graphic look.
- **Vector, not PNG.** Sharp at any size, a few KB each, tints cleanly. The cost is that
  fine detail does not survive tracing, which the flat style avoids asking for.

## Risks
- Gemini may ignore "no text" or "exactly two values", or soften the line into a brush stroke.
  The model sheet is the main defence; the pilot answers whether the prompt holds. The fallback
  is correcting in the chat, choosing another variant, or cleaning a picture by hand.
- Gemini stops responding to corrections after about two rounds on the same point (see
  round 5). The remedy is a new concept or a composite, not more prompting.
- Bee anatomy and hexagon regularity drift across a set. The recurring-object sentences and
  the contact-sheet review are the guard; reject on drift rather than accept near-misses.
- If the pilot does not hold together, the app loses nothing: the empty states already work
  with the hexagon marks from 0210.
- **Licence and provenance.** Read Google's current terms for generated images, including
  commercial use and whether the account type (consumer app, AI Studio, Workspace) changes
  anything. Copyright in AI-generated images is unsettled in several countries and may not be
  registrable, and the images carry an invisible SynthID mark, which should stay. Keep the
  manifest and record the position in the README. This also feeds the open-source question in
  [[0209-ios-app-demo-server-and-app-store-release]]: pictures that may not be copyrightable
  are awkward to put under a licence.

## Verification (planned)
- A contact-sheet render (as `AssetRenderTests`, opt-in) of all ten, light and dark, side by
  side: the coherence test is a human looking at them together.
- `DesignTests`: every `Illustration` case has an asset; each file is under about 60 KB and the
  set under 600 KB; `Theme.wax` meets 3:1 on both grounds; the template-image mode is set.
- Each screen with a picture seen on the simulator in light and dark, at the default and
  largest text sizes, and in landscape.

## Not in this task
The website. The beeswax theme could use the same drawings for the dashboard's "all clear"
and empty states, which would keep app and site together; the module is palette-free (ADR-0060),
so that belongs in `beeswax`, as a follow-up once the set exists.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
