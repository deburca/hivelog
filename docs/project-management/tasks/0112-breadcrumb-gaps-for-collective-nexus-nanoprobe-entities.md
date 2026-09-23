---
type: task
tags: [hivelog/task]
status: done
priority: medium
project: "[[breadcrumb-consistency]]"
area: routing
created: 2026-09-23
completed: 2026-09-23
branch: feature/0112-breadcrumb-gaps-for-collective-nexus-nanoprobe-entities
release:
depends-on:
blocked-by:
---
# Task: Breadcrumb gaps for ApiClient / AiProviderConfig / SensorDevice routes

## Context
User report: `/hivelog/api-clients`, `/hivelog/ai-provider-configs`, and
`/hivelog/sensor-devices` had no proper breadcrumb — the trail stopped
at "Home › HiveLog" with no crumb naming the page itself. Root cause:
`HivelogBreadcrumbBuilder::applies()` matches any route under
`/hivelog/` (so these pages were never silently excluded), but `build()`
never learned about `entity.api_client.*`, `entity.ai_provider_config.*`,
or `entity.sensor_device.*` — none of their collection routes were in
the `$collections` map, and none of their canonical/edit/delete/
sub-page routes had a dedicated ancestry block. The three collective/
nexus/nanoprobe submodules (tasks 0101, 0104, 0105/0106/0110) each
added real user-facing CRUD pages after
[[breadcrumb-consistency]]'s original audit ([[0013-breadcrumb-route-audit]]/
[[0014-implement-breadcrumb-consistency-fixes]]/[[0015-breadcrumb-test-coverage]])
closed, and none of them updated the breadcrumb builder — exactly the
kind of drift that project exists to catch.

Two rounds of live user feedback shaped the final shape of the fix:
1. AiProviderConfig/ApiClient canonical pages initially mirrored
   Apiary's own top-level pattern (skip the collection page entirely,
   go straight from "HiveLog" to the entity's own label — see
   ADR-0057). User feedback: these two should thread through their own
   collection page instead, since (unlike Apiary) they have no other
   natural ancestor and users reach them by browsing the collection.
2. SensorDevice initially threaded through its real structural
   ancestor (Apiary › Hive, when hive-scoped) — the same pattern
   Product/InventoryItem use. User feedback, after clicking through
   from `/hivelog/sensor-devices` to a device: the breadcrumb showed
   the associated hive, not "Sensor Devices" — not what was expected
   coming from that collection page. Switched to the same
   "thread through its own collection" pattern as AiProviderConfig/
   ApiClient, dropping the Apiary/Hive ancestry.

## Acceptance criteria
- [x] `entity.api_client.collection`, `entity.ai_provider_config.collection`,
      `entity.sensor_device.collection` added to `$collections` — fixes
      the three reported pages directly, and (via the existing
      site-wide `entity.<type>.add_form` regex block) their add forms
      too.
- [x] `sensor_device` route parameter: new ancestry block threading
      through `entity.sensor_device.collection` (not Apiary/Hive — see
      Context above), with named terminal crumbs for the full-history
      readings page (task 0110) and the config-download page.
- [x] `ai_provider_config` / `api_client` route parameters: new
      ancestry blocks, each threading through their own collection page
      (`entity.ai_provider_config.collection` / `entity.api_client.collection`);
      `api_client` additionally gets a named terminal crumb for the
      token-regeneration confirmation form.
- [x] Kernel/unit tests: collection + add-form data providers extended;
      new `build()` tests for sensor device canonical (hive-scoped and
      apiary-scoped), the readings and config-download sub-pages, AI
      provider config canonical, API client canonical, and the
      regenerate-token sub-page.
- [x] phpcs clean. Full kernel + unit suite re-run against `cms2`
      (795 tests), no regressions.
- [x] Verified live against `cms2`: all three originally-reported
      collection pages now end "› Sensor Devices" / "› AI Provider
      Configs" / "› API Clients"; their add forms thread to the same
      collection; `entity.sensor_device.canonical`,
      `entity.ai_provider_config.canonical`, and
      `entity.api_client.canonical` all show their own collection as
      the linked ancestor crumb.

## Implementation notes
- Left Apiary's own top-level pattern (no collection-page ancestor)
  unchanged, per ADR-0057 — it sits above the whole apiary/hive/…
  hierarchy, unlike AiProviderConfig/ApiClient/SensorDevice which have
  no other functional parent to show. Also left Product/InventoryItem/
  etc. unchanged (they still thread through their Apiary ancestor, not
  their own collection) — those genuinely have a more specific,
  functional parent than a generic collection listing, which
  SensorDevice's collection-page pattern doesn't contradict so much as
  sit alongside as a different, equally valid case (task 0110's own
  device-management UI is the primary way users reach a device).
- The Apiary/Hive-collapsing-behind-the-theme's-ellipsis symptom the
  user described for SensorDevice turned out to be real (confirmed via
  the quick_silver theme's `breadcrumb.html.twig`, which collapses
  middle crumbs behind decorative `aria-hidden` dots once a trail gets
  long) but was a secondary factor, not the actual complaint — the
  underlying trail was structurally wrong for what the user expected
  coming from the collection page, not just visually truncated.
- Found (not fixed, out of scope): `hivelog.queen.observations_csv` in
  `$non_page_routes` is a forward-looking placeholder for a still-
  backlog task ([[0001-queen-observation-csv-export]]), not stale dead
  code — confirmed via its own test docblock before assuming otherwise.

## Related
- Project:: [[breadcrumb-consistency]]
- Tasks:: [[0013-breadcrumb-route-audit]], [[0014-implement-breadcrumb-consistency-fixes]],
  [[0015-breadcrumb-test-coverage]] (the original audit this extends),
  [[0101-collective-rescope-to-api-client]], [[0104-ai-provider-config-management-ui]],
  [[0110-hive-apiary-stat-tiles]] (the submodule CRUD UIs that introduced
  the gap)
- Commits::
