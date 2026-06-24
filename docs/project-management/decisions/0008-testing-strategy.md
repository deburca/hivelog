---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0008: Testing strategy & coverage expectations

## Status
accepted

## Context
Today the suite is kernel + unit tests tagged `@group hivelog` (entity CRUD,
field-option validation, parent/child relationships, queen-colour auto-calc,
inspection logging; plus a mocked breadcrumb unit test). There are no
functional/JavaScript tests, so visual/responsive and JS behaviour is untested.
The run command is documented in `AGENTS.md`.

## Decision (recommended)
Keep the kernel + unit baseline and require tests for new non-trivial logic —
access control (`ApiaryAccessTrait`), entity `preSave()` invariants (e.g. one
active queen per hive), and breadcrumb building. Add `FunctionalJavascript` /
Nightwatch coverage only where behaviour is genuinely JS/visual. All tests carry
`@group hivelog`; the full suite must pass before tagging a release
(see [[0010-semantic-versioning-and-releases]]).

## Consequences
- Positive: regressions caught; behaviour documented by tests.
- Negative / trade-offs: test authoring/maintenance cost; functional tests are
  slower.
- Follow-up tasks: [[0015-breadcrumb-test-coverage]]; mobile verification is
  manual in [[0009-mobile-qa-and-tap-targets]] (this ADR explains why it is not
  automated).
