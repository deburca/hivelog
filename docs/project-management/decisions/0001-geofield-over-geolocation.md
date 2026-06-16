---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-16
supersedes:
---
# ADR-0001: Store apiary coordinates in a single geofield column

## Status
accepted

## Context
The `Apiary` entity needs to store a location for map display. Early schemas
used separate latitude/longitude columns backed by the `geolocation` module.
That spread one logical value across multiple columns and coupled storage to a
module whose widget we weren't really using.

## Decision
Store the apiary location as a single `geofield` `geolocation` column holding a
WKT `POINT`, rendered/edited through the `leaflet_widget_default` widget. The
migration from the old shapes is handled by `hivelog_update_10001` and
`hivelog_update_10002`, which read the legacy columns, install the new geofield
storage, and re-save entities so geofield's derived lat/lon/geohash columns are
recomputed.

## Consequences
- Positive: one canonical column; geofield computes derived values; map widget
  is satisfied without geolocation.
- Negative: any future change to this field still needs a paired update hook
  (all field storage is code-defined — see `AGENTS.md`).
- Follow-up tasks: [[0003-apiary-map-marker-clustering]] builds on this data.
