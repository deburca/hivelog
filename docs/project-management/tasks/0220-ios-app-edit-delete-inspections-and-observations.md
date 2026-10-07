---
type: task
tags: [hivelog/task]
status: backlog
priority: medium
project: "[[ios-field-app]]"
area: ios
created: 2026-10-07
branch:
depends-on: "[[0212-hivelog-api-write-compatibility-with-host-firewalls]]"
blocked-by:
---
# Task: iOS app: view, edit and delete inspections and queen observations

## Context
From the owner's field comments:
3. *Inspections:* you can add one, but not change or delete an existing one. What would that take?
4. *Queen observations:* where can existing observations be seen? Can they be edited or deleted?

This note answers both and sets out the work. **Nothing is built yet.**

## What is true today
- **Observations are not listed anywhere.** The hive page asks for the queen's latest three
  (`recentObservations(limit: 3)`) only to print "Last seen 30 Sep 2026 · Excellent" under the
  queen. There is no screen of observations and none opens one.
- **Inspections are listed** (last, then "Earlier inspections", five in all) and each opens a
  **read-only** detail. The client's writes are `createRecord`, `uploadPhoto` and `report`: nothing
  updates or deletes.
- **Server:** the app's role (`hivelog_field_app`) holds `edit own hive inspection` and
  `edit own queen observation` (and the other *edit own*s), so a JSON:API `PATCH` is already
  allowed by permission. It holds **no `delete` permission at all, on purpose** (ADR-0107 section 4:
  "the app deletes nothing in v1"), and `HivelogApiAccessParityTest` and
  `testTheRoleIsLeastPrivilege` pin that.
- **The host firewall** that broke writes (task 0212) answers 403 to **every `PATCH` and `PUT`**
  before Drupal sees them (kragebaekgaard.dk). 0212 deliberately did not help those, because the app
  did not use them. Whether it blocks `DELETE` is **not known**; test it before designing around it.
- **Deleting is safe in the data model:** deleting an inspection only *detaches* any action log that
  pointed at it (delete registry row 21, DETACH); an observation has no children. Neither is blocked.

## What it would take
**Server (`hivelog_api`), small:**
1. **Edits over a firewall:** let a `POST` with `X-HTTP-Method-Override: PATCH` (and `DELETE`) reach
   JSON:API as that method, in the existing `HivelogApiWriteCompatibilitySubscriber`, only with an
   `Authorization` header and only on the prefix, advertised as a new `meta.hivelog_api.features`
   entry (`method_override`) so the app uses it only when offered. Without it, an app on such a host
   could view but not change records.
2. **Delete permission:** add `delete own hive inspection` and `delete own queen observation` to the
   role (and *only* those two: not apiaries, hives, queens, logs), an update hook for existing sites,
   and update the least-privilege and parity tests, which currently assert no `delete` at all. The
   access handlers already apply the web's rule (an owner or member), so the API grants nothing the
   web does not.
3. **Orphaned photos** on a deleted inspection or observation: check whether the web's delete leaves
   the files and do the same (or clean up), so the two surfaces agree.
4. Release (a new permission and a feature flag: 2.12.0).

**App, the larger part:**
1. **An observations screen** from the queen card ("All observations"): the queen's observations,
   newest first, paged, each opening a detail. This is the "where can I view them" answer.
2. **Detail screens get Edit and Delete** (inspection, observation). Delete asks for confirmation
   ("Delete this inspection? Photos on it go too.") and returns to the hive, refreshing it.
3. **An edit form:** the create form (`RecordFormModel`, built from `/schema`) opened *with the
   record's values*, sending only what changed; photos: show the existing ones, allow adding and
   removing. `APIClient` gains `updateRecord` and `deleteRecord`, with the override above when the host
   offers it.
4. **Refresh and caches:** an edit or delete bumps `dataVersion` so the hive page, the lists and the
   read cache update; the cached copy of a deleted record must go.
5. Tests (client: the method and override header, 422 and 403 mapping; model: prefilled form, only
   changed fields), a UI pass on the simulator against the demo site, and an app release.

## Decisions needed from the owner
- **Online only, or queued offline?** The outbox today holds creates. An edit or delete of a record
  already on the server is more dangerous to replay late (the record may have changed on the website
  meanwhile). **Recommendation: online only for the first version**, with a clear "needs signal"
  message; revisit if it bites in the apiary.
- **Conflicts:** JSON:API has no version check. Last write wins is the simple rule; a stricter one
  (the app compares the record's `changed` time before it sends) is possible but costs a read.
- **Who may delete:** owners and apiary members, as on the web, or owners only? The role cannot
  express "owner only"; the access handler decides.
- **Is deleting from a phone wanted at all?** Editing a mistake is common in the field; deleting is
  rarer and irreversible. An option is to ship observations/inspections *view and edit* first and
  *delete* second.

## Acceptance criteria
- [ ] The three server items above, with kernel tests, released
- [ ] The app lists a queen's observations and opens one
- [ ] The app edits an inspection and an observation, photos included
- [ ] The app deletes an inspection and an observation, with confirmation
- [ ] Works on a host whose firewall blocks `PATCH` (the live site), verified there
- [ ] A person without edit or delete access gets a clear message, not a failure
- [ ] The decisions above recorded

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits::
