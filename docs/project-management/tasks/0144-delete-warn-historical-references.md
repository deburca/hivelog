---
type: task
tags: [hivelog/task]
status: todo
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
- [ ] All three rows registered with treatment WARN in the registry from
      [[0134-delete-dependency-framework]].
- [ ] The warning lists **each action log** containing an affected line
      (label, date, link to the log's edit form where the line can be
      removed), and each affected purchase (link to its page), not just
      a total. If there are many, show the first N
      plus "and M more" with a link to a filtered list, or to the
      apiary's calendar.
- [ ] Wording explains the consequence: "If you delete anyway, these
      log lines will show 'Unknown item' and will still count toward
      past cost reports." Confirm the report behaviour in code and
      match the text to it.
- [ ] Delete stays available, and deleting leaves the purchase / usage /
      yield rows intact (same as today).
- [ ] The two hand-written counters
      (`InventoryItemDeleteForm::countHistoricalReferences()` and the
      `ProductDeleteForm` equivalent) are removed in favour of the
      framework.
- [ ] Financial reports: verify `InventoryReportController` (cost
      report, combined report) handles an item / product that no longer
      exists. It should show "Unknown item" / "Unknown product" rather
      than error or silently drop the cost. Add a kernel test.
- [ ] Kernel tests: the warning lists the right logs; deleting anyway
      keeps the purchase / usage / yield rows; reports still render.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]],
  [[0045-warn-before-deleting-referenced-items-and-products]] (the
  original warnings this generalises)
- Commits::
