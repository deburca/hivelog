---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] An `apiary` reference, written when the record is created, on `hive_inspection`,
      `queen_observation`, `hive_action_log`, `inventory_usage`, `harvest_yield` and `hive_insight`
      (sensor readings and daily rollups to be decided; they follow their device)
- [ ] An update hook fills it in for existing rows from the hive's current apiary; safe to run twice
- [ ] `resolveApiary()` prefers the stamped apiary, so the old apiary's members keep seeing what was
      written under it, and the new apiary's members see the hive's full history
- [ ] Moving a hive needs the beekeeper role in **both** apiaries (the right to add a hive in the target)
- [ ] The move dialog lists the hive's sensors and components and, for each, offers three outcomes:
      move it with the hive, detach it first and then move, or cancel the whole move. Queens go with
      the hive
- [ ] Moving components carries their cost: the target apiary gets a matching item (same name and
      unit, created if missing) and a transfer-in purchase for the moved quantity at the original
      price, and the old apiary gets a matching "transferred out" record, so both apiaries' stock and
      cost reports stay right. A new record type is needed for the transfer
- [ ] The same-apiary rules (`HivelogSameApiary` on component items and action-log calendar actions)
      still hold for new records and ignore the stamped history
- [ ] Hive-scoped sensors that move keep their readings with the device
- [ ] Tests for the move (each outcome), the stamping, the backfill and the old and new apiary's views
- [ ] Update hooks and the API contract and schema reviewed; `AGENTS.md` updated
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0224-permission-granularity-by-role]]
- Commits::
