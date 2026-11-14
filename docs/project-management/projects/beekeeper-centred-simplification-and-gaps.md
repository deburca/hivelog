---
type: project
tags: [hivelog/project]
status: planning
target:
created: 2026-11-14
---
# Project: Beekeeper-centred simplification and gaps

## Goal
Make HiveLog read the way a beekeeper thinks: an apiary holds hives, a hive
is built from parts and holds one queen and her colony, the colony needs
regular and seasonal care, and it gives honey, wax, propolis and other
products that are harvested and perhaps sold. The
[[2026-11-14-beekeeper-domain-audit]] compared the application with that
picture and found places where the structure follows the database instead,
and things a beekeeper does that cannot be recorded.

## Scope
- In scope:
  - Simplification: vocabulary, navigation, the queen observation /
    inspection overlap, the double record of supers, a quick inspection,
    weeks shown as dates
  - Gaps: hives that have ended, harvesting without a planned action,
    starter products, per-hive harvest history, overdue inspections,
    single-hive reminders, queen and colony costs, queen end reason, hive
    type lists
- Out of scope (already planned elsewhere, see the audit note):
  - Treatments ([[treatment-register]]), swarms, splits and losses as
    events ([[colony-lifecycle-events]]), sales and batches
    ([[honey-sales-and-batch-provenance]]), weather
    ([[weather-aware-inspection-planning]]), exports
    ([[data-export-and-printable-records]])

## Tasks
```dataview
TABLE status, priority
FROM #hivelog/task
WHERE contains(string(project), this.file.name)
SORT status asc, priority asc
```
Gaps:
- [[0269-inactive-hives-leave-the-seasonal-workload]] (high)
- [[0270-starter-product-catalogue-and-harvest-yields]] (high)
- [[0271-record-a-harvest-directly]] (high)
- [[0272-hive-harvest-history-and-totals]]
- [[0273-inspection-overdue-alert]]
- [[0274-single-hive-reminders]]
- [[0280-queen-and-colony-acquisition-cost-in-reports]] (low)
- [[0281-queen-end-reason-and-marking]] (low)
- [[0282-hive-type-and-material-lists]] (low)

Simplification:
- [[0275-plain-language-vocabulary]]
- [[0276-navigation-by-beekeeper-job]]
- [[0277-queen-observation-and-inspection-overlap-adr]]
- [[0278-supers-one-source-of-truth]]
- [[0279-quick-inspection-and-colony-strength]]
- [[0283-calendar-weeks-shown-as-dates]] (low)

## Open questions
- Several tasks touch fields in the v1 API contract (supers, disease signs,
  queen observation). Do they wait for an `API_VERSION` 2, or stay additive?
- Is "hive" the box or the colony? [[0186-colony-lifecycle-adr]] has to
  answer it; 0269, 0280 and 0281 lean on the answer.
- Does the vocabulary change (0275) reach the Vinculum app at the same time?

## Related decisions
- 
