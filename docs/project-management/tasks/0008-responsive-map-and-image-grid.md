---
type: task
tags: [hivelog/task]
status: in-progress
priority: medium
project: "[[mobile-ux-improvements]]"
area: theme
created: 2026-06-17
branch: feature/0008-responsive-map-and-image-grid
release: v1.4.0
depends-on: ["[[0004-responsive-foundation-and-breakpoints]]"]
---
# Task: Responsive apiary map & hive image grid

## Context
Two media-heavy elements need mobile attention:
1. **Apiary map** — the apiary canonical page embeds a Leaflet map
   (`leaflet_widget_default`, per `AGENTS.md`) over a `geofield` point. Leaflet
   needs an explicit, responsive height or it collapses / overflows on phones.
2. **Hive image grid** — `HiveController::buildImagesGrid()` emits a
   `.hivelog-photos-grid` of thumbnail links, styled by `css/hivelog.images.css`.
   The grid column count should adapt to width.
Part of [[mobile-ux-improvements]].

## Resolution
Mostly CSS, scoped per [[0011-responsive-design-strategy]]; desktop unchanged.
- **Image grid** (`css/hivelog.images.css`): already fluid
  (`auto-fill, minmax(160px, 1fr)`), but very narrow phones (~320px) collapsed
  to one column. Added `@media (max-width: 480px)` forcing
  `grid-template-columns: repeat(2, 1fr)` for a tidy 2-up grid; thumbnails keep
  `aspect-ratio: 1 / 1` and stay tap-through links.
- **Apiary map**: a new `hivelog/map` library (`css/hivelog.map.css`), attached
  on the apiary view by `ApiaryController`, gives the Leaflet map a taller,
  viewport-relative height on small screens. At `≤768px` it overrides the
  formatter's inline 200px with `height: 40vh !important` (floored at 200px via
  `min-height`), matching the map container by id prefix (`[id^="leaflet-map"]`,
  from `Html::getUniqueId('leaflet_map')`) plus `.leaflet-container` as a
  runtime fallback — the field wrapper / `leaflet-container` classes are not
  reliably in the markup. Desktop keeps 200px. The `!important` beats the
  formatter's inline style; the override stays safe because the library loads
  only on the apiary view (single map).
  Touch pan/zoom is Leaflet's default; marker clustering remains
  [[0003-apiary-map-marker-clustering]].

## Acceptance criteria
- [x] Apiary map has a sensible responsive mobile height — `40vh` (min 200px) at
      `≤768px` via the scoped `hivelog/map` override; full-width, no overflow.
      (Touch pan/zoom is Leaflet's default.)
- [x] `.hivelog-photos-grid` reflows: 2 columns at `≤480px`, auto-fill above;
      no overflow.
- [x] Thumbnails keep aspect ratio (`1 / 1`) and remain tappable.
- [ ] Desktop (`>768px`) layout unchanged — by construction (gated `@media`);
      **manual visual check pending** (dev release).

## Implementation notes
- Image grid CSS is in `css/hivelog.images.css`; the markup is an inline
  template in `HiveController::buildImagesGrid()` (class `hivelog-photos-grid` /
  `hivelog-photos-grid__item`). A CSS grid with
  `grid-template-columns: repeat(auto-fill, minmax(...))` reflows for free.
- The map is rendered by the contrib `leaflet` module's field formatter, so
  prefer wrapping/container CSS over patching contrib. Respect
  [[0002-no-geocoder-dependency]] — do not add geocoder.

## Dependencies
- Depends on: [[0004-responsive-foundation-and-breakpoints]].
- Related work: [[0003-apiary-map-marker-clustering]] (same map; coordinate so
  clustering and responsive sizing don't conflict).

## Related
- Project:: [[mobile-ux-improvements]]
- Decisions:: [[0011-responsive-design-strategy]], [[0001-geofield-over-geolocation]], [[0002-no-geocoder-dependency]]
- Commits:: 
