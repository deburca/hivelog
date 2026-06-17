---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0003: Define entity schema in code, paired with update hooks

## Status
accepted (ratifies existing practice)

## Context
All five Hivelog entities are `ContentEntityBase` subclasses declared with the
PHP 8 `#[ContentEntityType]` attribute, and every field is defined in
`baseFieldDefinitions()`. There is no exported config for field storage, form
display, or view display. Drupal 11's `drush entity:updates` no longer performs
destructive schema changes, so schema drift must be handled explicitly.

## Decision
Keep field storage defined in code. Any change to `baseFieldDefinitions()` (new,
changed, or removed field) is paired with a `hivelog_update_NNNN` hook in
`hivelog.install` that uses `\Drupal::entityDefinitionUpdateManager()`: read
existing column data, (un)install storage, then re-save entities so derived
columns (e.g. geofield lat/lon/geohash) recompute. `hivelog_update_10001`–
`10003` are the canonical examples.

## Consequences
- Positive: schema is versioned with code; no reliance on importing config;
  reproducible installs.
- Negative / trade-offs: every field change needs a hand-written update hook;
  contributors must remember this.
- Follow-up tasks: applies to any schema-touching task, notably
  [[0016-uploaded-image-security]] (image field settings) and field-access work
  in [[0021-field-level-access]].
