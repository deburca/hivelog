---
type: task
tags: [hivelog/task]
status: review
priority: low
project: "[[ios-field-app]]"
area: ios
created: 2026-10-07
branch:
depends-on: "[[0221-ios-app-hive-insight-tint]]"
blocked-by:
---
# Task: iOS app: one tint per hive, across the list and the hive page

## Context
After [[0221-ios-app-hive-insight-tint]] the owner asked that, when viewing an apiary, the tint of a hive's
icon be consistent with the tint when viewing that hive. They were not guaranteed to be: the list got its
verdicts from one request for the apiary, the hive page from the hive's own insight, each into its own
state. While one was loading, or if one request failed (an older server answers 404 to the apiary request
only), or after a newer insight was fetched on the page, the two could disagree.

## Acceptance criteria
- [x] The list and the page read a hive's tint from one place in the app
- [x] Whichever screen heard last decides, for both: the list's verdicts, and the page's own insight
- [x] A hive found to have no insight loses its tint on both
- [x] A failed apiary request leaves what the hive pages already learnt alone, and a hive's page opened from
      the list shows the list's tint at once (no plain-then-tinted flash)
- [x] Tests, and checked on the simulator including the red "act now" state
- [ ] Released

## Implementation notes
- `FieldAppModel.hiveVerdicts` (by hive id, internal) with `noteVerdicts(_:for:)` (the apiary list heard
  every hive's verdict; one missing from the answer has none), `noteInsight(_:of:)` (a hive's own page heard its
  insight, or none) and `tint(of:)`. `HiveListView` publishes only if its request succeeded and reads
  `field.tint(of:)`; `HiveDetailView` reads the same for its header and publishes after its insight loads.
- Nothing is stored beyond the session: a new sign-in builds a new model.

## Verification
- `swift test`: 150 + 99 pass (four new in `FieldAppModelTests`: the list's verdicts, the page overriding
  the list, a hive with no insight, a hive never heard of).
- Simulator against the demo: with Hive 1's insight set to "act now", its badge is red with an exclamation
  mark in the list and on the hive page ("ACT NOW", the red insight card); Hive 2 green, Hive 3 grey on the list.
  The first sight of the red state, which the earlier checks never had.

## Not verified
- Opening a hive from a QR scan or an alert (no list ahead of it): the page uses its own insight there.
- A failed apiary request with the page's request succeeding, on a device (covered by the model test only).

## Related
- Project:: [[ios-field-app]]
- Decisions:: 
- Commits:: not committed yet (app repository, `feature/0222-consistent-hive-tint`)
