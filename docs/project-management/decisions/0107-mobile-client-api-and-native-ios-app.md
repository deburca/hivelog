---
type: decision
tags: [hivelog/decision]
status: proposed
date: 2026-10-04
supersedes:
---
# ADR-0107: Per-user mobile API and a native iOS field app

## Status
proposed, 2026-10-04. Planning artifact only, with no code changes yet.
Spike [[0198-mobile-api-jsonapi-oauth-spike]] (2026-10-04) supports §1 and
§2 with one amendment, below.
See [[ios-field-app]] for the project scope and phasing.

## Context
HiveLog is entirely server-rendered. Every one of its 22 entity types
(16 core, 6 from submodules) is created and edited through Drupal forms
and custom canonical-page controllers. The responsive work in
[[mobile-ux-improvements]] made those pages usable on a phone, but most
real beekeeping happens at the hive: gloves on, a smoker in one hand,
often with poor or no signal at an out-apiary. A native app could offer
things the browser can't do well there, such as camera capture straight
into an inspection, GPS for apiary location, QR hive-label scanning
([[qr-hive-labels]] lists a native app as out of scope), push alerts for
needs-attention items and `nexus` insights, and offline logging.

What exists today that a client could talk to:

- `GET /hivelog/api/collective/context` (`collective`) is read-only and
  authenticated by one **site-level** `ApiClient` bearer token. That
  token belongs to the site, not a person, so entity access can't be
  checked per user. It isn't a basis for an end-user app.
- `POST /hivelog/api/sensor-readings` (`nanoprobe`) is device ingestion
  only.

Facts about the codebase that shape the decision:

1. **Access is already entity-level.** `src/*AccessControlHandler.php`
   implement own/any/`administer hivelog`, and route-level entity access
   (task 0133) plus list row filtering (task 0124) sit on top. An API
   that goes through the entity access system inherits all of it.
2. **Deletes are already form-independent.** Delete-dependency CASCADE
   and DETACH (ADR-0103) run from an entity hook
   (`hivelog.module` → `hivelog.delete_dependency_executor`), and BLOCK
   is an access check (task 0141). An API delete obeys ADR-0103 with no
   extra work.
3. **Validation is not form-independent.** The two-layer convention
   (`preSave()` throws, `validateForm()` shows the friendly error, as
   used by `HiveComponent` and `CalendarActionItemRequirement`) means an
   API caller only ever reaches the throwing layer. The result is an
   uncaught `\InvalidArgumentException` (HTTP 500), not a field-level
   422.
4. **Many useful values are computed, not stored.** Examples:
   `Hive::getEmptyWeightKg()`, stock on hand,
   `getAvailableForHiveAssignmentQuantity()`, stat tiles
   (`hook_hivelog_*_stat_tiles()`), needs-attention alerts and dashboard
   sections. None of these appear in a generic entity serialization.
5. **Sync primitives mostly exist.** Every entity has a `uuid`. All but
   `SensorReading` (machine-written and never edited by a person) use
   `EntityChangedTrait`.
6. **Each install is its own server.** HiveLog is a module that any
   Drupal site can install, not a hosted service, so a client must
   connect to a URL the user chooses.

## Decision

### 1. A new optional submodule exposes a per-user API
A fifth submodule, provisionally `hivelog_api`, under `modules/`
(ADR-0098: core never depends on it). It depends on core `jsonapi`,
`serialization` and contrib `simple_oauth`. It provides:

- **JSON:API resources for core entity types**, restricted to the
  types and fields the app needs (via `jsonapi` resource config or a
  `jsonapi_extras`-style allow-list, decided in [[0198-mobile-api-jsonapi-oauth-spike]]: `jsonapi_extras` with `default_disabled`, which also sets `path_prefix` for versioning). Writes
  go through the entity API, so the access handlers, ADR-0103 delete
  behaviour and `preSave()` invariants (one active queen, queen colour)
  apply unchanged.
- **OAuth2 authorization code + PKCE** (`simple_oauth`), with one
  public client (no secret) for the app. Tokens are scoped to a real
  Drupal user, so everything a beekeeper can see or do through the API
  matches the web UI exactly. Short-lived access tokens and refresh
  tokens, revocable per user.
- **A small set of read-only custom endpoints** for computed views the
  app shows (dashboard alerts, hive stat tiles, empty or net weight,
  item availability), each returning the same data the matching web
  builder uses rather than a second implementation.

`collective`'s site-level token and context endpoint stay as they are.
That is a different trust model, for machine consumers, and is out of
scope here.

### 2. Form-only validation moves into entity constraints
Each `validateForm()` rule that guards data integrity becomes an
entity-level `Constraint` plugin (field or entity constraint), so web
forms and the API return the same error. This also benefits core, not
just the app. `preSave()` keeps its throw as a final backstop. The
two-layer convention becomes: **constraint (validates and gives a
friendly message) plus `preSave()` (backstop)**, with the form
inheriting the constraint through `ContentEntityForm::validateForm()`
instead of repeating the rule.

**Amendment from the spike (0198): this is a security requirement.**
Create and relationship-PATCH access is a global permission and does not see
the target parent, so through stock JSON:API a user could create records in,
and move their records into, another beekeeper's apiary or hive (HTTP
201/204). A "current user can view the referenced parent" field constraint on
every parent reference closes it (422). Writes must stay disabled until those
constraints exist. Constraints should also replace `preSave()` throws that
guard client-fixable states, which otherwise surface as HTTP 500.

### 3. A native SwiftUI app, scoped to the field workflow
A separate repository, not this module repo. The app covers:
connecting to a server, signing in, browsing apiaries and hives,
logging an inspection with photos, adding a queen observation, marking
a calendar action done or ignored, viewing alerts and the latest
insight, and scanning a hive QR label. Inventory, purchases, products,
financial reports, calendar *planning* and all admin or configuration
screens stay web-only.

### 4. Offline: decided before any client code (open)
This is the decision with the biggest impact on how the API is shaped,
and it's left **open** in this ADR. Two options are on the table:

- **A. Online with an outbox.** Reads need signal. Writes made without
  signal go into a local queue and are sent in order when the device
  reconnects. Records are created with client-generated UUIDs, so a
  retry is idempotent. Conflicts are rare because new inspections and
  observations are create-only.
- **B. Offline-first.** A local copy of every apiary the user can
  access, delta sync using `changed`, and conflict resolution for
  edits. Several times the effort of A on both client and server
  (delta endpoint, tombstones for deletes).

The provisional lean is **A**. The field-critical actions are almost
all *creates*, so A covers the poor-signal case for the work that
matters, and B can be built on top later without changing the API
shape A requires (client UUIDs, `changed` exposed).

### 5. Alternative considered: a PWA
A web manifest and service worker over the existing responsive UI
would cost almost nothing on the backend and give home-screen install
and camera upload. It was rejected as the end state because Drupal
form submissions can't be queued offline in any meaningful way, and
iOS restricts PWA push notifications and background behaviour. It
remains a cheap interim step if the native app slips.

## Consequences
- Positive:
  - One per-user API that later clients (Android, CLI, third-party
    integrations) can reuse, with access semantics identical to the
    web UI.
  - Moving validation to constraints closes a real gap (programmatic
    saves currently get 500s, not validation errors) whether or not
    the app ships.
  - Native field features (camera, QR, push, outbox) that the web UI
    can't provide.
- Negative / trade-offs:
  - Adds a contrib dependency (`simple_oauth`) and an OAuth key pair
    to manage on every site that enables the submodule.
  - A second codebase (Swift) with its own release cadence and App
    Store review. Review needs a public demo server and account, which
    `assimilate` could seed.
  - API compatibility becomes a contract. Field renames in
    `baseFieldDefinitions()` now break shipped app versions, so the API
    needs versioning, or field aliases in the resource config.
  - [[shared-apiaries-and-team-roles]] would change access semantics
    under the API. The API inherits that automatically, but the app's
    UI must handle viewer and editor roles once it lands.
- Follow-up tasks: [[0198-mobile-api-jsonapi-oauth-spike]],
  [[0199-decide-mobile-offline-scope]],
  [[0200-move-form-validation-to-entity-constraints]],
  [[0201-hivelog-api-submodule-scaffold]] and the rest of
  [[ios-field-app]]'s tasks.
