---
type: task
tags: [hivelog/task]
status: in-progress
priority: medium
project: "[[mobile-ux-improvements]]"
area: tests
created: 2026-06-17
branch: feature/0009-mobile-qa-and-tap-targets
release: 1.4.0
depends-on: ["[[0005-responsive-entity-list-tables]]", "[[0006-responsive-detail-tables]]", "[[0007-responsive-filter-form-and-heading]]", "[[0008-responsive-map-and-image-grid]]"]
---
# Task: Mobile QA & tap targets

## Context
Closing/QA task for [[mobile-ux-improvements]]: verify the per-page fixes hold
together across real device widths, and that interactive elements meet
touch-target guidance. The `hivelog:button-group` currently forces
`!px-1 !py-0` (`components/button-group/button-group.twig`), producing
View/Edit/Delete targets well under the recommended size — the main tap-target
risk. This task is the gate that confirms the project goal is met.

## Acceptance criteria
- [ ] Manual QA matrix completed: {Apiary canonical, Apiary list, Hive
      canonical, Inspection list/detail, Queen canonical, Queen Observation}
      × {360px, 414px, 768px} — recorded in this note.
- [ ] Interactive controls (buttons, grouped buttons, filter inputs, pager,
      map controls, image links) meet a documented minimum tap target
      (target ≥44×44px; absolute floor 24×24px per WCAG 2.5.8).
- [ ] No horizontal page scroll at 360px on any page.
- [ ] Tap-target fix for grouped buttons agreed with
      [[0011-unify-button-group-sizing]] (avoid double-fixing).
- [ ] Any defects found are logged as follow-up tasks (NNNN) and linked here.

## Implementation notes
- This is primarily verification, not new CSS — but small corrective tweaks may
  land here. Prefer pushing structural fixes back into the owning task.
- Consider a lightweight checklist rather than automated tests; the suite
  (`--group hivelog`, see `AGENTS.md`) does not do visual/responsive testing.

## Dependencies
- Depends on: [[0005-responsive-entity-list-tables]],
  [[0006-responsive-detail-tables]],
  [[0007-responsive-filter-form-and-heading]],
  [[0008-responsive-map-and-image-grid]].
- Cross-project: [[action-button-consistency]] (tap-target sizing overlaps
  [[0011-unify-button-group-sizing]]).

## Defects found during QA

### Defect 1 — Breadcrumbs incorrect on all entity hierarchy routes (FIXED)
**Root cause:** `hivelog.breadcrumb` was registered at priority 100 in
`hivelog.services.yml`. The `easy_breadcrumb` contrib module (priority 1003)
was installed on the test site and its catch-all `applies()` was intercepting
all hivelog routes first, producing path-based trails instead of the correct
entity-hierarchy trails.

**Fix:** Raised `hivelog.breadcrumb` priority to 1004 in `hivelog.services.yml`.
AGENTS.md updated to document the intentional priority and the reason it must
remain above 1003.

**Pages affected:** Hive canonical, Hive edit form, Inspection canonical,
Queen canonical, Queen observation canonical, Queen observation edit form.

## QA matrix (first pass — 360px, all pages)

| Page | Scroll | Buttons joined/coloured | Tap targets | Breadcrumb | Notes |
|---|---|---|---|---|---|
| Apiary list | ✓ | ✓ | ✓ | ✓ | Pass |
| Apiary canonical | ✓ | ✓ | ✓ | ✓ | Pass — breadcrumb: Home › HiveLog › Meadow Apiary |
| Hive canonical | pending re-test | pending | pending | fixed | Re-test required after breadcrumb fix |
| Hive canonical (no queen) | ✓ | ✓ | ✓ | pending | Pass for layout/buttons |
| Inspection canonical | pending re-test | pending | pending | fixed | Re-test required |
| Hive edit form | pending re-test | pending | pending | fixed | Re-test required |
| Queen canonical | pending re-test | pending | pending | fixed | Re-test required |
| Queen observation canonical | pending re-test | pending | pending | fixed | Re-test required |
| Queen observation edit | pending re-test | pending | pending | fixed | Re-test required |

## Related
- Project:: [[mobile-ux-improvements]]
- Defect fix:: hivelog.services.yml priority 100 → 1004 (PR #97)
