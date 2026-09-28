---
type: task
tags: [hivelog/task]
status: review
priority: medium
project: "[[collection-page-filter-coverage]]"
area: routing
created: 2026-09-28
branch: feature/0157-ai-provider-config-and-sensor-device-list-filters
release:
depends-on: ["[[0155-apiary-and-queen-list-filters]]"]
blocked-by:
---
# Task: Filters on the AI Provider Configs and Sensor Devices lists

## Context
The last two of [[collection-page-filter-coverage]]'s seven target
pages — both submodule-owned (`nexus` and `nanoprobe` respectively),
unlike 0155/0156's core-owned entities. Each filter form and its test
live in that submodule's own tree, not core's — same "each module owns
its own" convention every other submodule feature in this codebase
already follows.

## Acceptance criteria
- [x] `nexus`'s `AiProviderConfigFilterForm` (new, in
      `modules/nexus/src/Form/`): **Mode** (select, from
      `ai_provider_config`'s own field `allowed_values`), **Provider**
      (text, `LIKE`) and **Enabled** (select: Yes / No / - Any -, from
      the boolean field).
- [x] `nanoprobe`'s `SensorDeviceFilterForm` (new, in
      `modules/nanoprobe/src/Form/`): **Scope** (Apiary/Hive),
      **Device Type**, **Transport** (selects, from `sensor_device`'s
      own field `allowed_values`) and **Enabled** (select: Yes / No /
      - Any -).
- [x] `AiProviderConfigListBuilder` / `SensorDeviceListBuilder` each
      override `getFilterForm()` / `applyFilters()` / `hasActiveFilters()`
      per the established pattern — both already extend
      `HivelogListBuilder` (core's shared base class), so the same
      three hooks apply unchanged across the module boundary.
- [x] Kernel tests in each submodule's own `tests/src/Kernel/`: at
      least one filter narrows the rows, Reset clears the query
      string, empty-state message distinguishes the two cases.
- [x] Verified live on `cms2`: `/hivelog/ai-provider-configs?enabled=1`
      and `/hivelog/sensor-devices?scope=hive` both narrow correctly,
      with a working Reset.
- [x] phpcs clean; phpstan clean — run across the whole module tree
      (`modules/` included), not just core, per this repo's own CI
      parity rule (AGENTS.md "CI Pipeline").

## Implementation notes
- Key files: `modules/nexus/src/Form/AiProviderConfigFilterForm.php`
  (new), `modules/nexus/src/AiProviderConfigListBuilder.php`,
  `modules/nanoprobe/src/Form/SensorDeviceFilterForm.php` (new),
  `modules/nanoprobe/src/SensorDeviceListBuilder.php`,
  `modules/nexus/tests/src/Kernel/AiProviderConfigFilterTest.php` (new,
  3 tests), `modules/nanoprobe/tests/src/Kernel/SensorDeviceFilterTest.php`
  (new, 3 tests).
- No entity schema change → **no update hook required**.
- A boolean field's own filter (`enabled`) has no `allowed_values` to
  read generically — built its select options literally (`'' => '- Any
  -'`, `'1' => 'Yes'`, `'0' => 'No'`), no existing precedent filtering a
  boolean field in this codebase to follow instead.
- `AiProviderConfigFilterForm`'s `mode` options come straight from
  `AiProviderConfig::MODES` rather than via `entity_field.manager` —
  the constant already *is* the field's `allowed_values` source, so no
  extra service injection was needed for it; `SensorDeviceFilterForm`
  still needs `entity_field.manager` for `scope`/`device_type`/
  `transport`, none of which has a class constant equivalent.
- Both list builders' `formBuilder`/`requestStack` properties are
  `protected` on core's shared `HivelogListBuilder` base class, so both
  submodule subclasses read them directly with no new accessor needed
  — same cross-module-boundary reuse `ApiClientListBuilder` already
  relies on.
- `SensorDeviceFilterForm` is deliberately distinct from the
  pre-existing `SensorReadingFilterForm` (task 0110) — the latter
  filters one specific device's own reading history
  (`entity.sensor_device.readings`), not the device collection; no
  overlap in scope or route despite both living in
  `modules/nanoprobe/src/Form/`.
- phpstan: both new `FormBase` subclasses hit the same `new.static`
  finding tasks 0155/0156 already baselined — same fix (baseline, not
  `new self()`, since `FormBase::create()` legitimately keeps late
  static binding). Baseline regenerated 436 → 438; diffed to confirm
  only those two additions changed.
- Regression check before writing the new tests: ran
  `AiProviderConfigListBuilderAccessTest` (13 tests) and
  `SensorDeviceTest` with the new filter hooks wired in — all green,
  same "generic wiring doesn't change unfiltered behaviour" check
  0155/0156 both did.
- Live-verified on `cms2` — but not on the `kbg` site the other
  filter tasks used: none of the four optional submodules are enabled
  there. Re-checked module status across all three multisite sites and
  found all four (`nanoprobe`/`collective`/`nexus`/`assimilate`)
  enabled on `vdg` (`verdigris.ddev.site`) instead, so verification
  (and its throwaway fixtures/login link) ran there:
  `/hivelog/ai-provider-configs?enabled=1` excluded the disabled
  config; `/hivelog/sensor-devices?scope=hive` excluded the
  apiary-scoped device; both Reset links confirmed. Fixtures deleted
  afterward, confirmed gone via entity queries.
- Per explicit instruction, the full multi-directory phpunit suite was
  **not** run at the end of this task — deferred to run once after
  [[0158-api-client-list-filters]] was also complete, to avoid running
  the ~30-minute full suite twice back to back for two tasks landing
  together. Result: 1,094 tests, 0 failures (up from 1,085 before this
  pair of tasks; +9 new tests — 6 from this task's two new test files,
  3 from 0158's one). Same pre-existing third-party deprecation notices
  as every prior run (Symfony `EventDispatcher`, `key` module attribute
  discovery), unchanged in count.

## Related
- Project:: [[collection-page-filter-coverage]]
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]],
  [[0155-apiary-and-queen-list-filters]]
- Commits::
