---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Weather-Aware Inspection Planning

## Goal
Use each apiary's geolocation and a public forecast service to show
upcoming 'good inspection days' (warm, dry, low wind, daytime) on the
apiary page and dashboard, and optionally snapshot conditions onto an
inspection. Mirrors HiveTracks' bee weather forecast, BeeKeepPal's
CheckInspect and Apiary Book's weather integration.

Proposal C from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - A cached forecast client service (candidate: Open-Meteo, no API key)
  - An inspection-window scoring rule and its display
  - Optional weather snapshot on inspection save
- Out of scope:
  - Historical climate analytics
  - Replacing nanoprobe's ambient sensors — sensor data stays authoritative where present

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0179-weather-forecast-adr-and-client]]
- [[0180-inspection-window-panel]]
- [[0181-inspection-weather-snapshot]]

## Open questions
- Sending apiary coordinates to a third-party service is a privacy decision — must be opt-in per site (and possibly per apiary); capture in the ADR.
- Which provider, and should coordinates be rounded before sending?

## Related decisions
- 
