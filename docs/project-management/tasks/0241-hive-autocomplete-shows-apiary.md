---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-29
branch: ui/error-scenes-and-lists
release: 3.4.0
depends-on: ["[[0237-ui-polish-under-beeswax]]"]
blocked-by:
---
# Task: A hive autocomplete shows the hive's apiary

## Context
A hive field offered "VV-01 (1)". Hive names are only unique inside their apiary, so with two apiaries "VV-01" is two
hives and the dropdown gave no way to tell them apart.

## Acceptance criteria
- [x] The dropdown rows, and the text a pick leaves in the field, read "VV-01 · Vand Værk (1)": name, apiary, id
- [x] A saved reference shows the same text when its form opens, not the bare "VV-01 (1)"
- [x] Done once, for every hive autocomplete (core's and a submodule's), not field by field
- [x] Which hives are offered is unchanged; only the label is
- [x] Any other reference field is untouched
- [x] Kernel test `HiveReferenceAutocompleteTest`

## Outcome (2026-10-29)
- `HiveWithApiarySelection` (`default:hivelog_hive_with_apiary`) builds the labels; `HivelogHiveReference::alterWidget()`
  puts it, and a value callback for the saved value, on any `entity_reference_autocomplete` widget whose target is a hive,
  from `hivelog_field_widget_single_element_entity_reference_autocomplete_form_alter()`.
- The "(id)" stays: Drupal resolves the submitted text to a record by it, so it cannot be dropped without a custom element.
- Checked on hivelog.ddev.site: the autocomplete endpoint answers "VV-01 · Vand Værk (1)", "Hive 1 · Test Apiary (16)".

## Related
- Follows:: [[0237-ui-polish-under-beeswax]]
