---
type: task
tags: [hivelog/task]
status: todo
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

## Acceptance criteria
- [ ] The apiary map has a sensible responsive height at `<=480px` (e.g. a
      viewport-relative or fixed mobile height) and is pan/zoomable by touch
      without trapping page scroll.
- [ ] `.hivelog-photos-grid` reflows to fewer columns on narrow screens
      (e.g. 2 columns phone, more on desktop) with no overflow.
- [ ] Thumbnails keep aspect ratio and remain tappable (links open full image).
- [ ] Desktop (`>768px`) layout unchanged.

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
- Decisions:: [[0001-geofield-over-geolocation]], [[0002-no-geocoder-dependency]]
- Commits:: 
