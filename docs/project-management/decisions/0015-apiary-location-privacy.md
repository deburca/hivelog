---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0015: Apiary location privacy

## Status
accepted

## Context
`Apiary` stores both a free-text `location` (`string_long`) and exact GPS in a
`geofield` `geolocation` column. Apiary `visibility` defaults to `private`, but
**public** apiaries are viewable by any user holding the relevant "own"
permission. Exact apiary coordinates are theft-sensitive: publishing them can
lead directly to hive theft or vandalism.

## Decision (recommended)
Treat precise location as sensitive even on public apiaries. For non-members
viewing a public apiary, expose only an **approximate** location (e.g. rounded
coordinates / a coarse map, or region text) and withhold the exact marker;
full precision is reserved for apiary members (enforced via
[[0021-field-level-access]]). Add `noindex` to apiary canonical pages and never
expose coordinates through unauthenticated endpoints.

## Consequences
- Positive: protects beekeepers' hives from targeted theft while still allowing
  public discovery.
- Negative / trade-offs: public maps are less precise; fuzzing logic adds
  complexity.
- Follow-up tasks: enforced by [[0021-field-level-access]]; affects map
  rendering in [[0008-responsive-map-and-image-grid]]; related to image-borne
  location leaks in [[0016-uploaded-image-security]].
