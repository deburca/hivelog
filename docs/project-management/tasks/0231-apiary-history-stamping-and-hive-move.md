---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: entity
created: 2026-10-25
branch: feature/0231-apiary-history-stamping-and-hive-move
release:
depends-on: ["[[0224-permission-granularity-by-role]]"]
blocked-by:
---
# Task: Apiary history stamping and moving a hive between apiaries

## Context
Split out of [[0224-permission-granularity-by-role]]. Principle (decided 2026-10-25): **history is
never rewritten.** When a hive moves from apiary A to apiary B, every record already written keeps
the apiary, hive and user it was written under; records written after the move carry apiary B. Both
apiaries' histories, and the hive's own history, stay intact.

Today inspections, queen observations, hive action logs, inventory usage, harvest yields and hive
insights carry only a hive, so after a move they would follow the hive to the new apiary.

## Acceptance criteria
- [x] An `apiary` reference, written when the record is created, on `hive_inspection`,
      `queen_observation` and `hive_action_log` (`hive_insight` already had one). Inventory usage
      and harvest yield need none: they resolve through their item and product, whose apiary a hive
      move never changes. Sensor readings follow their device
- [x] An update hook (`hivelog_update_10036`) fills it in for existing rows from the hive's current
      apiary; safe to run twice
- [x] `resolveApiary()` prefers the stamped apiary for writes; reading also allows the hive's
      current apiary, so the old apiary keeps seeing what was written under it and the new apiary
      sees the hive's full history. Records written under another apiary are not edited from the new one
- [x] Moving a hive needs the beekeeper role in **both** apiaries (`update` on the hive, `write` on the
      target)
- [x] The move dialog lists the hive's components and (through a hook) sensors and, for each, offers
      move with the hive, detach first, or cancel the whole move. Queens go with the hive
- [x] Moving components carries their cost: a matching item and a transfer-in purchase at the original
      price in the target, and a matching negative purchase in the source, both flagged `transfer`
      (`hivelog_update_10037`) and not editable or deletable
- [x] The same-apiary rules still hold for new records and judge a log by its stamp
- [x] Hive-scoped sensors that move keep their readings with the device
- [x] Tests for each outcome, the stamping, the backfill and the old and new apiary's views
- [x] API contract reviewed (the three exposed types gain a read-only `apiary` relationship;
      `api_version` stays 1); `AGENTS.md` updated
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Follow-up (2026-10-26)
Checked after 3.0.0 shipped: the apiary cost and yield report and the dashboard inspection count found a hive's
records through the hive's apiary, so a moved hive took its history out of the old apiary's figures and into the
new one's (a real bug; fixed, with tests, by counting on the stamp). The move page's breadcrumb ended in the
route's long title (now "Move"), and the sensor part of the dialog was untranslated (Danish added). The calendar
checklist, alerts and hive lists are current-state views and follow the hive on purpose.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]]
- Commits:: merged for 3.0.0
