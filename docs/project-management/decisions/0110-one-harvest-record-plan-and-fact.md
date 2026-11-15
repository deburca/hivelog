---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-11-15
supersedes:
---

# ADR-0110: One harvest record, and a plain line between plan and fact

## Status
accepted, 2026-11-15, by the maintainer, as proposed the same day and with
the three questions below answered as proposed. Written after the maintainer
found the Products section, the Harvests section and the "product yield" on
a calendar action hard to tell apart on the local site. Amends
[[0034-honey-wax-propolis-yield-and-potential-income]]; does not supersede
it.

## Context
A product and a quantity meet in four places, and they mean different
things:

| Where | What it is | Today's name |
|---|---|---|
| Products, on the apiary page | The catalogue: what the apiary produces, its unit and expected price | Product |
| A calendar action's page | A plan: how much the action is expected to give each time | Product yield (`CalendarActionProductYield`) |
| An action log, when reported done | A fact: how much it really gave | Harvest yield (`HarvestYield` with a log) |
| Harvests, on the hive and apiary pages | A fact, recorded without a calendar action | Harvest yield (`HarvestYield`, direct, task 0271) |

The last two are the same real event, a harvest, recorded two ways and shown
in different places. What is wrong with that:

1. **The Harvests section lists only half of the harvests.** It shows the
   ones recorded directly. A harvest reported through the calendar appears
   only in the financial report. On an apiary whose harvests are calendar
   tasks the section is empty although honey was taken.
2. **The hive's totals do the same.** The "Harvest this year" tile and the
   Harvest History (tasks 0272, 0284) count direct harvests only, so they
   can read lower than the apiary's report for the same hive.
3. **The two kinds are stored differently.** A direct harvest carries its
   own `apiary`, `hive` and `harvest_date`. A logged one carries none of
   them and borrows its apiary, hive and year from its log, so every list
   and report needs two queries joined by hand
   (`InventoryReportController::yieldBreakdown()` already does).
4. **A logged harvest needs a plan first.** The log form offers a quantity
   only for a product the action expects (a `CalendarActionProductYield`).
   Task 0270 patched this for the starter calendar; any other action still
   cannot record a product nobody planned for.
5. **The words do not separate plan from fact.** "Product yield" on the
   action and "harvest yield" for the record read as one thing.

## Decision

### 1. A harvest is one kind of record, whatever screen wrote it
`HarvestYield` stays the single entity for a real harvest. **Every** harvest
carries its own `apiary`, `harvest_date` and, when it came from one hive,
`hive`, set when it is created and never changed afterwards. A harvest
reported with a calendar action also keeps its `hive_action_log` or
`apiary_action_log` reference: that is where it came from, not where its
apiary, hive or date are read from.

- Existing logged harvests are filled in by an update hook: `apiary` from
  the log's own stamp (a hive log's `apiary` field, an apiary log's apiary),
  `hive` from a hive log, and `harvest_date` from the log (see open
  question 1).
- `isDirect()` keeps its meaning, "no log", and is used only to say where a
  harvest came from.
- Reports, lists and totals read a harvest's own fields. The two-query join
  through logs goes away.

### 2. One list of harvests
The Harvests section on the hive and apiary pages lists every harvest, with
a column saying where it came from ("Calendar: Harvest Spring Honey" linking
to the log, or "Recorded directly"). The hive's totals
(`HivelogHarvestTotals`) count every harvest. The Products table gains a
"Harvested this year" column, so the catalogue and the log visibly connect.

### 3. Reporting a calendar harvest uses the same harvest fields
The action log form keeps its harvest in the same step as "Done": a
beekeeper should not have to save twice. But the block is the same set of
fields as "Record harvest" (product, quantity, date), built by shared code,
and:

- it is pre-filled with one row per expected harvest, as today;
- **a product the action does not expect can be added** on the spot, so a
  plan is a convenience and no longer a prerequisite;
- a row left at zero writes nothing.

Editing or deleting such a harvest afterwards is done from the log, as
today; the Harvests list links there for a logged row.

### 4. Names that say plan or fact
| Thing | Name in the interface |
|---|---|
| `Product` | Product (unchanged; [[0275-plain-language-vocabulary]] may revisit) |
| `CalendarActionProductYield` | **Expected harvest** |
| `HarvestYield` | **Harvest** |

Machine names, routes, permissions and tables do not change.

## Alternatives considered
- **Keep two kinds and only merge the lists.** Smallest change, and it fixes
  what the maintainer saw. Rejected as the end state: every new list, total
  or export would again have to join two shapes, which is how the Harvests
  section and the hive totals came to show half the data.
- **Remove the harvest from the log form; "Done" then opens Record
  harvest.** One form, but two saves for the commonest case, and a harvest
  action could be marked done with nothing recorded.
- **Make every harvest a log**, with a hidden calendar action for unplanned
  ones. Rejected in ADR-0034's amendment of 2026-11-14 for the reasons given
  there.

## Consequences
- Positive:
  - One place shows all harvests; the hive's totals agree with the apiary's
    report.
  - One query for any harvest list or report.
  - A product can be harvested on a calendar task without planning it first.
  - Plan and fact have different names.
- Negative / trade-offs:
  - A schema-data migration: an update hook backfills three fields on every
    logged harvest. It must be safe to run twice and must not touch
    `changed`.
  - A logged harvest now stores its apiary and hive as well as reaching them
    through its log. The stored values are the truth; the log reference is
    history. A hive that moves keeps its past harvests in the old apiary, as
    its logs already do.
  - The delete-registry rows `0034-1` / `0034-2` (hive and apiary are
    blocked by their harvests) now also match logged harvests. Their logs
    already block the same parents, so nothing new is blocked, but the
    delete page will name both.
  - Labels change in the interface and in tests that assert them.
- Not affected: `hivelog_api` (harvests are not an exposed resource), the
  price snapshot rule, potential income.

## Questions settled on acceptance
1. **The date an existing logged harvest gets.** A log has `year` and
   `week_completed`, not a date. Decided: the Monday of `week_completed` in
   `year`, or 1 July of `year` when there is no week. The year is what the
   reports use, and it is preserved either way.
2. **The default date of a new logged harvest.** Decided: today, editable.
3. **A harvest for an apiary-scoped action names no hive**, as a direct
   apiary harvest names none.

The harvest stays inside the action log form (Decision §3); the alternative
of opening Record harvest after "Done" was not taken.

## Follow-up tasks
- [[0285-every-harvest-carries-its-own-apiary-hive-and-date]]
- [[0286-one-list-of-harvests-and-totals-that-count-them-all]]
- [[0287-calendar-harvest-uses-the-harvest-fields]]
- [[0288-expected-harvest-and-harvest-wording]]

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0034-honey-wax-propolis-yield-and-potential-income]],
  [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0270-starter-product-catalogue-and-harvest-yields]],
  [[0271-record-a-harvest-directly]],
  [[0272-hive-harvest-history-and-totals]],
  [[0284-harvest-totals-per-product-not-across-units]]
