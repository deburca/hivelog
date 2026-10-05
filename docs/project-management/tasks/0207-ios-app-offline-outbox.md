---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[ios-field-app]]"
area: app
created: 2026-10-04
branch: feature/0207-ios-app-offline-outbox
release:
depends-on: ["[[0199-decide-mobile-offline-scope]]", "[[0205-ios-app-browse-and-inspection-logging]]"]
blocked-by:
---
# Task: iOS app: offline outbox for writes

## Context
Implements the outcome of [[0199-decide-mobile-offline-scope]]. Assuming
option A: writes made without signal are queued and replayed later.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] Inspections, observations and action reports created offline go into
      a persistent outbox with client-generated UUIDs (photos included)
- [x] Replay in order on reconnect; a retried create is idempotent (same
      UUID, no duplicate)
- [x] Failed items (validation or access errors) surfaced for the user to
      fix or discard, never silently dropped
- [x] Pending count visible; works across app restarts
- [x] Recently viewed apiaries/hives readable offline (cached)

## Implementation notes

### Backend (this repo)
None needed. The stock JSON:API already accepts a client UUID on create and answers a
repeat of the same id with **409**; checked against the dev site before building
(first POST 201, second 409, one record).

### App (`~/Development/vinculum`, branch `feature/0207-offline-outbox`, on top of 0206's)
- **Outbox** (`Outbox`, an actor; `FileOutboxStore`): each item is a JSON file written
  atomically, with its photos as separate files, in the app's own folder, one set per server.
  An item's UUID is also the id of the record it creates, so a send whose answer was lost
  is found already there (409) and treated as done. Items are sent **oldest first**; a
  connection that is down **stops the round** (the rest would only fail, and order is kept);
  an item the server **refuses** is set aside with the server's own words and the round
  goes on with the others.
- **Classification (0199 §6):** no connection and the server's own faults (5xx, 408, 429)
  are retried, with a cap of five server faults per item before it is handed to the person;
  being offline never counts. Everything else (422, 403, 404, an unexpected 409) is a
  message for the person, never retried on its own. A sign-in that has ended stops the round
  and keeps everything.
- **Photos at replay time (0199 §5):** the record is created first, then each photo is
  uploaded onto it, one by one. A photo's state is *waiting*, *attempted* (sent, answer
  lost) or *uploaded*; on the next round an *attempted* photo is looked up on the record by
  name before it is sent again, so a photo is not duplicated. Photo names carry the record
  type, date and the first part of the record's id. The photo files are removed as they go.
- **Saving a form** tries the server first, so a rule the server enforces is shown against
  its field at once, as before. If the server cannot be reached (or has a fault) the rest goes
  to the outbox under the same id, the form closes, and the hive page and a banner say it is
  waiting. A refusal is *not* queued: it is shown for the person to fix.
- **Calendar action reports** work the same way (online first, queued if the server cannot be
  reached) and leave the list at once; they are filed under the phone's ISO week if the alerts
  on screen are a saved copy, not the old copy's.
- **A failed item** opens as the form it was, filled in, with the server's objection on its
  field (*Fix*), or can be discarded after a confirmation. A failed report offers *Try again*.
- **Read cache** (`FileReadCache`): the last good answer to each read, keyed by path and
  query, 300 newest kept, file names hashed, excluded from backup. It is the server's own
  JSON, not the app's models, so it cannot disagree with the decoders. Only "cannot be
  reached" falls back to it: a 403, 404 or 5xx is told as it is. A banner says "No
  connection. Showing what was loaded 2 minutes ago".
- **When it sends:** when the app opens, when it comes to the front, when the network comes
  back (`NWPathMonitor`), and every 30 seconds while the app is open (a route to the
  internet is not a route to the server).
- **Signing out** with things unsent warns how many will be deleted; signing out deletes
  them and the cache. A sign-in that *ends* (revoked or expired) keeps the outbox so it can be
  sent after signing in again.

### Verification
- 165 app tests pass on the Mac and on an iOS 27 simulator (outbox, cache, form and model behaviour, including: restart
  persistence, send order, 409 as applied, offline stop, a refused item set aside, the server
  fault cap, photo resume, no duplicate photo, an ended sign-in, discard, replace-on-fix,
  one round at a time, the cache's fallback rules).
- **Run on the simulator against the kbg dev site, with the dev site made unreachable by
  stopping `ddev-router`:** pages visited online were shown again with the saved-copy banner,
  including after force-quitting and relaunching the app while offline; an inspection with a
  note, two choices and a photo was saved offline; a calendar action was reported done
  offline and left the list; both survived another force-quit; with the router back they were
  sent **without anyone touching the app** (the inspection arrived once, with its photo named
  for its record, owned by the test user; the report arrived once, week 41); an inspection
  the server would refuse (fed, no feed type) was queued, refused when sent, surfaced in a red
  banner with the server's words (in Danish) and **Fix** / **Discard**, and **Fix** reopened
  the form as it was.

### Found while doing it
- **JSON:API names the unit of retry.** Creating under a client UUID and getting 409 for a
  repeat means the whole design needs no server change. A repeated *photo upload* has no id
  and is not idempotent; that is the only place a duplicate can arise, and it is handled by
  looking at the record's photo names first.
- **Not done: reconciling a queued report with a newer server state.** If someone else
  reports the same action while the phone is offline, both logs exist (the website behaves
  the same: the most recently changed wins).
- **Not done: the cache is not used for photos' bytes**, only for the JSON. A photo already
  seen is re-fetched; offline it shows a spinner, not the picture.
- **Not done: a queued item cannot be edited** except when the server refused it. To change one
  that is waiting, discard it and enter it again.
- **Not done: no Background App Refresh / background URLSession.** Items are sent while the
  app is open or comes to the front, not while it is closed. A beekeeper who records at an
  apiary with no signal and drives home must open the app once for it to send.
- **Not tested on a phone:** signal that comes and goes mid-request, low-power mode, a full disk.

### Follow-ups
- Background sending (`BGAppRefreshTask`/`BGProcessingTask`) so items go without opening the app.
- Cache photo bytes for recently viewed inspections.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
