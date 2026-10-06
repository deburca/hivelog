---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[ios-field-app]]"
area: api
created: 2026-10-06
branch: feature/0215-demo-site-kit
release:
depends-on:
blocked-by:
---
# Task: the field app's role may view the user's own sensor devices and readings

## Context
Found while walking the app against the demo site ([[0215-demo-site-kit]]): the hive page had no
at-a-glance tiles, although the server holds sensor readings. `GET /hivelog/api/v1/computed/hive/{hive}/stat-tiles`
returned an empty list for the reviewer and three tiles (weight, temperature, net colony weight)
for an administrator.

Cause: `nanoprobe` builds the tiles only from sensor devices and readings the current user may
*view*, and the `hivelog_field_app` role, which caps what an app token can do, had no sensor
permission at all (`hivelog_api_field_app_permissions()` said so on purpose: "no inventory, product
or sensor permission"). That was right for the API's allow-list, which does not serve sensors, but
it also starved the computed tiles (task 0202) that the app shows. So on **any** site a beekeeper's
phone would have shown no sensor tiles, however many sensors they had.

## Acceptance criteria
- [x] The role holds exactly two sensor permissions, `view own sensor device` and
      `view own sensor reading`, and no other sensor permission (no edit, add, delete or "any")
- [x] They are granted only where `nanoprobe` defines them, and **when `nanoprobe` is installed
      after `hivelog_api`** the role is topped up (`hook_modules_installed`)
- [x] An update hook (`hivelog_api_update_10002`) gives an existing site's role the two permissions,
      leaving a site's own changes alone
- [x] The sensors are still not served as resources on `/hivelog/api/v1` (404)
- [x] Another beekeeper's hive tiles are still refused (403); their sensors are never named in
      one's own tiles
- [x] Tests that fail without the change; phpcs (CI's coder) and phpstan clean
- [x] The demo seed gives the hives components with weights, so Net Colony Weight is a number
- [ ] Released

## Implementation notes
- `hivelog_api.install`: `hivelog_api_field_app_permissions()` adds the two permissions; the role
  sync already grants only permissions that exist, so a site without `nanoprobe` is unchanged.
  `hivelog_api_update_10002()` re-runs the sync.
- `hivelog_api.module`: `hivelog_api_modules_installed()` re-syncs the role when `nanoprobe`
  arrives later (not during a configuration import, which brings its own role).
- **What the permissions open up:** a token can now *read* the user's own sensors through the
  computed tiles, and, since the OAuth provider is global, through the web routes a user could
  already open (`/hivelog/sensor_device/...`), the same reads the signed-in user has. Nothing
  can be written, and nothing new is exposed to other users: the access handlers still scope
  `own` to the apiary owner or a beekeeper member.
- **Tests:** `HivelogApiSensorTilesTest` (3, with `nanoprobe` enabled and the real role): the
  owner's tiles contain both sensors and a net weight (30 kg on the scale less 2 x 5 kg of hive);
  another beekeeper's hive is a 403 and its sensor's name is not in one's own tiles; the sensors are
  404 as resources. `HivelogApiInstallTest`: the least-privilege test now allows exactly the two
  view permissions; installing `nanoprobe` later tops the role up; the update hook adds them and
  keeps a site's own grant. **With the two permissions removed from the role, the owner test fails**
  (no sensor tile).
- `demo/scripts/seed.php`: the weights (floor 2.0, brood box 3.5, honey super 2.5, roof 1.5 kg) and
  purchases, then the components of each hive; Hive 1 is 9.5 kg empty, 12.47 kg net.

## Verification
- `modules/hivelog_api/tests/src/Kernel`: 78 tests before the last fix, then the new class: 3 tests,
  112 assertions, all pass; the rest of the directory passes.
- phpcs with CI's `drupal/coder` ^9 (clean); phpstan with the module's config in cms2 (clean).
- On the demo site: the update ran, the server returns 21.97 kg, 35.22 °C and Net Colony Weight
  12.47 kg for the reviewer, and **the app's hive page shows all three tiles** (iPhone 18 Pro
  simulator).

## Not verified
- The full module suite was not re-run: the change is confined to `hivelog_api`'s install and module
  files, and `nanoprobe` and core did not change.
- On a real beekeeper's site with real sensors. The permission set is the one the web UI's own
  `view own` handlers already apply.

## Related
- Project:: [[ios-field-app]]
- Decisions:: [[0107-mobile-client-api-and-native-ios-app]]
- Commits::
