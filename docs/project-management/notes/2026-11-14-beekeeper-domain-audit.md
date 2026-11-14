---
type: notes
tags: [hivelog/notes, audit]
created: 2026-11-14
---

# 2026-11-14 — Beekeeper domain audit

An audit of HiveLog 4.3.0 against the plain picture of beekeeping: a
beekeeper needs an apiary; an apiary has hives; a hive is made of components
and holds one queen and her colony; bees make honey, wax, propolis and royal
jelly; they need regular and seasonal care; products are harvested and
perhaps sold. It looked for structure that could be simpler for a beekeeper
and for things that cannot be recorded. Method: reading the entity field
definitions, routes, navigation registry, alert collector, forms and report
controller, and the open tasks and planned projects. Nothing was run in a
browser.

The new work is collected in
[[beekeeper-centred-simplification-and-gaps]].

## How the picture maps today

| Beekeeping | HiveLog | Verdict |
|---|---|---|
| Beekeeper, apiary | `Apiary`, `ApiaryMembership` (owner / beekeeper / novice) | Sound |
| Hives in an apiary | `Hive`, moved only through the move dialog | Sound; ended hives are not handled (0269) |
| Components | `HiveComponent` → `InventoryItem`, empty weight | Sound; supers are recorded twice (0278) |
| One queen | `Queen`, one active per hive, `QueenObservation` | Overlaps the inspection (0277); no end reason (0281) |
| The colony | No entity; folded into `Hive` | Open question for [[0186-colony-lifecycle-adr]] |
| Regular care | `HiveInspection` | Long form, coarse strength (0279); no overdue reminder (0273) |
| Seasonal care | `CalendarAction` and the two action logs | Sound; no single-hive reminder (0274); weeks not dates (0283) |
| Products | `Product`, free-named | Nothing seeded, so royal jelly and even honey start absent (0270) |
| Harvest | `HarvestYield`, only through a calendar log | Cannot harvest unplanned (0271); invisible on the hive (0272) |
| Sale | Potential income only | Planned: [[honey-sales-and-batch-provenance]] |

## Findings: gaps

1. **Ended hives stay in the workload.** `dead`, `sold` and `merged` are read
   nowhere; seasonal alerts and tasks continue and the queen stays active.
   → [[0269-inactive-hives-leave-the-seasonal-workload]]
2. **A new apiary has harvest tasks but no products.** The starter calendar
   has no yields, so a reported harvest records no honey until three screens
   of setup are done. → [[0270-starter-product-catalogue-and-harvest-yields]]
3. **A harvest needs a planned action.** `HarvestYield` has no form or route
   of its own. → [[0271-record-a-harvest-directly]]
4. **No harvest on the hive page.** Per-hive yield is stored and never shown.
   → [[0272-hive-harvest-history-and-totals]]
5. **Nothing says a hive is overdue for inspection.**
   → [[0273-inspection-overdue-alert]]
6. **No reminder for one hive on one date.** → [[0274-single-hive-reminders]]
7. **Queen and colony purchases are outside the cost report.**
   → [[0280-queen-and-colony-acquisition-cost-in-reports]]
8. **A queen ends without a date or reason; marked / clipped unknown.**
   → [[0281-queen-end-reason-and-marking]]
9. **Hive type and material lists are regional.**
   → [[0282-hive-type-and-material-lists]]

## Findings: simplification

1. **Labels are the data model's nouns** (Calendar Actions, Hive Action Logs,
   Inventory Items; an "Insights" hub that holds setup).
   → [[0275-plain-language-vocabulary]]
2. **Navigation lists entity types**, eight under one item, with the logs and
   the financial report reachable only by cross-links.
   → [[0276-navigation-by-beekeeper-job]]
3. **The queen is recorded in two places per visit**, and temperament in four
   entities. → [[0277-queen-observation-and-inspection-overlap-adr]]
4. **Supers are typed on the inspection and again in the composition.**
   → [[0278-supers-one-source-of-truth]]
5. **The inspection form has 22 fields and no short path**, yet lacks frames
   of bees and brood and takes one disease only.
   → [[0279-quick-inspection-and-colony-strength]]
6. **ISO weeks are shown as the schedule.**
   → [[0283-calendar-weeks-shown-as-dates]]

## Already covered, no new task

| Gap | Where it is planned |
|---|---|
| Treatments, doses, withdrawal periods, varroa trend | [[treatment-register]] (0182–0185) |
| Swarms, splits, merges, deaths as dated events; lineage | [[colony-lifecycle-events]] (0186–0188) |
| Selling; batches; honey in stock after harvest | [[honey-sales-and-batch-provenance]], [[0046-real-sales-ledger]] |
| Using stock (sugar, feed) during an inspection | [[0230-inspection-records-items-used]] |
| Weather at and before an inspection | [[weather-aware-inspection-planning]] |
| The hive page as an overview, not a form | [[0253-hive-page-operational-dashboard]] |
| Guidance for a first-time user on empty pages | [[0254-empty-state-improvement]] |
| Totals across apiaries | [[0047-cross-apiary-aggregate-views]] |
| Requirements and yields edited inside the calendar action form | [[0055-embed-required-items-and-expected-yield-in-calendar-action-edit-form]] |
| Product categories | [[0053-product-category-field]] |
| Printed records and exports | [[data-export-and-printable-records]] |

Two of these deserve a nudge from this audit. The sales ADR
([[0192-sales-and-batch-adr]]) should state plainly that a product gets a
**stock on hand** (harvested minus sold or given away), as an inventory item
has. And the lifecycle ADR should settle **hive versus colony** before 0269,
0280 and 0281 are built, since each of them is easier if the colony has its
own start and end.

## Not pursued

- Forage and bloom records for an apiary, and honey quality (moisture,
  floral source): real, but better attached to batches when those exist.
- Queen breeding and pedigree: kept out of scope by
  [[colony-lifecycle-events]].

## Vault observations

- Ten iOS tasks carry `status: deferred`, which is not in the vault's
  status vocabulary.
- Task IDs 0249, 0255 and 0264 are referenced in commits and notes but have
  no file in `tasks/`.
- `roadmap.md` still describes 2.4.0 and does not list this project.
