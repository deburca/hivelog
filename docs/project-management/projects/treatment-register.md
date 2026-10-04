---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-10-04
---
# Project: Treatment Register

## Goal
A structured veterinary treatment record per hive (product, batch/lot,
dose, start/end, withdrawal period, supers removed) with a varroa-count
trend and threshold alert, and a printable register. Apiary Book records
veterinary controls; BeeHero logs treatments; and in many jurisdictions
beekeepers must keep a veterinary medicines record. HiveLog today only
has a free-text varroa count on inspections and treatment *calendar
actions*.

Proposal D from the [[2026-10-04-beekeeping-software-market-survey]].

## Scope
- In scope:
  - ADR on how treatments relate to calendar action logs and inventory usage
  - A treatment entity, forms, hive-page section and update hook
  - Varroa trend chart and a needs-attention threshold alert
  - A per-apiary/per-year treatment register report
- Out of scope:
  - Jurisdiction-specific regulatory submissions
  - Medicine stock control beyond what `InventoryUsage` already does

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
- [[0182-treatment-register-adr]]
- [[0183-treatment-entity-and-ui]]
- [[0184-varroa-trend-and-threshold-alert]]
- [[0185-treatment-register-report]]

## Open questions
- Which jurisdiction's record requirements should the field set follow by default?
- Should reporting a varroa-treatment `HiveActionLog` as done auto-create a treatment record?

## Related decisions
- 
