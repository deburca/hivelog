---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0016: Uploaded image security

## Status
accepted

## Context
`Hive.images` is an `image` field with `file_directory: hivelog/hive` and
`file_extensions: png gif jpg jpeg webp`. No `uri_scheme` is set, so files land
in the **public** filesystem and are served by direct URL **regardless of entity
access** — a private hive's photos are effectively public if the URL is known.
Phone photos also commonly embed EXIF GPS, which can reveal the apiary location
(cf. [[0015-apiary-location-privacy]]).

## Decision (recommended)
Store hive images in the **private** filesystem (`uri_scheme: private`) with
delivery gated by the hive's access (mirroring [[0019-authorisation-model]]), and
**strip EXIF/GPS metadata** on upload (or when generating derivatives). Keep the
extension allowlist; consider max dimensions/file size. Because this changes
field storage settings, ship it with an update hook per
[[0003-code-defined-entity-schema]] and a migration plan for existing files.

## Consequences
- Positive: prevents data/location leakage via direct file URLs and photo
  metadata.
- Negative / trade-offs: private files need a delivery path and have caching/CDN
  implications; migrating existing public files needs care.
- Follow-up tasks: relates to [[0015-apiary-location-privacy]],
  [[0019-authorisation-model]], [[0020-access-parity-custom-routes]]; needs a
  schema/settings update hook.
