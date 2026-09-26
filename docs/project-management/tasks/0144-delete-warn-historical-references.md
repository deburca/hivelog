---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0144-delete-warn-historical-references
release:
depends-on: ["[[0134-delete-dependency-framework]]"]
blocked-by:
---
# Task: Warn (with links) before deleting items / products with history

## Context
Implements the WARN rows of
[[0103-delete-policy-for-records-with-children]]. Blocking is **not
possible or not appropriate** here. Usages / harvest yields (#23, #26)
have no delete page of their own; they are lines entered inline on an
action log. Purchases (#22) could be deleted, but deleting them to
retire a catalog entry would rewrite past cost reports, which is the
rationale of [[0045-warn-before-deleting-referenced-items-and-products]]
that this task keeps. So the user gets a warning with links. If they
ignore it, the parent is deleted and the history shows "Unknown …".

| # | Parent | Child | Today |
|---|---|---|---|
| 22 | InventoryItem | InventoryPurchase (`item`) | `InventoryItemDeleteForm` warns with a combined purchase + usage count, **no links** |
| 23 | InventoryItem | InventoryUsage (`item`) | `InventoryItemDeleteForm` warns with a combined purchase + usage count, **no links** |
| 26 | Product | HarvestYield (`product`) | `ProductDeleteForm` warns with a count, **no links** |


## Acceptance criteria
- [x] All three rows registered with treatment WARN in the registry from
      [[0134-delete-dependency-framework]].
- [x] The warning lists **each action log** containing an affected line
      (label, date, link to the log's edit form where the line can be
      removed), and each affected purchase (link to its page), not just
      a total. If there are many, show the first N
      plus "and M more" with a link to a filtered list, or to the
      apiary's calendar.
- [x] Wording explains the consequence: "If you delete anyway, these
      log lines will show 'Unknown item' and will still count toward
      past cost reports." Confirm the report behaviour in code and
      match the text to it.
- [x] Delete stays available, and deleting leaves the purchase / usage /
      yield rows intact (same as today).
- [x] The two hand-written counters
      (`InventoryItemDeleteForm::countHistoricalReferences()` and the
      `ProductDeleteForm` equivalent) are removed in favour of the
      framework.
- [x] Financial reports: verify `InventoryReportController` (cost
      report, combined report) handles an item / product that no longer
      exists. It should show "Unknown item" / "Unknown product" rather
      than error or silently drop the cost. Add a kernel test.
- [x] Kernel tests: the warning lists the right logs; deleting anyway
      keeps the purchase / usage / yield rows; reports still render.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**Registry rows were already in place** — like task 0143's DETACH rows,
all three WARN rows (#22 InventoryItem→InventoryPurchase, #23
InventoryItem→InventoryUsage, #26 Product→HarvestYield) were registered
during task 0134's initial mass-registration. Nothing to add to
`HivelogDeleteDependencyRegistry`.

**Shared rendering extension points added to `HivelogEntityDeleteForm`**,
so the richer per-row WARN rendering doesn't disturb the generic
BLOCK/CASCADE/DETACH sections every other entity type still uses:
- `buildRowItems(array $row): array` — plural; defaults to the existing
  single bare-count-plus-link item (`buildRowItem()`), but
  `InventoryItemDeleteForm`/`ProductDeleteForm` override it per
  `$row['adr_row']` to return one list item per affected purchase/log
  instead.
- `warnDescription(): ?TranslatableMarkup` — an optional explanatory
  paragraph rendered between a WARN section's heading and its item
  list; NULL by default, overridden by both subclasses for the
  "Unknown item"/"Unknown product" consequence text the AC specifies
  verbatim.
- `truncatedItems(array $items, ?Url $more_url)` — caps a WARN row's
  detail list at `WARN_DETAIL_LIMIT` (10), collapsing the rest into one
  "and N more" line, linked when `$more_url` is accessible.
- `resolveActionLog(EntityInterface $entity): ?EntityInterface` — the
  hive/apiary action log an `InventoryUsage`/`HarvestYield` row was
  recorded against (their `preSave()`'s own "exactly one of
  `hive_action_log`/`apiary_action_log`" invariant), shared by both
  subclasses.
- `buildActionLogItem(EntityInterface $log): array` — one detail-list
  item: the log's own label plus its `created` timestamp as the
  "reported on" date (a `HiveActionLog`/`ApiaryActionLog` row only
  exists once "done"/"ignored" has actually been reported — see either
  class's own docblock — so `created` is a genuinely meaningful date;
  neither entity has a dedicated date field beyond the `year` integer
  already in `label()`), linked to the log's edit form.

`InventoryItemDeleteForm::buildUsageLogItems()` and
`ProductDeleteForm::buildYieldLogItems()` both deduplicate by
`"<entity_type>:<id>"` before rendering — several usage/yield rows can
share one action log (a single reported action can use more than one
inventory item, or yield more than one product), so the WARN list names
the log actually worth revisiting once, not once per usage row.

**Found and fixed the real report-aggregation bug the AC's own bullet 5
called out.** `InventoryReportController::consumableCostBreakdown()`
and `yieldBreakdown()` both `continue`d past any usage/yield row whose
`item`/`product` reference had gone empty (exactly what a WARN-treated
delete leaves behind) — silently dropping that row's cost/income from
every apiary/year total, the combined report, the 5-year trend, and the
dashboard's "Net YTD" tile (all of which run through
`computeApiaryYearTotals()`). Fixed by bucketing a dangling row under
key `0` with `item`/`product: NULL` instead of skipping it, and
precomputing a `unit` string in each breakdown row (`''` for the
NULL-item bucket) so `costReport()`'s table renderer doesn't need to
call `->get('unit')` on a possibly-NULL entity. `costReport()` now
renders "Unknown item"/"Unknown product" text instead of `->toLink()`
for that bucket, and every cache-dependency loop
(`addCacheableDependency()`/`addTotalsCacheDependencies()`/
`buildTrendRows()`/`buildCombinedTrendRows()`) skips the NULL entity
rather than erroring. `depreciationBreakdown()` was never affected —
its rows are loaded directly from `InventoryItem`, never via a
dangling reference.

**Verification.** phpcs clean. phpstan clean — regenerated
`phpstan-baseline.neon` (436 → 434 findings; diffed against the
original to confirm the only changes were the two now-stale
`getDescription()`-call-count baseline entries for `InventoryItemTest`/
`ProductTest`, dropped from 2 to 1 each, matching the test rewrites
below — no new findings anywhere). Full kernel/unit/functional suite
against `cms2`: 730 tests, 0 failures (only the pre-existing geofield
attribute-discovery deprecation noise, unrelated). Updated
`InventoryItemTest::testDeleteWarningPresentWhenPurchasesExist` and
`ProductTest::testDeleteWarningPresentWhenHarvestYieldsExist` — both
used to assert on `getDescription()`'s old bare-count string, which no
longer carries this content; rewritten to render the WARN section via
`entity.form_builder` and assert on the actual purchase/log link and
the "Unknown item"/"Unknown product" wording (matching
`HivelogDeleteDependencyFrameworkTest::testDeleteFormShowsWarnSectionWithLink`'s
existing pattern, which needed no changes). Added two new tests to
`InventoryCostReportTest` — `testDeletedItemStillCountsInCostReportAsUnknown`
and `testDeletedProductStillCountsInCostReportAsUnknown` — each
recording real usage/yield via the action-log form flow, deleting the
item/product via the raw entity API (the WARN row's own bypass path),
and asserting the report still shows "Unknown item"/"Unknown product"
*and* the correct, undropped cost/income figure.

Live-verified on `cms2` with throwaway fixtures: the WARN section
rendered the exact expected markup (purchase linked to its canonical
page, "Before you delete" heading, the "Unknown item" consequence
paragraph); a directly-created `InventoryUsage` row's owning log
appeared in the same section once its item was deleted, and the cost
report correctly showed "Unknown item" for it. One live-verification
misstep is worth recording: an overly broad cleanup query (`calendar_action.title LIKE '%Feeding%'`)
accidentally deleted two real `HiveActionLog` rows belonging to the
`assimilate` demo submodule's seeded "Assimilate Demo Hive" fixture
(not reproducible — `assimilate_cron()` only regenerates
`SensorReading` rows, and `DemoDataProvisioner` has no method that
creates demo action logs). Flagged to and accepted by the repo owner as
an acceptable loss (cosmetic demo/dashboard data only); no code in this
task caused or is affected by it.

- Key files: `src/Form/HivelogEntityDeleteForm.php` (shared extension
  points), `src/Form/InventoryItemDeleteForm.php`,
  `src/Form/ProductDeleteForm.php` (both rewritten),
  `src/Controller/InventoryReportController.php` (the NULL-safety fix),
  `phpstan-baseline.neon` (regenerated),
  `tests/src/Kernel/InventoryItemTest.php`,
  `tests/src/Kernel/ProductTest.php` (both updated),
  `tests/src/Kernel/InventoryCostReportTest.php` (two new tests).

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0045-warn-before-deleting-referenced-items-and-products]] (the
  original warnings this generalises)
- Commits::
