---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[treatment-register]]"
area: dashboard
created: 2026-10-04
branch: feature/0184-varroa-trend-and-threshold-alert
release:
depends-on:
blocked-by:
---
# Task: Varroa trend chart and threshold alert

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal D. `HiveInspection` already stores Varroa Check / Varroa Count
but nothing trends or alerts on it.
Part of [[treatment-register]].

## Acceptance criteria
- [ ] A varroa count trend on the hive Insights page
      (`hook_hivelog_hive_insights_panels()`), with treatments overlaid
      once [[0183-treatment-entity-and-ui]] lands
- [ ] A configurable threshold (site setting) raising a needs-attention alert
      via `hook_hivelog_needs_attention_alerts()`
- [ ] Document what the count measures (mites per 100 bees vs natural drop) —
      normalise or label accordingly
- [ ] Kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[treatment-register]]
- Decisions:: 
- Commits:: 
