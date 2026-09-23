---
type: task
tags: [hivelog/task]
status: todo
priority: high
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0134-delete-dependency-framework
release:
depends-on:
blocked-by:
---
# Task: Delete-dependency framework (relationship registry, counts, form rendering)

## Context
[[0103-delete-policy-for-records-with-children]] inventories all 28
parent → child relationships and assigns each one a treatment: BLOCK,
WARN, CASCADE or DETACH. This task builds the shared machinery. The
four follow-up tasks each switch on one treatment:
[[0141-delete-block-relationships]],
[[0142-delete-cascade-owned-records]],
[[0143-delete-detach-optional-references]],
[[0144-delete-warn-historical-references]].

Today there is no delete handling at all. The only dependent-record
logic is two hand-written counters in `InventoryItemDeleteForm` and
`ProductDeleteForm`.

## Acceptance criteria
- [ ] [[0103-delete-policy-for-records-with-children]] confirmed by the
      user (CASCADE / DETACH plus the open questions) and flipped to
      `accepted` before implementation starts.
- [ ] **One relationship registry**, declared once: parent type, child
      type, reference field, treatment, and "where to manage these
      children" link target (route + parameters, optional `#anchor`).
      It extends or reuses the parent map from
      [[0116-breadcrumb-builder-parent-map-refactor]] (coordinate:
      whichever lands first owns the shared class). Submodules register
      their rows (#7, #8, #12, #13, #27, #28) via a hook
      (`hook_hivelog_delete_dependencies()`, documented in
      `hivelog.api.php`) or tagged services.
- [ ] **A dependency counter service** returns, for an entity, the
      per-row counts of referencing children: all children
      (`accessCheck(FALSE)`) and those the current user can delete. It
      caches per request, so list pages with a Delete button per row
      don't run N × M queries (a grouped count per child type per page).
- [ ] **Access integration point**: a helper each access control
      handler's `delete` branch calls, returning
      `AccessResult::forbidden($reason)` when any BLOCK row has
      children. Cacheability: the child types' list cache tags. Wired
      up by [[0141-delete-block-relationships]], not here.
- [ ] **Delete form rendering** in the shared base from
      [[0127-shared-delete-form-base]] (or a trait, if 0127 hasn't
      landed). One section per treatment present:
      - BLOCK: "Can't delete yet" with counts, links, and the
        can't-delete-these-yourself wording from the ADR; no Delete
        button.
      - WARN: a warning with counts and links; Delete stays.
      - CASCADE: "Will also be deleted: …" with counts.
      - DETACH: "Will be kept but unlinked: …" with counts and
        reassign links.
      The styling reuses `.hivelog-notice--warning` (`hivelog/notices`
      library) and adds a critical variant for BLOCK if needed, using
      the `--hivelog-critical*` tokens.
- [ ] **Delete execution hook**: a single place (the entity storage's
      delete or `hook_entity_predelete()`) that runs CASCADE and DETACH
      for the rows registered against the entity being deleted, whatever
      the delete path, not only the form. Populated by 0142 / 0143.
- [ ] Links are shown only when the current user can access the target
      (`Url::access()`); otherwise the count is shown without a link.
- [ ] Kernel tests for the framework itself, using a test-only
      relationship registered by a test module: counting, the
      per-request cache, rendering of each treatment section, and access
      integration returning forbidden with the reason.
- [ ] `InventoryItemDeleteForm::countHistoricalReferences()` and
      `ProductDeleteForm`'s counter removed once
      [[0141-delete-block-relationships]] /
      [[0144-delete-warn-historical-references]] have moved their rows
      onto the framework.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Keep the registry data-only (arrays or value objects) so it can be
  unit-tested and dumped for docs. The ADR's inventory table should be
  generated from it or asserted against it by a test, so the two
  can't drift.
- Key files: new `src/Delete/` (registry, counter service, form trait),
  `hivelog.services.yml`, `hivelog.api.php`,
  `src/*AccessControlHandler.php`, `src/Form/*DeleteForm.php`.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0141-delete-block-relationships]],
  [[0142-delete-cascade-owned-records]],
  [[0143-delete-detach-optional-references]],
  [[0144-delete-warn-historical-references]],
  [[0145-orphan-report-and-cleanup-command]],
  [[0127-shared-delete-form-base]],
  [[0116-breadcrumb-builder-parent-map-refactor]]
- Commits::
