---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[hivelog-visual-identity]]"
area: ui
created: 2026-10-27
branch: ui/audit-fixes-1
release: 3.1.0
depends-on: ["[[0061-beeswax-hivelog-skin]]", "[[0128-page-heading-hierarchy]]"]
blocked-by:
---
# Task: UI polish under the beeswax theme

## Context
Screenshots of HiveLog under beeswax (the local hvg site) showed plain links where buttons belong, three field
looks in one form, 60px page titles, repeated "Field / Value" headers, wrapping dates and button clusters, and a
header that said "HiveLog" three times. The theme side is beeswax 1.1.0 and 1.1.1; this task is the module side. Pull
requests 165 and 166.

## Acceptance criteria
- [x] "Add a member", "Move to another apiary" and "Manage members" are buttons; Delete is red with a Cancel button
- [x] The dashboard's title is "Dashboard" and the page header does not repeat the brand
- [x] Detail tables share one label column, hide their Field / Value header from sight and keep it for screen readers
- [x] A date field's label and description look like every other field's
- [x] A date and the Edit / Delete cluster never wrap in a table cell
- [x] One Operations column everywhere: a visually hidden header, and a header strip spanning list tables
- [x] The queen's colour reads "Blue", not "Blue (years ending 0, 5)", on the hive, queen and list pages
- [x] The anonymous inspector / author is plain text, not a link to a page that is not there
- [x] Tests for the above; `AGENTS.md` records the removed masthead classes and the new button contexts

## Outcome (2026-10-27)
- Button contexts `.hivelog-inline-actions`, `.hivelog-confirm-form` and `.hivelog-page-actions` (see `AGENTS.md`).
- `HivelogDetailPageTrait::buildReference()` renders every reference field; `Queen::getColourName()` is the one place
  the marking years are stripped from the colour label.
- **Removed:** `.hivelog-masthead__mark` and `.hivelog-masthead__word`. The dashboard title is plain text now; a
  theme that styled those classes simply has nothing to style.
- **Not checked:** the submodule lists (sensors, API clients, AI providers; they inherit the Operations header and the
  nowrap rules through `HivelogListBuilder`), phone width and dark mode. Covered by the submodules' own kernel tests.
- Left for the next audit: the footer, the breadcrumb's "…", the header "HiveLog" item that duplicates the
  strip's "Dashboard", and the account pages (profile and edit).

## Related
- Project:: [[hivelog-visual-identity]]
- Follows:: [[0061-beeswax-hivelog-skin]], [[0128-page-heading-hierarchy]]
