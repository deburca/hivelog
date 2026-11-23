---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[beekeeper-centred-simplification-and-gaps]]"
area: entity
created: 2026-11-22
branch: feature/0295-temperament-cache-on-inspection-and-observation-save
release:
depends-on: []
blocked-by:
---
# Task: Hive and queen temperament follow the latest inspection and observation

## Context
From [[0111-one-place-to-record-the-queen]] (task [[0277-queen-observation-and-inspection-overlap-adr]]). Part of [[beekeeper-centred-simplification-and-gaps]].

Temperament is stored on `Hive`, `Queen`, `HiveInspection` and `QueenObservation` with nothing keeping them in step, and the hive page's Temperament tile and the hive and queen filters read only the Hive and Queen fields. They become a cache of the latest record.

## Acceptance criteria
- [x] Saving a new or changed inspection that has a temperament sets its hive's `temperament` when it is the hive's latest inspection by date
- [x] Saving an observation likewise sets its queen's `temperament` when it is her latest observation by date
- [x] Runs on entity save, so web, API and scripts all behave the same; a record with no temperament changes nothing
- [x] The Hive and Queen forms' field descriptions say the value is updated from the latest inspection or observation
- [x] The fields stay in the v1 contract, unchanged (`HivelogApiContractTest` passes)
- [x] Kernel tests: latest wins, an older back-dated record does not overwrite, empty temperament leaves it, the observation form and the inspection form both trigger it
- [x] `AGENTS.md` updated if entity types, routes, services, hooks or conventions changed
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/HiveInspection.php`, `src/Entity/QueenObservation.php` (`postSave()`), `src/Form/HiveForm.php`, `src/Form/QueenForm.php`
- Update hook needed? No (optionally a one-off backfill is out of scope).
- Open for the implementer: a direct update that does not bump the hive's `changed` time, as `hivelog_update_10036` did.

## Related
- Project:: [[beekeeper-centred-simplification-and-gaps]]
- Decisions:: [[0111-one-place-to-record-the-queen]]
- Commits::
