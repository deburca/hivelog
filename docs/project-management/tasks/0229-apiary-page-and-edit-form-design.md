---
type: task
tags: [hivelog/task]
status: in-progress
priority: medium
project: "[[shared-apiaries-and-team-roles]]"
area: theme
created: 2026-10-22
branch: feature/0229-apiary-page-design
release:
depends-on: ["[[0223-apiary-membership-entity-and-ui]]"]
blocked-by:
---
# Task: Apiary page and edit form design

## Context
Follow-up to [[0223-apiary-membership-entity-and-ui]]. Reviewing the apiary page and its
edit form (desktop and phone screenshots, 2026-10-22) showed a flat wall of fields on the
edit form, the Members section sitting above the Save button, a map that opens on central
Africa, a navigation strip that takes about a third of a phone's first screen, hive rows
that say little, and overdue calendar items that look like any other row.

The pane that Claude drives cannot load the site's stylesheets, so anything visual is
checked from screenshots the owner sends.

## Acceptance criteria
- [ ] Edit form is grouped into Details (name, location, notes), Map and Settings
      (visibility, AI insights with its disclosure, owner)
- [ ] Members section comes after the Save / Cancel buttons
- [ ] Map opens on Denmark when the apiary has no location yet
- [ ] Navigation strip is collapsed behind a menu button on narrow screens
- [ ] Hive rows show more than breed / temperament / status
- [ ] Overdue calendar items stand out from upcoming ones
- [ ] phpcs clean; phpstan clean; kernel tests pass (`--group hivelog`)
- [ ] `AGENTS.md` updated if conventions changed

## Implementation notes
- Edit form grouping uses `details` elements with `#group`, so no field is moved or
  re-parented. The `collective` module's AI-insights disclosure joins the Settings group
  and stays visible: it is the consent text for that checkbox.
- The Leaflet widget reads its start position from `map_position`, not the `center` key
  the field definition used to set, which is why the map opened on 0,0. A site that has
  saved its own form display for Apiary keeps that display's settings.

## Related
- Project:: [[shared-apiaries-and-team-roles]]
- Follows:: [[0223-apiary-membership-entity-and-ui]]
- Commits::
