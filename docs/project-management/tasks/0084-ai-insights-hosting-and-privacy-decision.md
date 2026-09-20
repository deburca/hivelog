---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
branch: feature/0084-ai-insights-hosting-and-privacy-decision
release:
depends-on:
blocked-by:
---
# Task: Model/hosting choice + privacy-handling decision for AI insights

## Context
The single most gating open question in
[[0083-ai-assisted-apiary-insights]] — its own Consequences section says
no implementation task should be opened until this is answered. Not a
coding task: the deliverable is a written decision (a short ADR
amendment or a new numbered ADR, whichever fits once drafted), not code.

## Acceptance criteria
- [ ] Compare at least: (a) a self-hosted open model (no hive/apiary data
      ever leaves hivelog's own infrastructure; real ops burden; likely
      lower reasoning quality), and (b) a hosted third-party API (better
      reasoning quality; real per-call cost; the privacy question below
      becomes load-bearing). Document the trade-off explicitly rather
      than picking one without recorded reasoning.
- [ ] If a hosted API is the conclusion: enumerate exactly what data an
      insight's request payload would need to contain (weight/temperature
      trend, inspection notes, calendar status, queen status, ...), and
      for each field, decide send-raw / generalise-or-strip / never-send.
      Apiary GPS/location is the clearest case that must not travel raw,
      per [[0015-apiary-location-privacy]]'s existing precedent for photo
      EXIF data — this task extends that same reasoning to a new kind of
      outbound data flow, HiveLog's first.
- [ ] Decide the consent model: does a beekeeper opt in per apiary, per
      hive, or is it a single account-level setting? Today hivelog has no
      data-sharing consent mechanism at all — this task introduces the
      first one, so it needs its own explicit design, not an assumption
      that turning the feature on implies consent.
- [ ] Estimate real cost at a plausible cadence (daily, per hive, per
      [[0083-ai-assisted-apiary-insights]] §7) against at least one
      concrete hosted-API pricing model, so the trade-off in the first
      bullet is grounded in a real number, not a guess.
- [ ] Record the decision as an ADR (either an amendment to
      [[0083-ai-assisted-apiary-insights]] or a new numbered ADR,
      whichever reads better once drafted) — this task's output is the
      document, not any code.

## Implementation notes
- No PHP, no tests, no `hivelog` code changes in this task — it is pure
  research and a written decision, deliberately, matching
  [[0083-ai-assisted-apiary-insights]]'s own "cannot be picked up as an
  implementation task yet."
- [[0086-ai-insights-implementation-adr]] is blocked on this task's
  outcome, not the other way around.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0083-ai-assisted-apiary-insights]],
  [[0015-apiary-location-privacy]]
- Commits::
