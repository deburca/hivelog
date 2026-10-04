---
type: task
tags: [hivelog/task]
status: done
priority: high
project: "[[ios-field-app]]"
area: docs
created: 2026-10-04
branch: feature/0199-decide-mobile-offline-scope
release:
depends-on:
blocked-by:
---
# Task: Decide the app's offline scope

## Context
[[0107-mobile-client-api-and-native-ios-app]] §4 leaves offline open:
(A) online with an outbox for writes, or (B) offline-first with a local
copy and delta sync. The choice shapes the API (client-generated UUIDs,
`changed` exposure, delete tombstones) so it must be made before
[[0201-hivelog-api-submodule-scaffold]] starts.
Part of [[ios-field-app]].

## Acceptance criteria
- [x] A or B chosen (or a documented middle ground, e.g. A plus a cached
      read-only copy of the last-viewed apiaries)
- [x] ADR-0107 §4 updated with the decision and ADR status flipped to
      `accepted` if the rest of it stands after
      [[0198-mobile-api-jsonapi-oauth-spike]]
- [x] API requirements the choice implies listed for 0201/0202

## Implementation notes

### Decision: A, online with an outbox, plus a client-side read cache
This is the middle ground the criteria allow: **A for writes, and the app
caches what it has recently viewed so it stays readable offline.** B
(offline-first with delta sync) is rejected for v1.

Why:
- The field-critical writes in the app's scope (ADR-0107 §3) are all
  **creates of leaf records**: an inspection, a queen observation, an action
  report. Nothing else depends on them, so replaying them later is safe and
  conflicts do not arise. Parents (apiary, hive, queen, calendar action)
  always already exist, because creating them stays web-only.
- Edits the app does make are few and low-conflict: reporting a calendar
  action `done` or `ignored`. With no optimistic locking on the API (spike),
  a status edit is last-write-wins, which is acceptable for that field.
- **The app does not delete anything** in v1, so there are no tombstones, which
  is the main cost driver of B.
- B needs a delta endpoint, tombstones, conflict UI and a local mirror of the
  access model (including [[shared-apiaries-and-team-roles]] later). That is
  several times the work for a benefit (browsing everything with no signal)
  that is not field-critical.
- Choosing A closes nothing off: B can be added later on the same API.

**Not an input I had:** the criteria ask about real signal conditions at the
out-apiaries and the edit/create mix. I could not measure those. The edit/create
mix follows from the app's scope above. If a real apiary has no signal for
hours (not minutes), the outbox still works but the cache window matters more;
revisit the read cache size then, not the architecture.

### API requirements for 0201 / 0202
Confirmed by the spike unless marked otherwise.
1. **Client-generated UUIDs on POST**: works on stock JSON:API. A retry of the
   same UUID returns **409**, with no duplicate row. The outbox treats 409 on
   a create as "already applied" and drops the item.
2. **Edits are idempotent PATCHes** with no `changed` precondition (`changed`
   is not writable, so there is no optimistic locking). Document last-write-wins
   rather than build a precondition.
3. **No delete or tombstone support** is needed, because the app does not delete.
   Keep `delete` out of the allow-listed operations for the app's role.
4. **Replay order matters, and the API cannot enforce it.** An action report
   may reference an inspection created in the same offline session. JSON:API
   accepts a relationship to a UUID, but only once that record exists, so the
   outbox must replay in creation order and stop a dependent item if its
   dependency failed.
5. **Photos: upload at replay time, not at capture time.** An orphan file upload
   is a *temporary* file that Drupal's cron purges after its max age (default
   6 hours, `system.file` `temporary_maximum_age`; verify in 0201), so a photo uploaded at capture and referenced
   hours later can vanish. Replay does: upload the file(s), then create the
   inspection referencing them, in one outbox step. A crash between the two
   leaves only a temporary orphan, and the retry re-uploads. A file upload has no
   client UUID, so it cannot be made idempotent by id. This is acceptable
   because orphans are purged. (The spike showed both orphan-then-reference
   and direct attach work; direct attach onto an existing inspection is the
   fallback.)
6. **Errors must be classifiable.** The outbox surfaces 4xx to the user
   (422 field errors, 403 access) and retries only network failures and 5xx
   with a cap. The spike found client-fixable states returning **500**
   (cross-apiary component, over-stock), which would look retryable. This is
   one more reason [[0200-move-form-validation-to-entity-constraints]] must turn
   those into 422. For the app's own record types the relevant ones are
   inspection, observation and action-log constraints.
7. **Token refresh must be serialised in the app.** The spike showed refresh
   *rotates*: reusing the old refresh token is 400 `invalid_grant`. Two outbox
   workers refreshing at once would invalidate the session. The app uses a single
   refresh at a time. Access tokens are short (300 s in the spike), so every
   long replay crosses a refresh. Refresh-token lifetime sets how long the
   device may be offline before re-sign-in; read the default in 0201 and
   record it.
8. **Capture-time dates:** `inspection_date` / `observation_date` are sent
   from the device when the user records the visit, never defaulted to replay
   time on the server.
9. **0202 (computed endpoints, dashboard alerts, stat tiles, insights) are
   read-only and online-only.** The app shows the last cached response with a
   "last updated" time when offline. No offline write path needed.
10. **No API support is needed for the read cache.** It is a client-side store
    of recently fetched responses (no delta or ETag endpoint). Cache lifetime and
    size are an app concern for [[0207-ios-app-offline-outbox]].

### Consequences for other notes
- ADR-0107 §4 now records this decision; ADR status moves to `accepted` (the
  rest stands after the spike, with the §2 security amendment).
- [[0207-ios-app-offline-outbox]] stands as written (it assumed A). Requirements
  4 to 7 above are added to its notes.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits:: 
