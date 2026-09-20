---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
branch: feature/0086-ai-insights-implementation-adr
release:
depends-on: ["[[0084-ai-insights-hosting-and-privacy-decision]]", "[[0087-ai-insights-hosting-and-privacy-model]]"]
blocked-by:
---
# Task: Write the AI-insights implementation ADR

## Context
The "proper follow-up ADR" [[0083-ai-assisted-apiary-insights]]
explicitly deferred everything concrete to. Its own text: "intentionally
left to a proper follow-up ADR once [[sensor-data-collection]] has real
pilot data and the privacy question has a real answer, rather than
guessed now." This task is that follow-up — still a documentation
deliverable, not code; actual implementation task files (entity, agent
endpoint, UI) get broken out from *this* ADR once it lands, the same way
[[0074-sensor-data-ingestion-architecture]] led to
[[0076-sensor-device-and-reading-entities]] onward.

## Acceptance criteria
- [ ] Full `HiveInsight` entity schema (fields, types, validation) —
      superseding [[0083-ai-assisted-apiary-insights]] §3's provisional
      sketch (`hive`/`apiary`, `verdict`, recommendation text, cited
      signals, timestamp) with a real, buildable design, following
      [[0003-code-defined-entity-schema]].
- [ ] Access control: how `HiveInsight` resolves through
      `ApiaryAccessTrait`, matching every other entity in the module.
- [ ] The agent's write-back contract: request/response shape for the
      authenticated endpoint an external insight-generating process posts
      to, reusing [[0074-sensor-data-ingestion-architecture]] §3's
      device-token pattern as [[0083-ai-assisted-apiary-insights]] §2
      already decided in principle.
- [ ] UI surface: where an insight is shown (a hive-page panel, a
      dashboard rollup tile, or both) and how the explainability
      requirement (§4 of the parent ADR) actually renders — the cited
      signals must be visible, not just the verdict.
- [ ] Hive-scoped vs. apiary-scoped insights — resolve the open question
      from [[ai-apiary-insights]]'s project file rather than leaving it
      open a second time.
- [ ] Incorporates [[0087-ai-insights-hosting-and-privacy-model]]'s
      decision directly: hosted API by default (self-hosting as a
      supported alternative), the per-field data-minimisation table, and
      the new `Apiary.ai_insights_enabled` consent field — no longer
      blocked, since [[0084-ai-insights-hosting-and-privacy-decision]]
      is done.
- [ ] Incorporates [[0085-sensor-less-insight-prototype]]'s findings if
      available at the time (not a hard blocker, since that task has no
      `depends-on`/`blocked-by` here, but its conclusion should shape
      whether this ADR treats sensor-less reasoning as in-scope for the
      first real implementation or a later addition).
- [ ] Once accepted, this ADR's own "Decision" section should be broken
      into numbered implementation tasks the same way
      [[0074-sensor-data-ingestion-architecture]] was — that breakdown is
      this task's natural follow-on, not part of it.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0083-ai-assisted-apiary-insights]],
  [[0087-ai-insights-hosting-and-privacy-model]],
  [[0074-sensor-data-ingestion-architecture]]
- Commits::
