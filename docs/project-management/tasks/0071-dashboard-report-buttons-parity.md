---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[action-button-consistency]]"
area: theme
created: 2026-09-09
completed: 2026-09-09
branch: feature/0071-dashboard-report-buttons-parity
release: 1.8.5
depends-on: ["[[0056-dashboard-landing-page]]", "[[0068-replace-dropbutton-operations]]"]
blocked-by:
---
# Task: Dashboard "Needs attention" — Done / Ignored button parity

## Context
Three related defects on the `/hivelog` dashboard "Needs attention" queue,
all in `DashboardController::buildAttentionRow()` /
`collectSeasonalAlerts()`:

1. **Only one report action.** Seasonal rows rendered a single "Report
   done" button. The apiary and hive calendar checklists
   (`ApiaryController` / `HiveController`, since
   [[0068-replace-dropbutton-operations]]) offer both outcomes as a
   `hivelog:button-group` — "Report Done" **and** "Report Ignored". The
   dashboard should match: a beekeeper who wants to dismiss a seasonal
   task as not-applicable had no way to do it from the dashboard.

2. **Label not translated.** The dashboard passed
   `$this->t('Report done')` (lower-case *done*). The `.po` only carries
   `Report Done` / `Report Ignored` (title case, from the checklists), so
   the dashboard string fell through untranslated while `/hivelog/apiary/1`
   showed the Danish label.

3. **Labels too long for mobile.** "Report Done" / "Report Ignored" wrap
   awkwardly in the narrow dashboard action column and in the checklist
   Operations column on phones.

## Resolution
- `collectSeasonalAlerts()` — both the apiary-scoped and hive-scoped
  alert descriptors now carry an `actions` list built by a new
  `reportButtons($route, $params)` helper: two `hivelog:button`
  descriptors, **"Done"** (primary) + **"Ignored"**, each a safe GET link
  to the action-log add form with a `?status=` default (ADR-0018 — the
  write still only happens through that form's CSRF-protected POST).
- `buildAttentionRow()` — delegates the action cell to a new
  `buildAttentionAction()`: an `actions` list renders as a
  `hivelog:button-group` inside the existing `.hivelog-attention__action`
  wrapper; low-stock rows keep their single "Add purchase" button via the
  unchanged `action_label` / `action_url` path.
- `ApiaryController` / `HiveController` checklist buttons relabelled
  `Report Done` → **`Done`**, `Report Ignored` → **`Ignored`**. These
  msgids already exist in `hivelog.da.po` (`Udført` / `Ignoreret`, reused
  from the status-filter labels), so the checklist stays translated and
  the dashboard now picks up the same translation.
- `hivelog.da.po` — dropped the now-unused `Report Done` / `Report
  Ignored` entries; added the new source refs to `Done` / `Ignored`.
- Doc comments in `ApiaryActionLogController` / `HiveActionLogController`
  updated to quote the new labels.

## Acceptance criteria
- [x] Dashboard seasonal rows show a Done + Ignored button group linking
      to `…/log/add?status=done` and `…?status=ignored`.
- [x] Dashboard + checklist report buttons read "Done" / "Ignored" and
      resolve through the existing `Done` / `Ignored` translations.
- [x] Low-stock rows unchanged (single "Add purchase" button).
- [x] `DashboardTest::testOverdueApiaryActionAppears` extended for the
      button group + both status links + short labels.
      `DashboardTest` + `ApiaryCalendarChecklistTest` +
      `HiveCalendarChecklistTest` green from cms2 (55 tests, OK — the 6
      pre-existing geofield deprecations only).
- [x] phpcs clean (`--standard=Drupal,DrupalPractice --warning-severity=0`).
- [x] PR against `main`, CI green (Lint + Kernel/Unit are the hard gates)
      — PR #138, squash-merge commit `0beb718`. Shipped in release 1.8.5.

## Implementation notes
- Key files: `src/Controller/DashboardController.php`
  (`buildAttentionRow` / `buildAttentionAction` / `reportButtons` /
  `collectSeasonalAlerts`), `src/Controller/ApiaryController.php`,
  `src/Controller/HiveController.php`, `translations/hivelog.da.po`,
  `tests/src/Kernel/DashboardTest.php`.
- No entity schema change — no update hook.
- CSS: reuses `.hivelog-attention__action` + `.hivelog-button-group`
  (both already in the button-context list in `css/hivelog.buttons.css`);
  no stylesheet change.

## Related
- Project:: [[action-button-consistency]] · also helps [[mobile-ux-improvements]]
- Follows:: [[0068-replace-dropbutton-operations]], [[0056-dashboard-landing-page]]
- Decisions:: [[0012-action-button-design-system]], [[0018-csrf-and-safe-http-methods]]
- Commits:: `0beb718` (PR #138 — fix + tests + `.po`); `5e4d730`
  (release 1.8.5 bump)
