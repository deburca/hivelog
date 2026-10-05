---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0206-ios-app-queen-calendar-and-alerts
release:
depends-on: ["[[0205-ios-app-browse-and-inspection-logging]]", "[[0202-hivelog-api-computed-view-endpoints]]"]
blocked-by:
---
# Task: iOS app: queen observations, calendar actions and alerts

## Context
The remaining in-scope field actions.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] Add a queen observation from the hive screen (only when there is an
      active queen, matching the web hive page)
- [x] Calendar actions due this week for an apiary; report done/ignored as a
      hive or apiary action log
- [x] Alerts screen from the needs-attention endpoint
- [ ] Hive stat tiles and the latest insight on the hive screen when
      available. **Built, decoded and rendered with sample data, but never seen with
      real data: the kbg dev site has no sensors and no insight module, so it returns
      no tiles and no insight.**

## Implementation notes

### Backend (this repo)
Nothing new was needed: the alerts, stat-tile and insight endpoints are 0202's, and
creating an observation or an action log is plain JSON:API with the 0200/0203
validation and access. The schema endpoint from 0205 already describes
`queen_observation`, `hive_action_log` and `apiary_action_log`. (Verified on the dev
site: an observation is created, a photo uploads onto it, a log is filed with the right
owner, year and week.)

### App (`~/Development/vinculum`, branch `feature/0206-queen-calendar-and-alerts`, on top of 0205's)
- **One form for any record.** 0205's inspection form became `RecordKind` /
  `RecordDraft` / `RecordFormModel` / `RecordFormView`: a kind names the record type,
  its parent, its date field, its sections and its dependent fields. An inspection and a
  queen observation are two such kinds; the next record type (and 0207's outbox) will be a
  third.
- **Alerts tab** (badge = how many), and a **"Due now" card** on an apiary's page and on a
  hive's page, from the same shared state. An alert that is a seasonal calendar action
  opens a sheet with two large buttons, *Mark done* and *Ignore this time*, and optional
  notes. Done is filed under the server's own week (not the phone's clock) with
  `week_completed`; ignore records no week. A hive-scoped action becomes a hive action log,
  an apiary-scoped one an apiary action log. The action leaves the list at once and the
  server is asked again.
- **Queen card** on the hive page: her name, marking colour, year and breed, her latest
  observation, and *Add observation* (shown only when there is an active queen).
- **Insight and tile cards** at the top of the hive page, when the server returns any.
- `Alert` is called `Attention` in code, because SwiftUI already has an `Alert`.

### Verification
- 127 app tests pass on the Mac and on an iOS 27 simulator. New: alerts, tiles, insight,
  observations and reporting against responses captured from the dev site (hand-written
  where the dev site has nothing: tiles with values, an insight; they are marked as such),
  and the model's alerts / reporting / ended-sign-in behaviour.
- **Run on the simulator against the kbg dev site**: the Alerts tab (27 to review, 25
  overdue, matching the website), reporting an apiary-level action *done* (apiary action
  log on the server: apiary 10, done, 2026, week 41, owned by the test user), reporting a
  hive-level action *ignored* (hive action log: ignored, no week), the due-now cards on both
  pages, adding a queen observation (saved with its health, and the queen card updated),
  restoring the saved session across a reinstall.

### Found while doing it
- **The two checks CI runs that mine did not.** hivelog's CI had been red since 2.8.1 on
  unused imports my local phpcs did not flag (fixed, and the Test job, never run since, now
  passes on PHP 8.3/8.4/8.5).
- **"Due now" is what the website's Needs attention list says**: overdue or due this week.
  Actions scheduled for later weeks are not listed, as on the dashboard. The website's
  calendar page shows those too; the app does not yet.
- **No inventory usage or harvest yield on "done"** (out of scope here; the website still
  handles it), and **no link from a done action to an inspection** (the hive log's
  `inspection` field).
- **Two people reporting the same action** each file a log; the website behaves the same
  (the most recently changed wins).

### Not done
- Seeing the tiles and insight with real data; the camera and gloves checks from 0205.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
