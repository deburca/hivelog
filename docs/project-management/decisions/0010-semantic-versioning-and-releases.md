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
