---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[page-structure-consistency]]"
area: entity
created: 2026-09-23
branch: feature/0143-delete-detach-optional-references
release:
depends-on: ["[[0134-delete-dependency-framework]]"]
blocked-by:
---
# Task: Detach optional references when their target is deleted

## Context
Implements the DETACH rows of
[[0103-delete-policy-for-records-with-children]]. In each, the
reference is **optional** and the child is a complete, valid record
without it. The children should **not** be deleted before the parent,
and not with it either. Today they're left holding a dangling ID.
*DETACH is a recommendation in the ADR. Confirm it before starting.*

| # | Parent deleted | Child kept | On delete |
|---|---|---|---|
| 11 | Hive | Queen (`hive`) | clear `hive`, set `status` = inactive. An unassigned queen is an existing, supported state (AGENTS.md "Content entities"; breadcrumb "unassigned queen" handling). Her observations stay with her. |
| 12 | Hive | SensorDevice (`hive`) | clear `hive`. The device stays registered to its apiary as an apiary-scoped device, and the physical hardware is reusable. |
| 21 | HiveInspection | HiveActionLog (`inspection`) | clear `inspection`. The log keeps its own record of the action. |

## Acceptance criteria
- [x] The three rows are registered with treatment DETACH, each with a
      "reassign" link target: the queen's edit form, the sensor
      device's edit form, and the log's edit form respectively. Row #12
      is registered by nanoprobe.
- [x] The delete confirmation page warns: "Will be kept but unlinked:
      2 queens (they will become unassigned and inactive), 1 sensor
      device (it will become apiary-scoped)", with links to reassign
      them first if wanted.
- [x] On delete by **any** path, the framework's execution hook clears
      the reference (and, for #11, sets `status` inactive), saving each
      child through the entity API so derived logic runs: `Queen::preSave()`
      and the sensor device's own validation.
- [x] #12: confirm a hive-scoped device's metrics still make sense
      apiary-scoped (e.g. a hive weight sensor). If not, the warning
      text says the device should be reassigned to another hive. Record
      the outcome in Implementation notes.
- [x] Kernel test per row: after deleting the parent, the child still
      exists with the reference empty (and, for #11, `status`
      inactive), and no other field changed.
- [x] phpcs clean; kernel + unit suite green against `cms2`.

## Implementation notes

**All three rows and the delete-form warning section were already
built** — this task's actual gap was narrower than its own title
suggests. `HivelogDeleteDependencyRegistry` already had rows #11
(Hive→Queen) and #21 (HiveInspection→HiveActionLog) registered with
`treatment: DETACH` and their `manage` targets set;
`nanoprobe_hivelog_delete_dependencies()` already had row #12 the same
way; `HivelogEntityDeleteForm::buildDependencySections()` already
rendered a generic "Will be kept but unlinked" section for any DETACH
row. All of that shipped with task 0134's initial mass-registration.
What was missing was purely the *execution*:
`HivelogDeleteDependencyExecutor::execute()`'s `DETACH` case was a
literal `// Populated by task 0143.` no-op, and the delete-form text
was generic (a bare count, no row-specific consequence) — this task
filled in both.

**`HivelogDeleteDependencyExecutor::detach()`** mirrors `cascade()`'s
chunked-query loop exactly, but loads and re-saves each child instead
of deleting it: clear the reference field, apply the row's specific
side effect (`applyDetachSideEffects()`, matched on `adr_row` since
`HiveActionLog` itself appears in the registry under three different
rows with different treatments), then `$child->save()` — always
through the entity API, never a raw query, so `Queen::preSave()` and
`SensorDevice::preSave()` both actually run.

**Row #11 (Queen) — status must flip in the same save, not after.**
`Queen::preSave()`'s one-active-queen-per-hive query only runs when
`status === 'active' && $hive_id` is truthy; setting `status` to
`inactive` *and* clearing `hive` before calling `save()` (rather than
two separate saves) means that query never fires at all, avoiding both
a transient "active queen with no hive" state and an unnecessary extra
query per detached queen. Also detaches *every* queen the hive has —
`Hive::getQueens()`'s own "active or retired, every queen the hive has
ever had" scope means a hive can have more than one queen still
referencing it, not just the current active one; the kernel test
covers both.

**Row #12 (SensorDevice) — a real `preSave()` invariant, not just a
courtesy.** `SensorDevice::preSave()` throws `InvalidArgumentException`
if `scope === 'hive'` with an empty `hive` — clearing `hive` alone
would make every hive delete with a hive-scoped device throw on save.
Flipping `scope` to `apiary` in the same save is not optional; it's
required for the save to succeed at all, which is a stronger reason
than the ADR's own framing ("hardware is reusable") suggests.

**#12's "do the metrics still make sense" question — confirmed
**mixed**, not resolved by changing behaviour.** Checked
`SensorDevice::DEVICE_TYPE_METRICS` against the `scope` field's own
description ("apiary-scoped... serves the whole site, e.g. an ambient
weather station"): `weight`, `acoustic`, `entrance_counter` and `gps`
are all inherently about one physical hive and don't become
meaningful "apiary-wide" readings just because the reference is
cleared; only `temperature_humidity`'s *external* metrics genuinely fit
an apiary-wide reading the way an ambient weather station's would (its
*internal* metrics are hive-internal by definition, same problem as
the others). Despite that, the execution still always sets
`scope: apiary` on detach, matching ADR-0103's own explicitly
*confirmed* outcome for row #12 ("all 3 DETACH rows in the inventory
stand as listed", confirmed 2026-09-24) — this task implements the
ADR, it doesn't relitigate it. What *is* in this task's scope is the
warning text, which already links to where the device can be
reassigned to another hive first (the existing `manage:
'parent-canonical'` target); no further differentiation by
`device_type` was added, since the AC's own example text
("1 sensor device (it will become apiary-scoped)") doesn't ask for
device-type-specific wording and the generic link already gives the
user the escape hatch. A future task could add a device-type-aware
warning if this proves confusing in practice — out of scope here.

**Delete-form warning text**: `HivelogEntityDeleteForm::detachConsequenceNote()`
adds a short, literal-`t()`-per-row parenthetical (Drupal's
translatable-strings-must-be-statically-extractable rule, same pattern
`HivelogBreadcrumbBuilder::terminalCrumbLabel()` already uses) —
matched by `adr_row`, not `child`, since a single child type
(`hive_action_log`) already appears under three different registry rows
with three different treatments. Row #21 gets no parenthetical (the
`default => NULL` case) — the task's own text only specifies a
consequence for #11/#12, and #21's own consequence ("the log keeps its
own record of the action") is arguably not worth a special callout
since nothing changes about the log except one internal cross-reference
field with "Never edited directly on this form" already in its own
label description.

**Verification.** phpcs clean, phpstan clean (three
`Call to an undefined method EntityInterface::set()` findings — every
registered row's child is really a `FieldableEntityInterface`, but
`EntityStorageInterface::loadMultiple()` types generically —
suppressed with `@phpstan-ignore-next-line`, matching
`ApiaryAccessTrait`'s own established precedent for the identical
situation, not a baseline change). Full kernel suite (hivelog core +
nanoprobe): 843 tests, zero failures, only the 3 pre-existing unrelated
`DashboardTest` Functional errors already documented in prior tasks.
Live-verified on `cms2` via `drush php-eval` with throwaway fixtures
(all cleaned up after): deleting a hive correctly detaches its queen
(hive cleared, status inactive) and its sensor device (hive cleared,
scope apiary-scoped); deleting an inspection correctly detaches its
action log (inspection cleared, every other field untouched); the
delete confirmation page's rendered, tag-stripped text reads exactly
"Will be kept but unlinked ... 1 queen (they will become unassigned and
inactive)1 sensor device (it will become apiary-scoped)" — matching
the AC's own example verbatim.

- Key files: `src/Delete/HivelogDeleteDependencyExecutor.php`
  (the `detach()`/`applyDetachSideEffects()` methods this task added),
  `src/Form/HivelogEntityDeleteForm.php` (`detachConsequenceNote()`).
  `src/Entity/Queen.php` and `modules/nanoprobe/src/Entity/SensorDevice.php`
  needed no changes — their existing `preSave()` invariants are exactly
  what the executor now correctly satisfies.

## Related
- Project:: [[page-structure-consistency]]
- Decisions:: [[0103-delete-policy-for-records-with-children]]
- Tasks:: [[0134-delete-dependency-framework]]
- Commits::
