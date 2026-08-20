---
type: task
tags: [hivelog/task]
status: backlog
priority: high
project: "[[inventory-and-yield-improvements]]"
area: entity
created: 2026-08-20
branch: feature/0041-scope-item-and-product-autocomplete-to-current-apiary
release:
depends-on:
blocked-by:
---
# Task: Scope item/product autocomplete widgets to the current apiary

## Context
`InventoryPurchase.item`, `CalendarActionItemRequirement.item`, and
`CalendarActionProductYield.product` all use a plain
`entity_reference_autocomplete` widget with no `handler_settings`
filtering. A beekeeper with more than one apiary — exactly the users
per-apiary scoping (ADR-0027's confirmed decision) was built for — gets
autocomplete suggestions spanning every item/product across every apiary
they can see, not just the one they're on. The same-apiary guard only
fires in `preSave()`/`validateForm()` at submit time, as a generic error
— a silent trap rather than a filtered picker.

## Acceptance criteria
- [ ] Each of the three fields' `entity_reference_autocomplete` widget is
      scoped to the current apiary at render time — investigate whether
      Drupal's built-in `handler_settings.target_bundles`/context-based
      selection handler filtering can do this declaratively for a
      non-bundled entity type, or whether an apiary-aware
      `EntityReferenceSelection` plugin (or a lighter form-level `#autocomplete_route_parameters`
      override) is needed. `InventoryItem`/`Product` are not bundled
      entities, so this may need a small custom selection handler rather
      than pure `target_bundles` config — a decision to make while
      implementing, not pre-decided here.
- [ ] The existing `preSave()`/`validateForm()` same-apiary guards on all
      three entities stay in place unchanged — this task narrows what the
      widget *offers*, it doesn't replace the guard that protects
      programmatic creation too.
- [ ] Kernel test: building the add form for each of the three entities
      within a specific apiary context, confirm the item/product
      autocomplete only matches entities belonging to that apiary (not
      ones from another apiary the same user can also see).
- [ ] `ddev drush updb -y && ddev drush cr` clean (unlikely to need a
      schema change — widget-level config only).

## Implementation notes
- Key files: `src/Entity/InventoryPurchase.php`,
  `src/Entity/CalendarActionItemRequirement.php`,
  `src/Entity/CalendarActionProductYield.php` (widget settings), possibly
  a new `src/Plugin/EntityReferenceSelection/` handler if declarative
  `handler_settings` filtering isn't expressive enough for "same apiary
  as the entity currently being edited" (the apiary isn't known from the
  field definition alone — it comes from the *other* field on the same
  form, e.g. `calendar_action`/`apiary` — so this may need
  `hook_field_widget_form_alter()` or an AJAX-updated widget rather than
  static `handler_settings`, similar to how core's own
  "dependent autocomplete" patterns work).
- Consider whether this is worth doing generically (a shared trait/helper
  usable by all three fields) given the shape is identical across them —
  judgement call once the actual filtering mechanism is chosen.

## Related
- Project:: [[inventory-and-yield-improvements]]
- Decisions:: [[0027-inventory-tracking-and-depreciation]]
- Commits::
