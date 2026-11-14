---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: dashboard
created: 2026-11-14
branch: feature/0273-inspection-overdue-alert
release:
depends-on: ["[[0269-inactive-hives-leave-the-seasonal-workload]]"]
blocked-by:
---
# Task: Alert when a hive has not been inspected in season

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

"Regular care" has no reminder. The alert collector has three sources (seasonal calendar,
low stock, submodules); none looks at inspection dates. The apiary's hive table shows a "Last
inspection" column, but a hive untouched for five weeks in May raises nothing on the dashboard.
[[0180-inspection-window-panel]] is about weather, not about elapsed time.

## Acceptance criteria
- [ ] A needs-attention row for an active hive whose last inspection is older than a threshold, only inside an inspection season
- [ ] Threshold (default 14 days) and season (default weeks 14 to 40) are settings; decide whether per site or per apiary
- [ ] A "Last inspected" tile on the hive page with a warning sub-line past the threshold
- [ ] Rows carry `kind` and `subject` so `computed/alerts` serves them
- [ ] Kernel tests
- [ ] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/HivelogAlertCollector.php`, `src/HivelogStatTileBuilder.php`
- Update hook needed? Only if the threshold is stored on the apiary.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
