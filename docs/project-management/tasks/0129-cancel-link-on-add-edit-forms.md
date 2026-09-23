---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0129-cancel-link-on-add-edit-forms
release:
depends-on:
blocked-by:
---
# Task: Cancel link on every add / edit form

## Context
From the page-structure review of 2026-09-23. None of the 16 add / edit
entity forms (13 in `src/Form/`, plus `ApiClientForm`,
`AiProviderConfigForm`, `SensorDeviceForm`) offers a Cancel action.
Delete confirmation forms do, via core's `ConfirmFormBase`. Combined
with the breadcrumb bug in [[0117-breadcrumb-terminal-crumb-on-form-pages]]
(the crumb you'd go back to is plain text), the only way off a form
without saving is the browser's back button or the nav strip.

## Acceptance criteria
- [ ] Every add / edit entity form has a **Cancel** link in
      `form-actions`, after Save (and after Delete on edit forms),
      rendered as a `hivelog:button` in the default variant, so it is
      styled by `hivelog.buttons.css` (`.hivelog-entity-form
      .form-actions` is already a registered context wrapper).
- [ ] Cancel target rule, same spirit as
      [[0127-shared-delete-form-base]]:
      - `?destination=` in the request wins.
      - Edit form: the entity's canonical page.
      - Scoped add form: the parent's canonical page (e.g.
        `/hivelog/apiary/43/hive/add` → apiary 43).
      - Site-wide add form: the collection.
- [ ] Implemented once, e.g. a `HivelogEntityFormTrait::addCancelAction()`
      or a shared `HivelogContentEntityForm` base in `hivelog` core that
      submodule forms can use. Not 16 copies.
- [ ] Kernel test (data provider over the form handlers): the cancel link
      exists and points at the expected URL for an edit form, a scoped
      add form and a site-wide add form.
- [ ] Visual check on `cms2` for one form per module.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- The scoped add routes pre-populate the parent via controller methods
  (`HiveController::addForm()` etc.), so the parent is available on the
  form entity (`$entity->get('apiary')->entity`) when building actions.
  Reuse the parent map from
  [[0116-breadcrumb-builder-parent-map-refactor]] if it has been
  extracted by then (see [[0127-shared-delete-form-base]]).
- Key files: `src/Form/*Form.php` (non-delete, non-filter), the three
  submodule entity forms.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0012-action-button-design-system]]
- Tasks:: [[0117-breadcrumb-terminal-crumb-on-form-pages]],
  [[0127-shared-delete-form-base]]
- Commits::
