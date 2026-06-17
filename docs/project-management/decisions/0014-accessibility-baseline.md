---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0014: Accessibility baseline (WCAG 2.1 AA)

## Status
accepted

## Context
There is no documented accessibility target. Concrete gaps exist: grouped
buttons are very small (`!px-1 !py-0`), button colours are hard-coded hexes with
unverified contrast, and image `alt` is configured but not required
(`alt_field_required: FALSE` on `Hive.images`). The weight-histogram SVG is a
good example to follow — it already sets `role="img"`, `aria-label`, and
`<title>`.

## Decision (recommended)
Target **WCAG 2.1 AA** for module UI: text/UI colour contrast ≥ 4.5:1 (≥ 3:1 for
large text/UI components); interactive tap targets ≥ 24×24px (aim 44×44px);
full keyboard operability with visible focus; meaningful `alt`/labels; and
ARIA labelling for non-text widgets (as the histogram already does). Revisit
whether `alt` should be required on hive images.

## Consequences
- Positive: inclusive, legally safer UI; better mobile usability.
- Negative / trade-offs: audit + remediation effort; some colours/sizes may need
  to change.
- Follow-up tasks: [[0009-mobile-qa-and-tap-targets]] (tap targets),
  [[0014-implement-breadcrumb-consistency-fixes]] is unrelated; the button
  contrast/sizing work is governed jointly with
  [[0012-action-button-design-system]].
