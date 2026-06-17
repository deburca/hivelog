---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-06-17
supersedes:
---
# ADR-0006: Minimal contrib dependency policy

## Status
proposed (pending approval)

## Context
Required contrib dependencies are intentionally limited to `geofield` and
`leaflet` (`hivelog.info.yml`). [[0002-no-geocoder-dependency]] already bans
geocoder. Release 1.3.0 bundled geofield Drupal 11 patches via
`cweagans/composer-patches` rather than forking or adding a dependency. This ADR
generalises that restraint into a standing policy.

## Decision (recommended)
Keep the dependency surface minimal. Adding a new contrib dependency requires:
(1) a concrete, in-module use; (2) a short ADR recording why core/CSS/a small
patch is insufficient; (3) confirmation it supports the current Drupal/PHP
baseline. Prefer core APIs and module-owned CSS over pulling frameworks; prefer
small, documented patches (composer-patches) over forks.

## Consequences
- Positive: smaller attack surface, fewer upgrade-blocking deps, simpler installs.
- Negative / trade-offs: occasional reinvention of functionality a contrib
  module already provides.
- Follow-up tasks: generalises [[0002-no-geocoder-dependency]]; guards
  [[0001-queen-observation-csv-export]] (use core `StreamedResponse`, no CSV lib)
  and [[0003-apiary-map-marker-clustering]] (front-end clustering, no new dep).
