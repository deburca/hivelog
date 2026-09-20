---
type: task
tags: [hivelog/task]
status: backlog
priority: low
project: "[[ai-apiary-insights]]"
area: entity
created: 2026-09-20
branch: feature/0085-sensor-less-insight-prototype
release:
depends-on:
blocked-by:
---
# Task: Prototype — does inspection/calendar-only reasoning work without sensors?

## Context
[[0083-ai-assisted-apiary-insights]] argues the feature's value doesn't
strictly require [[sensor-data-collection]] to ship first — a
sensor-less hive with just `HiveInspection`/`CalendarAction`/
`HiveActionLog` history is already a plausible, if thinner, input. That's
an untested hypothesis, not a decision — this task is a throwaway
prototype to find out before committing either way, not a request to
build any real hivelog feature.

## Acceptance criteria
- [ ] Assemble a realistic-but-synthetic "hive context" — a plausible
      set of `HiveInspection` records, `CalendarAction`/`HiveActionLog`
      status, and queen history for one fictional hive over one season
      (**not** a real beekeeper's live data, to sidestep
      [[0084-ai-insights-hosting-and-privacy-decision]] entirely for
      this exploratory step).
- [ ] Feed that context to a model (any convenient one for this
      throwaway spike — the real hosting/model choice is
      [[0084-ai-insights-hosting-and-privacy-decision]]'s job, not
      this task's) and see whether it produces a recommendation in the
      shape [[0083-ai-assisted-apiary-insights]] describes (act now /
      inspect soon / all clear, with cited signals).
- [ ] Try at least one scenario per verdict (an obviously-overdue
      calendar action → act now; a pattern that should read as swarm
      risk from inspection notes alone → inspect soon; a
      clean/uneventful season → all clear) to see whether inspection/
      calendar data alone carries enough signal for all three, or only
      some.
- [ ] Write up the finding plainly: does sensor-less reasoning produce
      genuinely useful output, or is it too thin to be worth shipping
      before sensor data exists? This determines whether
      [[ai-apiary-insights]] should be sequenced independently of
      [[sensor-data-collection]] or effectively wait for it.

## Implementation notes
- Explicitly a spike: no hivelog code, no tests, no entity changes. If
  the prototype looks promising, its learnings feed
  [[0086-ai-insights-implementation-adr]]; if not, that ADR can drop
  "sensor-less reasoning" as a near-term goal without having guessed.

## Related
- Project:: [[ai-apiary-insights]]
- Decisions:: [[0083-ai-assisted-apiary-insights]]
- Commits::
