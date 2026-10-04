---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[weather-aware-inspection-planning]]"
area: integration
created: 2026-10-04
branch: feature/0179-weather-forecast-adr-and-client
release:
depends-on:
blocked-by:
---
# Task: ADR and forecast client service

## Context
Market survey ([[2026-10-04-beekeeping-software-market-survey]])
proposal C.
Part of [[weather-aware-inspection-planning]].

## Acceptance criteria
- [ ] ADR (next free number): provider choice, opt-in (site setting, default
      off), coordinate rounding, caching TTL, failure behaviour
- [ ] A `hivelog.weather_forecast` service returning an hourly forecast for an
      apiary's geofield point, cached per rounded location
- [ ] Graceful degradation: no setting/no geolocation/HTTP failure → no panel,
      no error
- [ ] Unit test with mocked HTTP client
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Nothing calls out to the network unless the site opts in

## Related
- Project:: [[weather-aware-inspection-planning]]
- Decisions:: 
- Commits:: 
