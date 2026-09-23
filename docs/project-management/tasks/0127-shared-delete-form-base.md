---
type: task
tags: [hivelog/task]
status: todo
priority: medium
project: "[[page-structure-consistency]]"
area: routing
created: 2026-09-23
branch: feature/0127-shared-delete-form-base
release:
depends-on: ["[[0116-breadcrumb-builder-parent-map-refactor]]"]
blocked-by:
---
# Task: One delete-form base with a single cancel / redirect rule

## Context
From the page-structure review of 2026-09-23. There are 16 delete-form
classes (838 lines), 13 in `src/Form/` plus one each in `collective`,
`nexus` and `nanoprobe`. Each hand-writes `getQuestion()`,
`getCancelUrl()` and a `submitForm()` that deletes, sets a message and
redirects. Where they send the user is inconsistent:

| Form | Cancel → | After delete → |
|---|---|---|
| Apiary | apiary | apiary collection |
| Hive, HiveInspection, Queen, QueenObservation | the entity | parent (hive / apiary / queen) |
| CalendarAction, Hive- / ApiaryActionLog, Requirement, Yield | **parent** | parent |
| Product | **apiary** | apiary |
| InventoryItem, InventoryPurchase | **collection** | **collection** (though apiary-scoped, unlike Product) |
| SensorDevice, ApiClient, AiProviderConfig | core default | collection |

Fallbacks when the parent is missing also vary. HiveInspection with no
hive redirects to the *apiary* collection, for example.

## Acceptance criteria
- [ ] One rule, written in the base class docblock:
      - **Cancel** returns to the entity's canonical page when it has
        one, otherwise to its parent's canonical page (requirement /
        yield → calendar action). Cancel should undo the click that
        brought the user here.
      - **After delete**: go to the parent's canonical page, else the
        entity type's collection, else the dashboard.
      - A `?destination=` query (already used by e.g. the Apiary page's
        calendar rows) wins over both, as core does.
- [ ] `HivelogEntityDeleteForm extends ContentEntityDeleteForm`
      implements the rule, resolving the parent from the same parent map
      [[0116-breadcrumb-builder-parent-map-refactor]] introduces. Extract
      that map into a small shared class or service so the breadcrumb
      builder and this base read one definition.
- [ ] `getQuestion()` / the status message come from the entity type's
      singular label ("Are you sure you want to delete hive %name?",
      "Hive %name has been deleted."), so most subclasses become empty.
      Wire them directly in the entity attribute's `delete` form handler
      and delete the empty classes.
- [ ] Classes with real extra logic keep only that logic:
      `InventoryItemDeleteForm` / `ProductDeleteForm` (the "N historical
      records reference this" description), `HiveDeleteForm` /
      `QueenDeleteForm` if they have cascade specifics, and the
      submodule forms if needed.
- [ ] Kernel test per entity type (data provider): cancel URL and
      post-delete redirect match the rule, including the
      missing-parent fallback.
- [ ] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes
- Behaviour change for users: InventoryItem / InventoryPurchase deletes
  will now land on the apiary page instead of the global list, matching
  Product. Worth a line in the release note.
- The submodule entity types live in modules `hivelog` doesn't depend
  on. The base class must work from entity type IDs and field names
  only.
- Key files: `src/Form/*DeleteForm.php`, the three submodule
  `*DeleteForm.php`, entity attributes' `form` handler maps.

## Related
- Project:: [[page-structure-consistency]]
- Tasks:: [[0116-breadcrumb-builder-parent-map-refactor]] (the shared
  parent map), [[0129-cancel-link-on-add-edit-forms]] (the same
  "where does Cancel go" rule for add / edit forms)
- Commits::
