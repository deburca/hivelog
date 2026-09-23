---
type: task
tags: [hivelog/task]
status: todo
priority: high
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0117-breadcrumb-terminal-crumb-on-form-pages
release:
depends-on: ["[[0116-breadcrumb-builder-parent-map-refactor]]"]
blocked-by:
---
# Task: Terminal page crumb on edit / delete / add pages

## Context
**User-visible bug**, found in the navigation and breadcrumb review of
2026-09-23 and verified live against `cms2`. On every edit, delete and
add form, the breadcrumb ends with the same crumb as the entity's own
view page (or, for site-wide add forms, the collection). The theme
renders the last crumb as plain text, so the page the user would go back
to can't be clicked. For example, `/hivelog/hive/22/edit` renders
`[Home] › [Apiary] › Hive`, with "Hive" not a link. Full evidence table
and the rule being adopted:
[[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]].

Depends on [[0116-breadcrumb-builder-parent-map-refactor]] because the
refactor turns this fix into one generic rule. If the refactor slips,
this can still land on the current builder as a small per-route
addition; the refactor is not strictly required.

## Acceptance criteria
- [ ] Edit / delete forms for every hivelog-owned and submodule entity
      type end `… › [<Entity>] › Edit` / `… › [<Entity>] › Delete`, with
      the entity crumb linked.
- [ ] Scoped add forms end `… › [<Parent>] › <route title>`, e.g.
      `/hivelog/apiary/43/hive/add` → `[HiveLog] › [Apiary] › Add Hive`.
      Covers `hivelog.hive.add`, `.inspection.add`, `.queen.add`,
      `.queen_observation.add`, `.calendar_action.add`,
      `.hive_action_log.add`, `.apiary_action_log.add`,
      `.inventory_item.add`, `.inventory_purchase.add`, `.product.add`,
      `.calendar_action_item_requirement.add`,
      `.calendar_action_product_yield.add`,
      `nanoprobe.sensor_device.add_for_hive` / `.add_for_apiary`.
- [ ] Site-wide add forms (`entity.<type>.add_form`) end
      `[HiveLog] › [<Collection>] › <route title>`, with the collection
      linked. For example, `/hivelog/queen/add` → `[Queens] › Add Queen`.
- [ ] Named sub-pages (Insights, Calendar, Financial Report, Readings,
      Download Configuration, Regenerate Token) keep their current trails.
      They already follow the rule.
- [ ] Layout Builder override routes get a terminal crumb from the
      route title.
- [ ] Canonical pages and collection pages are **unchanged**.
- [ ] `HivelogBreadcrumbBuilderTest` updated: every edit / delete / add
      data-provider case asserts the new terminal crumb and that the
      preceding crumb targets the entity's canonical route (or the
      collection route).
- [ ] Verified live on `cms2` (`Host: drupal-cms2.ddev.site`) for at
      least: hive edit, hive delete, hive-scoped inspection add,
      apiary-scoped hive add, site-wide queen add, sensor device edit.
- [ ] [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]] flipped
      to `accepted` in the same commit.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Terminal label source: fixed "Edit" / "Delete" for
  `entity.*.edit_form` / `.delete_form`; the route's static `_title`
  for add routes (read from the route object's defaults; no
  `TitleResolver` needed for static titles). Keep an explicit override
  map for any route whose title callback embeds an entity label.
- The terminal crumb links to the current route (a self-link the theme
  renders as plain text), matching how collection crumbs already work,
  not `<nolink>`.
- Key files: `src/Breadcrumb/HivelogBreadcrumbBuilder.php`,
  `tests/src/Unit/Breadcrumb/HivelogBreadcrumbBuilderTest.php`.

## Related
- Project:: [[breadcrumb-consistency]]
- Decisions:: [[0102-breadcrumb-terminal-crumb-on-non-canonical-pages]],
  [[0013-breadcrumb-policy]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
