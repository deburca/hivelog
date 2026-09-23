---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-09-23
supersedes:
---
# ADR-0102: Breadcrumb — terminal crumb on non-canonical pages

## Status
proposed. **Amends** [[0013-breadcrumb-policy]] rule 2 only, the same
way [[0057-dashboard-information-architecture]] amended its root-crumb
target. It does not supersede it; rules 1, 3 and 4 stand unchanged.

## Context
[[0013-breadcrumb-policy]] rule 2 says "the current entity's label is
always appended as the terminal crumb on every route — the theme renders
the last link as plain text". That holds on a canonical page, where the
entity *is* the page. It breaks on every page where the entity is the
*parent* of what the user is doing:

- edit / delete forms (`entity.<type>.edit_form` / `.delete_form`)
- scoped add forms (`hivelog.hive.add`, `hivelog.inspection.add`, …)
- site-wide add forms (`entity.<type>.add_form`), which end on their
  collection name

The real front-end themes (`quick_silver`, and `beeswax`, which shares
its template) render `loop.last` as a plain `<li aria-current="page">`,
whatever its URL. So on these pages the one crumb that leads back is
exactly the one that isn't a link. Verified live against `cms2`,
2026-09-23 (`[x]` = link, `x*` = plain terminal):

| Page | Rendered trail |
|---|---|
| `/hivelog/hive/22` | [Home] › [Apiary] › Hive* (correct) |
| `/hivelog/hive/22/edit` | [Home] › [Apiary] › Hive* (no way back to the hive) |
| `/hivelog/hive/22/delete` | [Home] › [Apiary] › Hive* (same) |
| `/hivelog/hive/22/inspection/add` | [Home] › [Apiary] › Hive* (same) |
| `/hivelog/apiary/43/hive/add` | [Home] › [HiveLog] › Apiary* (no way back to the apiary) |
| `/hivelog/queen/add` | [Home] › [HiveLog] › Queens* (no way back to the list) |

The same theme also truncates any trail longer than 3 crumbs to
`Home … <last two>`. On deep pages, the last two crumbs are the only
ones the user sees, so which crumb is terminal matters a lot.

The builder already solved this for a handful of named sub-pages by
adding a terminal crumb after the entity link (`$apiary_page_crumbs`,
`$hive_page_crumbs`, `$sensor_device_page_crumbs`, the API client
Regenerate Token case). This ADR makes that the general rule instead of
a per-route exception.

## Decision
**Every breadcrumb trail ends with a crumb naming the current page.**

- **Canonical pages**: the terminal crumb is the entity's own label, as
  today.
- **Collection pages and the combined report**: the terminal crumb is
  the page's own name, as today.
- **Any other page under `/hivelog`**: the subject entity (or the parent
  entity, for scoped add forms, or the collection, for site-wide add
  forms) stays in the trail as a **link**, followed by a terminal crumb
  naming the page:
  - edit / delete forms: short fixed labels, **"Edit"** / **"Delete"**.
    The preceding crumb already names the entity, so "Edit Hive" would
    only repeat it.
  - add forms (scoped and site-wide): the route's own `_title`
    ("Add Hive", "Add Inspection", "Add Queen", …).
  - named sub-pages (Insights, Calendar, Financial Report, Readings,
    Download Configuration, Regenerate Token): their existing short
    labels, unchanged.
  - Layout Builder override routes: the route title, for consistency.

Where a route's title callback embeds the entity label (e.g.
`ApiaryController::fullCalendarTitle()`), keep an explicit short-label
override so the terminal crumb doesn't repeat the ancestor crumb before it.

## Consequences
- Positive: every form page has a working link back to what it edits
  or adds to. With theme truncation, the visible trail becomes the
  useful one: `Home … [Hive] › Edit`. The named-sub-page maps stop
  being special cases, which removes most of the builder's early
  `return` branches.
- Negative / trade-offs: form-page trails get one crumb longer. The
  change touches the expected trail of every edit / delete / add case
  in `HivelogBreadcrumbBuilderTest`. A theme that already links every
  crumb (Claro, Olivero) gains a redundant-looking but harmless
  non-linked "Edit" crumb.
- Follow-up tasks: [[0117-breadcrumb-terminal-crumb-on-form-pages]]
  implements this, after
  [[0116-breadcrumb-builder-parent-map-refactor]].

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0013-breadcrumb-policy]] (amended),
  [[0057-dashboard-information-architecture]] (the earlier amendment
  precedent)
