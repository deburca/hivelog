---
type: task
tags: [hivelog/task]
status: done
priority: low
project: "[[ios-field-app]]"
area: entity
created: 2026-11-25
branch: feature/0297-consistent-field-labels
release:
depends-on: []
blocked-by:
---
# Task: Consistent field labels: sentence case, one meaning per word, units shown

## Context
Raised by the Vinculum app's UI review of 2026-11-25 (Vinculum task 0081). Part of [[ios-field-app]].

The app builds its forms from `/api/v1/schema` and shows each field's label exactly as HiveLog gives it. The site's own forms show the same labels. Read side by side in one form, they disagree with each other. These are HiveLog's words, so the app will not rename them. Each label below is checked against the captured 4.5 schema.

- **Mixed capitalisation in one form.** Most labels are title case: "Hive Name", "Hive Type", "Hive Material", "Inspection Date", "Queen Seen", "Queen Cells", "Eggs Seen", "Brood Pattern", "Honey Stores", "Pollen Stores", "Varroa Check", "Varroa Count", "Disease Signs", "Feed Type", "Action Taken", "Queen Year", "Queen Colour", "Purchase Cost", "Purchase Date", "Introduction Date", "Observation Date", "Week Start", "Week End", "Week Completed", "Calendar Action" and "Linked Inspection". Others are sentence case: "Frames of bees", "Frames of brood", "External check (before opening)" and "AI insights enabled". The app's own headings ("The colony", "Queen and brood", "What you did") are sentence case.
- **"Active" means two things.** `queen_observation.active` is "Was the queen observed actively laying / moving on the comb?". `queen.status` has the options Active / Inactive, meaning in service or retired, and `hive.status` uses Active the same way. On the queen's page the word stands for both.
- **"Queen ID" is the label of `queen.name`**, described as "e.g. Q-2026-001". The app treats this field as the queen's name ("Queen Maud").
- **"Pictures"** is the label of every `images` field (hive, inspection, queen observation); two of their descriptions already say "Photos". The app says photo throughout.
- **"Weight" has no unit** in its label, though its description says kilograms, so the form shows a bare number.

## Acceptance criteria
- [x] Field labels on `hive`, `hive_inspection`, `queen`, `queen_observation`, `calendar_action`, `hive_action_log` and `apiary_action_log` are in sentence case ("Hive name", "Inspection date", "Queen seen", …), with proper nouns kept ("Varroa", "European Foulbrood (EFB)")
- [x] `queen_observation.active` is labelled for what it records, e.g. "Laying or moving on the comb", so that "Active" means in service only
- [x] A decision, recorded in this vault, on whether `queen.name` is a name or an identifier. If it is a name: label "Name" and the description's example a name. If it is an identifier: keep "Queen ID", and tell the app so its fallback words for an unnamed queen can be checked
- [x] `images` fields are labelled "Photos"
- [x] A field with a unit shows it in its label: "Weight (kg)"
- [x] `translations/hivelog.da.po` is updated for the changed source strings (e.g. "Queen ID" → "Dronning-id", "Pictures" → "Billeder" are there now)
- [x] `/api/v1/schema` returns the new labels. Its shape, `api_version` and field keys are unchanged
- [x] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)

## Implementation notes
- Key files: `src/Entity/Hive.php`, `src/Entity/HiveInspection.php`, `src/Entity/Queen.php`, `src/Entity/QueenObservation.php`, the calendar and action-log entities, `src/Controller/HiveController.php` (a "Pictures" heading of its own, line 1387), `translations/hivelog.da.po`.
- Update hook needed? Probably not: base-field labels come from code. Check whether any of these are configurable fields with a stored label.
- The API's field keys must not change. The app matches on keys, never on labels.
- Once this ships, the Vinculum app re-captures `api-schema-*.json`. No app code change is expected beyond tests that quote a label.

## Outcome
- Sentence case on every field label of the seven types; list headers and filters that name one of these fields use its label too (`Week completed`, `Calendar action`, `Queen seen`).
- `queen_observation.active` is "Laying or moving on the comb", also in the observation tables on the hive and queen pages, the observation list and its filter.
- `queen.name` is a name (ADR-0112): label "Name", description "A name for this queen, e.g. Maud."
- `images` fields are "Photos" (and the hive page's photo heading); `hive_inspection.weight` is "Weight (kg)".
- No configurable fields among them, so no update hook. Keys, shape and `api_version` unchanged; `HivelogApiSchemaTest::testLabelsAreConsistent()` checks the new labels.
- Left alone, out of scope: `inventory_purchase.purchase_date` ("Purchase Date") and the `Calendar Action` labels on the item-requirement and product-yield entities.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0112-a-queen-has-a-name-not-an-identifier]]
- Commits::
