---
type: task
tags: [hivelog/task]
status: review
priority: high
project: "[[ios-field-app]]"
area: entity
created: 2026-10-04
branch: feature/0200-move-form-validation-to-entity-constraints
release:
depends-on:
blocked-by:
---
# Task: Move form-only validation into entity constraints

## Context
The two-layer convention (`preSave()` throws, `validateForm()` gives the
friendly error) leaves any non-form caller — an API, a migration, a
script — with a 500 instead of a validation error.
[[0107-mobile-client-api-and-native-ios-app]] §2 replaces the form layer
with entity `Constraint` plugins, so forms and the API share one rule.
Useful to core whether or not the app ships.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] Inventory every `validateForm()` across core and submodules that
      enforces a data invariant (vs. pure UI concerns); list them here
- [x] Each invariant becomes a field or entity `Constraint` plugin; at
      minimum `HiveComponent` (same apiary; quantity ≤ available, own row
      excluded on edit) and `CalendarActionItemRequirement` /
      `CalendarActionProductYield` (same apiary)
- [x] Forms rely on `ContentEntityForm`'s constraint validation instead of
      repeating the rule; user-facing messages unchanged
- [x] `preSave()` throws kept as the backstop
- [x] Kernel tests: `$entity->validate()` reports each violation
- [x] AGENTS.md's "two-layer convention" wording updated (HiveComponent
      section and wherever else it's described)
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes

### Inventory: every `validateForm()` in core and submodules
Nine forms override it. Each rule below was a data invariant, not a UI
concern, and is now a constraint on the field it reports on.

| Form | Rule | Constraint | On field |
|---|---|---|---|
| `HiveComponentForm` | item in the hive's apiary | `HivelogSameApiary` | `item` |
| `HiveComponentForm` | quantity <= available (own row excluded on edit) | `HivelogHiveComponentQuantity` | `quantity` |
| `CalendarActionItemRequirementForm` | item in the action's apiary | `HivelogSameApiary` | `item` |
| `CalendarActionProductYieldForm` | product in the action's apiary | `HivelogSameApiary` | `product` |
| `InventoryItemForm` | durable needs a useful life | `HivelogConditionalField` | `useful_life_years` |
| `InventoryPurchaseForm` | item in the purchase's apiary | `HivelogSameApiary` | `item` |
| `InventoryPurchaseForm` | disposal only for a durable item | `HivelogDurableItemDisposal` | `disposal_date` |
| `InventoryPurchaseForm` | disposal not before purchase | `HivelogNotBefore` | `disposal_date` |
| `CalendarActionForm` | end week >= start week | `HivelogNotBefore` | `week_end` |
| `HiveInspectionForm` | feed type when fed; varroa count when checked | `HivelogConditionalField` x2 | `feed_type`, `varroa_count` |
| `SensorDeviceForm` (nanoprobe) | hive required for hive scope, empty for apiary scope | `SensorDeviceHiveScope` | `hive` |
| `AiProviderConfigForm` (nexus) | key / provider / endpoint required by mode | `HivelogConditionalField` x3 | `key`, `provider`, `endpoint_url` |

What stays in a form is genuinely UI: `HiveInspectionForm`'s
`normaliseDependentFields()` (clearing a hidden dependent value so a user is
not punished for something the UI hid).

**Left `preSave()`-only, on purpose:** `SensorReading`, `HiveInsight`,
`InventoryUsage`, `HarvestYield` are machine-written or written as a side effect
of another form and are neither form-edited nor exposed to the API;
`AiProviderConfig`'s "mode is recognised" check is already covered by the
field's allowed values.

### Parent-access constraint (the security half, from spike 0198)
`HivelogParentAccess` applies the scoped add routes' `_entity_access`
(`update` on the parent) to every caller. It is on the primary parent
reference of all 13 child types. Without it, a generic API could create in,
or move records into, another beekeeper's apiary or hive. Design points:
- It checks `update`, matching the routes, not `view`. The spike's experiment
  used `view`, which would have let a stranger write into a publicly viewable
  hive.
- Only a **new or changed** reference is checked (the stored value is loaded
  with `loadUnchanged()`), so an unrelated edit does not fail because the
  parent's access later changed.
- The message names the kind of record, never the record, so it cannot be used
  to probe for one.

### Decisions and deviations
- **Wording is unchanged** from the form messages, passed as each call site's
  `message` option. Rules are field constraints rather than entity constraints
  because a violation then lands on the field's own form element, as before.
- **Two additions beyond the form rules:** a log's `calendar_action` must
  belong to the log's hive/apiary (`HivelogSameApiary` on `HiveActionLog` and
  `ApiaryActionLog`). Nothing enforced this before, and the spike showed a
  record could be pointed at another apiary's action. This is a new rule, not
  a move; existing rows are not re-validated.
- **A cross-apiary component reports one error**, not also an out-of-stock one
  (availability is apiary-wide, so it would be a second, confusing message).
- **One constraint per plugin ID per field** (`addConstraint()` is keyed by
  ID). Two `HivelogConditionalField` rules on one field would silently overwrite
  each other, which is why `SensorDevice.hive` got its own combined plugin.
- **Violations on fields not in a form's display are dropped by core**
  (`ContentEntityForm::validateForm()`), so the parent-access rule is
  enforced for the web UI only where the parent field is an editable widget;
  the scoped routes still carry the rest. The API has no such filter.
- **Test harness change:** `ScopedEntityFormRoundTripTest`'s disposal-date case
  built an unsubmitted form, whose datetime value is a raw element array that a
  real submit would have converted. The test now sets the dates as a submit
  would (`DrupalDateTime`), and the validator ignores a non-scalar value rather
  than comparing it. No assertion was weakened.
- `phpstan-baseline.neon` shrank: the removed form code took six findings with
  it (the ratchet reported them as unmatched until the entries were trimmed).

### Not done
- `HiveActionLog.inspection` is not checked to belong to the log's hive.
  Unchanged from before; noted for 0203.
- No JSON:API request was made: the API submodule does not exist yet (0201).
  The constraint behaviour is proven through `validate()`, which is the layer
  JSON:API calls, and by the spike's earlier request-level run.

### Verification
New `EntityConstraintsTest` (13 tests, 373 assertions): the parent-access
matrix across all 13 child types (own parent allowed, a foreign one refused with
exactly one violation, admin allowed, re-parenting refused, unchanged parent not
re-checked), each rule above, and the `preSave()` backstop. Two submodule tests
cover the sensor-device and AI-provider rules. phpcs and phpstan clean.

**Full suite: 1,221 tests / 20,429 assertions** (1,206 before + 15 new), run
as eleven chunks covering every test file once. The first pass had 17
failures, all from the change itself and none a bug in it:
- 12 tests (`ApiaryActionLog`, `CalendarAction`, `CalendarActionItemRequirement`,
  `CalendarActionProductYield`, `HiveActionLog`) ran `validate()` as an
  anonymous user, which the parent-access constraint now (correctly) refuses.
  They now run as the superuser, as `HiveComponentTest` already did.
- 5 `HiveInspectionTest` cases invoked the private
  `HiveInspectionForm::validateDependentFields()` by reflection, which is gone.
  They now submit through the form's real `validateForm()` and assert on the
  `feed_type` / `varroa_count` error keys (the unsubmitted-form harness raises
  unrelated errors on other fields, so `hasAnyErrors()` is no longer a clean
  signal). The "rule fires" assertions are unchanged.
The six affected files plus `EntityConstraintsTest` were then re-run green,
and Functional (10 tests, the real web add/edit flows) passed in the first pass.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
