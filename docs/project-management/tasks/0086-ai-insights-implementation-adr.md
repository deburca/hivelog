---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
completed: 2026-09-20
branch: feature/0086-ai-insights-implementation-adr
release:
depends-on: ["[[0084-ai-insights-hosting-and-privacy-decision]]", "[[0087-ai-insights-hosting-and-privacy-model]]", "[[0085-sensor-less-insight-prototype]]"]
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
- [x] Full `HiveInsight` entity schema (fields, types, validation) —
      superseding [[0083-ai-assisted-apiary-insights]] §3's provisional
      sketch with a real, buildable design, following
      [[0003-code-defined-entity-schema]]. **Done —
      [[0088-ai-insights-implementation]] §1**: `apiary`/`hive`/`scope`
      (reusing `CalendarAction`/`SensorDevice`'s duality), code-defined
      `verdict`, `recommendation`, `signals` (rendered via the existing
      `SimpleBulletText` utility — no new rendering mechanism),
      `confidence`, `context_snapshot`, `generated`/`created`.
- [x] Access control: how `HiveInsight` resolves through
      `ApiaryAccessTrait`, matching every other entity in the module.
      **Done — [[0088-ai-insights-implementation]] §3**: identical to
      `SensorDevice`'s resolution chain, zero exceptions for reads.
- [x] The agent's write-back contract. **Done — went further than
      asked: [[0088-ai-insights-implementation]] §2 also specifies the
      *context-read* endpoint the agent needs first (a real gap
      [[0083-ai-assisted-apiary-insights]] §2 left unaddressed — it only
      covered writing output, never reading input), and makes that
      endpoint the actual enforcement point for
      `Apiary.ai_insights_enabled` and every
      [[0087-ai-insights-hosting-and-privacy-model]] data-minimisation
      rule.**
- [x] UI surface. **Done — [[0088-ai-insights-implementation]] §4**: a
      hive-page panel (with staleness flagging past ~48h), a dashboard
      section kept separate from "Needs attention" (never merged — the
      row shapes don't fit), and — the concrete answer to the user's
      "no inspection currently needed" example — a positive "N hives all
      clear today" summary line nothing else in hivelog currently
      provides.
- [x] Hive-scoped vs. apiary-scoped insights. **Resolved —
      [[0088-ai-insights-implementation]] §5: schema supports both from
      day one, Phase 1 ships hive-scoped only (matches every
      [[0085-sensor-less-insight-prototype]] scenario and the user's own
      three examples), apiary-scoped deferred to Phase 2 the same way
      [[0025-seasonal-calendar-and-hive-action-tracking]] preceded
      [[0027-apiary-vs-hive-scoped-calendar-items]].**
- [x] Incorporates [[0087-ai-insights-hosting-and-privacy-model]]'s
      decision directly. **Done** — the data-minimisation table is
      enforced in exactly one place (the context-read endpoint), not
      scattered; `ai_insights_enabled` gates that same endpoint.
- [x] Incorporates [[0085-sensor-less-insight-prototype]]'s finding and
      its two flagged gaps. **Done — [[0088-ai-insights-implementation]]
      §6 ("Pre-launch validation")** makes both gaps explicit,
      non-optional gates before Phase 1 ships to real data: re-validate
      at the actual production (Haiku-class) model tier, and test
      ambiguous/sparse inspection histories the prototype's three
      deliberately-clear scenarios never exercised.
- [x] Note that breaking this ADR into numbered implementation tasks is
      a separate follow-on step, not part of this task. **Done —
      [[0088-ai-insights-implementation]]'s Consequences says so
      explicitly; no task files opened by this task.**

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0088-ai-insights-implementation]] (the resulting ADR),
  [[0083-ai-assisted-apiary-insights]],
  [[0087-ai-insights-hosting-and-privacy-model]],
  [[0074-sensor-data-ingestion-architecture]]
- Commits::
