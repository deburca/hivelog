---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0010: Semantic versioning & release process

## Status
accepted

## Context
The repository already carries version tags (`1.1.0`, `1.2.0`, `1.3.0`) and the
vault has a `releases/` area with per-version notes (`releases/1.1.0.md`,
`releases/1.2.0.md`, …). There is no written rule for what bumps which version
component or what a release must satisfy.

## Decision (recommended)
Follow Semantic Versioning. A change that alters entity schema or the permission
model is a **major** bump (or ships with a documented update path via
[[0003-code-defined-entity-schema]]); backward-compatible features are **minor**;
fixes are **patch**. Each release has a `releases/X.Y.Z.md` note + checklist,
uses the repository's real tag format (`1.4.0`, not `v1.4.0`), is cut only when
the `--group hivelog` suite is green and `drush updb` is clean, and bumps
`hivelog.info.yml`.

## Consequences
- Positive: predictable upgrades; clear changelog; safe schema transitions.
- Negative / trade-offs: release discipline and bookkeeping overhead.
- Follow-up tasks: drives the `releases/` notes; gated by
  [[0008-testing-strategy]] (green suite) and [[0003-code-defined-entity-schema]]
  (update hooks).

## Amendment — 2026-06-25
The "green suite before tagging" gate is now automated: the GitHub Actions CI
pipeline (see [[0023-github-actions-ci-pipeline]]) runs on every push and on
`release` publication events, so a failed suite is visible before the tag is
promoted. The manual checklist in `releases/X.Y.Z.md` retains its "suite green"
item; CI provides the automated evidence for it.

## Amendment — 2026-09-25
[[0098-nanoprobe-collective-locutus-submodule-split]]'s four submodules
(`assimilate`, `collective`, `nanoprobe`, `nexus`) each carry their own
`version:` in their own `.info.yml`, but were left at `1.0.0` since their
creation — never bumped by a release, unlike core's `hivelog.info.yml`.
Since none of the four ships a separate release cadence of its own (they
live in this same repository and are always distributed at the exact
commit/tag core is), a submodule having its own independent version number
would be fiction: there is no "nexus 1.0.0" release distinct from a
"hivelog 1.8.x" release it shipped inside. Decided (task 0139) that every
submodule's `version:` **tracks core's own `hivelog.info.yml` version
exactly**, bumped in the same commit as every future release — not dropped
from the info files entirely, since Drupal's own admin "Extend"/"Reports
available updates" pages display each module's `version:` and a
permanently-frozen `1.0.0` next to a real 1.8.x number would misleadingly
suggest the submodule hasn't changed in over a year. The release checklist
in `releases/X.Y.Z.md` gains one line: bump all five `.info.yml` files
(core + four submodules), not just `hivelog.info.yml`.
