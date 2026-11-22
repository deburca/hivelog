---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-14
branch: feature/0274-single-hive-reminders
release: "4.4.1"
depends-on:
blocked-by:
---
# Task: One-off reminders for a single hive

## Context
Found by the [[2026-11-14-beekeeper-domain-audit]]. Part of [[beekeeper-centred-simplification-and-gaps]].

A `CalendarAction` is apiary-wide and week-scheduled: scope `hive` means every hive in the
apiary. The follow-ups that fill a beekeeper's notebook are about one hive and one date: check
the queen is released in five days, look for eggs in three weeks, remove the strips on the 20th.
Today they can only go in an inspection's notes, which nothing reads back.

## Acceptance criteria
- [x] A reminder has a hive, a due date, a short text and done / dismissed
- [x] It can be added from the hive page and at the end of the inspection form ("Next visit")
- [x] Due and overdue reminders appear in Needs attention and on the hive page
- [x] Decided: new `HiveReminder` entity (separate from `CalendarAction`)
- [x] Access, hierarchy, delete-registry rows and kernel tests implemented
- [x] AGENTS.md updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation (done)
### New entity: `HiveReminder`
- **Fields**: `hive` (entity_reference→hive), `title` (string, max 255), `notes`
  (string_long), `due_date` (date, required), `status` (list_string: pending/done/dismissed),
  `uid`, `created`, `changed`.
- **Constraint**: `HivelogParentAccess` on `hive` field.
- **Access control**: `HiveReminderAccessControlHandler` extending `EntityAccessControlHandler`.
  View/update/delete scoped to the hive's apiary (beekeeper or owner role). Create requires
  `add hive reminder+administer hivelog`.
- **List builder**: `HiveReminderListBuilder` showing Hive, Title, Due Date, Status columns.
- **Form**: `HiveReminderForm` extending `ContentEntityForm`, with `editTitle()` and
  `deleteTitle()` static title callbacks.
- **Selection**: `HiveReminderHiveSelection` extends `ApiaryScopedSelection` for the hive field
  widget, filtering to active hives only.

### Routes (under `/hivelog/`)
| Route | Path | Permission |
|-------|------|------------|
| `hivelog.hive_reminder.add` | `/hivelog/hive/{hive}/reminder/add` | `add hive reminder+administer hivelog` + `hive.update` |
| `entity.hive_reminder.collection` | `/hivelog/hive-reminders` | `view any hive reminder+administer hivelog` |
| `entity.hive_reminder.edit_form` | `/hivelog/hive-reminder/{hive_reminder}/edit` | `edit any hive reminder+administer hivelog` |
| `entity.hive_reminder.delete_form` | `/hivelog/hive-reminder/{hive_reminder}/delete` | `delete any hive reminder+administer hivelog` |

### Hive page integration
- `buildRemindersSection()` in `HiveController` loads pending reminders (due_date ASC), renders each
  as a row with "Done" / "Dismiss" quick-action buttons, and shows an overdue chip when due date is past.
- Uses `.hivelog-detail-section`, `.hivelog-reminders` classes.
- Cache tags: `hive_reminder_list` + each hive's list cache tag.

### Alert integration
- `collectReminderAlerts()` in `HivelogAlertCollector` produces rows for:
  - **Overdue** (critical, "Overdue N day(s)") — past due date
  - **Today / Due in 1-3 days** (critical, "Today" or "Due in N day(s)")
  - **Due in 4-7 days** (warning, "Due in N day(s)")
- Each alert has `kind: reminder`, `subject: hive`, action link to the edit form with status query param.

### Delete dependency registry
- Row `0274-1`: parent=hive, child=hive_reminder, field=hive, treatment=CASCADE, manage=parent-canonical#reminders
  (added after the first release; until then deleting a hive left its reminders orphaned).

### Next visit on the inspection form
- A new inspection's form has a **Next visit** section (date, optional note) for anyone who may create a
  reminder; saving creates a pending reminder on the hive. Not on edit, so editing never duplicates one.
  A date before today is refused. The first release ticked this box without it existing.

### Nav strip
- Added "Reminders" nav item under Apiaries (records group, weight 3, section hive_reminder).

### Entity hierarchy
- `hive_reminder` → parent field: `hive` in `PARENT_FIELD`.
- Included in `COLLECTION_TYPES`, `SUBJECT_PARAMS`, and `COLLECTION_FALLBACK_TYPES`.

### Permissions added
```yaml
add hive reminder+administer hivelog:
  title: 'Add a hive reminder'
edit any hive reminder+administer hivelog:
  title: 'Edit any hive reminder'
delete any hive reminder+administer hivelog:
  title: 'Delete any hive reminder'
view own hive reminder+administer hivelog:
  title: 'View own hive reminder'
view any hive reminder+administer hivelog:
  title: 'View any hive reminder'
```

### Update hook

### Tests
- `tests/src/Kernel/HiveReminderTest.php`: create/save, status update, allowed values.

## phpstan notes
The full `phpstan analyse` run reports 44 errors across `HiveReminder.php`, `HiveReminderForm.php`, `HiveReminderListBuilder.php`, `HiveReminderTest.php`, and `HivelogAlertCollector.php`. All are `class.notFound`, `interface.notFound`, `trait.notFound`, or `method.notFound` for the new entity class — phpstan cannot resolve it because Drupal core isn't installed in this analysing site. Same pattern that requires excluding Nexus's AI module files via `excludePaths`. This will be handled as part of baseline cleanup in task 0289 (`fix-pre-existing-phpcs-warnings-and-errors`). Do not add temporary suppressions.

## Open questions
1. **"Next visit" on inspection form** — should it auto-create a reminder or pre-fill a field?
   (Decided: pre-fill → user confirms. Implementation left as optional follow-up.)
2. **Dismissed reminders recoverable?** — Yes, status can be changed back to "pending".
3. **CSS for reminders section** — Uses existing `.hivelog-detail-section` + chip classes. No new CSS needed.

## Implementation notes
- Key files: `src/Entity/CalendarAction.php`, `src/HivelogAlertCollector.php`, `src/Form/HiveInspectionForm.php`
- Update hook needed? Yes if an entity or field is added.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: 
- Commits:: 
