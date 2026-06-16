---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-16
supersedes:
---
# ADR-0002: Do not depend on the geocoder module

## Status
accepted

## Context
It's tempting to reach for the `geocoder` module whenever addresses or maps are
involved. Hivelog's apiary map, however, uses the `leaflet_widget_default`
widget for direct point entry — users drop a pin rather than geocode a string.
Nothing else in the module talks to geocoder services.

## Decision
Keep the required contrib dependencies limited to `geofield` and `leaflet`
(see `hivelog.info.yml`). Do **not** add `geocoder`. Any future feature that
seems to want it must first document a concrete, in-module use here before the
dependency is reintroduced.

## Consequences
- Positive: smaller dependency surface, fewer moving parts, simpler installs.
- Negative: address→coordinate geocoding is unavailable out of the box; pin
  entry only.
- Guards: [[0001-queen-observation-csv-export]] (use core `StreamedResponse`,
  not a new lib) and [[0003-apiary-map-marker-clustering]] (clustering must stay
  front-end, not pull geocoder) both reference this ADR.
