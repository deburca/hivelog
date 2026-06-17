---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0007: Adopt Drupal coding standards & static analysis

## Status
accepted

## Context
The code already follows Drupal conventions (`declare(strict_types=1)`, typed
properties, full docblocks, `phpcs:ignore` annotations in places), but there is
no committed `phpcs.xml`, PHPStan config, or CI gate enforcing it. Standards are
therefore convention-by-habit and can regress silently.

## Decision (recommended)
Adopt `Drupal` + `DrupalPractice` coding standards via
`drupal/coder` (phpcs), and PHPStan with `mglaman/phpstan-drupal` +
`phpstan/phpstan-deprecation-rules` at an agreed level (start where the codebase
currently passes, then ratchet up). Expose `composer` scripts (`lint`, `stan`)
and run them in CI alongside the test suite. Either fix existing violations or
commit a baseline.

## Consequences
- Positive: consistent style, early detection of deprecations/bugs, lower review
  friction.
- Negative / trade-offs: upfront cleanup or baseline; CI configuration to set up
  and maintain.
- Follow-up tasks: pairs with [[0008-testing-strategy]] in the same CI pipeline.
