---
type: decision
tags: [hivelog/decision]
status: accepted
date: 2026-11-22
supersedes:
---

# ADR-0111: One place to record what was seen of the queen, and which temperament is true

## Status
accepted, 2026-11-22, by the maintainer, as proposed, with the three questions below
answered as recommended. Written for task [[0277-queen-observation-and-inspection-overlap-adr]]
(from the [[2026-11-14-beekeeper-domain-audit]]).

## Context
What a beekeeper sees of the queen can be written in two entities, and one of its facts
in four.

| Fact | `HiveInspection` (per hive visit) | `QueenObservation` (per queen) | `Hive` | `Queen` |
|---|---|---|---|---|
| Queen seen / laying | `queen_seen`, `eggs_seen` (booleans) | `active` ("actively laying / moving") | | |
| Queen cells | `queen_cells` | | | |
| Queen health | | `health` (excellent, good, fair, poor) | | |
| Temperament | `temperament` | `temperament` | `temperament` | `temperament` |

Findings from the code:

1. **The overlap is real but small.** `queen_seen`/`eggs_seen` and `active` all say "she
   is there and laying". Health is only on the observation, queen cells only on the
   inspection. A beekeeper who sees the queen during an inspection chooses between
   opening a second form (the hive page's **Add Observation** button, a separate route and
   page) and not recording it.
2. **They have different parents, for a reason.** An inspection belongs to a hive. An
   observation belongs to a queen, and the hive page gathers observations from every
   queen the hive has ever had, so history survives a requeening. An observation can
   also be made without inspecting the hive.
3. **Temperament has no single truth.** Four fields hold the same idea with nothing
   keeping them in step. The Hive and Queen values are hand-edited on their own forms and
   are what the hive page's Temperament tile and the hive and queen list filters read.
   Nothing reads the inspection's or the observation's value for any summary.
4. **Insights read the inspection, not the observation.** `collective`'s context
   (`HiveContextBuilder`) sends the recent inspections' `queen_seen` and `queen_cells`
   (among others) to the model. It sends no temperament and no queen observation. So the
   inspection's queen fields are the ones with a reader; `active` and `health` have none.
5. **The observation is a published API resource.** `queen_observation` is on the v1
   allow-list and in `tests/fixtures/api-v1-contract.json` with all its fields, and the
   field-app role may add, edit and delete its own (tasks 0201, 0220). The Vinculum app
   writes it. The `temperament` fields on `hive`, `queen`, `hive_inspection` and
   `queen_observation` are also in the contract. A rename or removal needs an alias or a
   new API version (ADR-0107).

Options in the task:

- **(a)** The inspection form writes an optional queen observation in the same save.
- **(b)** Observations become a section of the inspection and the entity is retired.
- **(c)** Keep both and document when each applies.

**(b) is out.** It removes a published API resource (a major version and a migration of
every observation), it throws away the queen-scoped history that survives requeening, and
it forces a hive visit to record a queen-only event. The overlap it would remove is three
booleans.

## Decision
**Keep both entities, remove the double entry with (a), and write down the rule from (c).**

1. **When each applies.** An *inspection* is a visit to a hive: what the colony was
   like. A *queen observation* is a note about one queen: her health and whether she was
   laying. Record an observation on its own when the queen was looked at outside an
   inspection (marking, checking a new queen). Record it from the inspection form when she
   was looked at during one.
2. **The inspection form offers a Queen observation section** (new inspections only,
   like the Next visit reminder of task 0274). It has Health, and Actively laying
   pre-filled from Eggs seen. When filled in it creates a `QueenObservation` for the hive's
   active queen with the inspection's date and temperament, validated like any other
   write. The section is not offered if the hive has no active queen or the user may not
   add an observation. An observation carries no link back to its inspection, so editing
   an inspection never rewrites it.
3. **Temperament is recorded per visit** (on the inspection or the observation, as
   now), and the **hive's and queen's temperament become a cache of the latest record**:
   saving an inspection sets its hive's `temperament`; saving an observation sets its
   queen's. The stored fields stay, because the Temperament tile, both filters and the v1
   contract read them. The Hive and Queen forms keep the field as a starting value for a
   record with no visits yet, with a description saying it is updated from the latest
   inspection or observation. The most recent write wins, whichever form made it.
4. **API contract:** unchanged. No field is renamed or removed, `queen_observation` stays
   exposed, and the app keeps working. The inspection-form section is web-only. The
   cache in point 3 runs on entity save, so it applies to writes through the API too.

## Consequences
- Positive: one form records a visit and what was seen of the queen; no migration,
  no API version change; insights keep the inspection fields they read; the Hive and Queen temperament stop drifting from what was
  recorded; queen history across requeening is kept.
- Negative / trade-offs: the same words are still stored in more than one place (the
  cache is deliberate); `active` and `eggs_seen` can still disagree if edited separately;
  a hive whose temperament was hand-edited will take the next inspection's value, which a
  beekeeper may not expect (the form description says so); an observation made from an
  inspection is not linked to it.
- Follow-up tasks: [[0294-inspection-form-queen-observation-section]],
  [[0295-temperament-cache-on-inspection-and-observation-save]],
  [[0296-document-when-inspection-and-queen-observation-apply]]. None needs an update
  hook, because no field is added.

## Questions, and the answers
1. *Is a cache of the latest record the right meaning for Hive and Queen temperament, or
   should the fields leave the forms?* A cache. The fields stay on the forms as a starting
   value.
2. *Should an observation made from an inspection link back to it?* No. It stays an
   independent queen record; no `inspection` field.
3. *Which fields does the inspection form's section offer?* Health and Actively laying
   (pre-filled from Eggs seen). Notes and photos stay on the separate observation form.
