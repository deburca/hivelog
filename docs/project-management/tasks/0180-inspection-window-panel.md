---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[weather-aware-inspection-planning]]"
area: dashboard
created: 2026-10-04
branch: feature/0180-inspection-window-panel
release:
depends-on: ["[[0179-weather-forecast-adr-and-client]]"]
blocked-by:
---
# Task: Good-inspection-days panel

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal C.
Part of [[weather-aware-inspection-planning]].

## Acceptance criteria
- [ ] A documented scoring rule (temperature, precipitation, wind, daylight)
      producing good/marginal/poor windows for the next 7 days
- [ ] Shown as a top-level H2 section on the Apiary page and as a dashboard
      section via `hook_hivelog_dashboard_sections()`
- [ ] Uses surface/severity tokens; responsive at 480/768px
- [ ] Kernel test for the scoring rule and cache metadata
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- 

## Related
- Project:: [[weather-aware-inspection-planning]]
- Decisions:: 
- Commits:: 
