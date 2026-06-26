---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[action-button-consistency]]"
area: entity
created: 2026-06-17
branch: feature/0012-audit-action-buttons-across-pages
release: 1.4.0
depends-on: ["[[0010-define-button-tokens-and-source-of-truth]]"]
---
# Task: Audit action buttons across pages

## Context
Even with one styling system ([[0010-define-button-tokens-and-source-of-truth]]),
buttons can still be inconsistent in **which variant and label** each action
uses. A known example: in `src/Controller/HiveController.php`, "Add Inspection"
and "Add Queen" are `variant: primary`, but "Edit Queen" and "Add Observation"
use the default variant — so "Add" actions don't look alike. This task defines
and applies variant/label rules everywhere buttons are emitted. Part of
[[action-button-consistency]].

## Variant rules (ratified)
- **Add / Save / primary CTA** → `primary`
- **Edit / View / secondary** → `default` (omit `variant` key — default is the fallback)
- **Delete / destructive** → `danger`

## Acceptance criteria
- [x] Inventory of every action-button render site (controllers, list builders,
      form actions, action links) with current vs intended variant/label.
- [x] Variant rules above ratified and applied across the module.
- [x] "Add Observation" / "Edit Queen" reconciled with the ratified rules.
- [x] Action links from `hivelog.links.action.yml` divergence documented as
      acceptable (see below).
- [x] Labels follow "Add X" / "Edit X" / "Delete X" / "View" convention — confirmed
      consistent across all render sites.

## Render-site inventory

| Render site | Button | Was | Correct | Fixed? |
|---|---|---|---|---|
| `ApiaryController` — apiary page | Add Hive | `primary` | `primary` | ✓ no change |
| `ApiaryController` — hive row ops | Edit | `default` (omitted) | `default` | ✓ no change |
| `ApiaryController` — hive row ops | Delete | `danger` | `danger` | ✓ no change |
| `HiveController` — hive page | Add Inspection | `primary` | `primary` | ✓ no change |
| `HiveController` — inspection row ops | View | `default` (omitted) | `default` | ✓ no change |
| `HiveController` — inspection row ops | Edit | `default` (omitted) | `default` | ✓ no change |
| `HiveController` — inspection row ops | Delete | `danger` | `danger` | ✓ no change |
| `HiveController` — queen section | Edit Queen | `default` (omitted) | `default` | ✓ no change |
| `HiveController` — queen section | Add Observation | `default` (omitted) | `primary` | **fixed** |
| `HiveController` — no queen | Add Queen | `primary` | `primary` | ✓ no change |
| `QueenController` — queen page | Add Observation | `primary` | `primary` | ✓ no change |
| `QueenController` — observation row ops | View | `default` (omitted) | `default` | ✓ no change |
| `QueenController` — observation row ops | Edit | `default` (omitted) | `default` | ✓ no change |
| `QueenController` — observation row ops | Delete | `danger` | `danger` | ✓ no change |
| `QueenController` — queen page actions | Edit | `primary` | `default` | **fixed** |
| `QueenController` — queen page actions | Delete | `danger` | `danger` | ✓ no change |
| `HiveInspectionController` — inspection page | Edit | `primary` | `default` | **fixed** |
| `HiveInspectionController` — inspection page | Delete | `danger` | `danger` | ✓ no change |
| `QueenObservationController` — observation page | Edit | `primary` | `default` | **fixed** |
| `QueenObservationController` — observation page | Delete | `danger` | `danger` | ✓ no change |
| `ApiaryListBuilder` — apiary list row ops | Edit | `default` (omitted) | `default` | ✓ no change |
| `ApiaryListBuilder` — apiary list row ops | Delete | `danger` | `danger` | ✓ no change |
| `ApiaryListBuilder` — heading | Add Apiary | `primary` | `primary` | ✓ no change |
| `HivelogHiveFilterForm` | Reset | `default` (omitted) | `default` | ✓ no change |
| `HivelogInspectionFilterForm` | Reset | `default` (omitted) | `default` | ✓ no change |

## Action links divergence (`hivelog.links.action.yml`)

`hivelog.links.action.yml` defines two local actions — **Add Apiary** and **Add
Queen** — that render via Drupal's local-action theming, not the `hivelog:button`
SDC. These are styled by the active admin theme and do not pass through
`hivelog.buttons.css`.

**Decision: document as acceptable.** The local-action links appear in Drupal's
standard local-action region (above page content), not inline with module-owned
button groups. Replacing them with SDC buttons would require custom controller
rendering that duplicates Drupal's access/route machinery for no material gain.
The divergence is visible but not disruptive — both the local-action links and
the SDC buttons lead to the correct forms.

## Test fixes
Two kernel test assertions were updated to match the corrected variants:
- `QueenObservationTest::testObservationViewRendersSectionedLayout` — `button--primary` → `button--default`
- `QueenTest::testQueenViewRendersSectionedLayout` — `button--primary` → `button--default`

All 169 tests green after fixes.

## Related
- Project:: [[action-button-consistency]]
- PRs:: #91
- Released:: 1.4.0
