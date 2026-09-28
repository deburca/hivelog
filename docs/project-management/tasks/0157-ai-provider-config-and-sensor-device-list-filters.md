---
type: task
tags: [hivelog/task]
status: backlog
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
- [ ] `nexus`'s `AiProviderConfigFilterForm` (new, in
      `modules/nexus/src/Form/`): **Mode** (select, from
      `ai_provider_config`'s own field `allowed_values`), **Provider**
      (text, `LIKE`) and **Enabled** (select: Yes / No / - Any -, from
      the boolean field).
- [ ] `nanoprobe`'s `SensorDeviceFilterForm` (new, in
      `modules/nanoprobe/src/Form/`): **Scope** (Apiary/Hive),
      **Device Type**, **Transport** (selects, from `sensor_device`'s
      own field `allowed_values`) and **Enabled** (select: Yes / No /
      - Any -).
- [ ] `AiProviderConfigListBuilder` / `SensorDeviceListBuilder` each
      override `getFilterForm()` / `applyFilters()` / `hasActiveFilters()`
      per the established pattern — both already extend
      `HivelogListBuilder` (core's shared base class), so the same
      three hooks apply unchanged across the module boundary.
- [ ] Kernel tests in each submodule's own `tests/src/Kernel/`: at
      least one filter narrows the rows, Reset clears the query
      string, empty-state message distinguishes the two cases.
- [ ] Verified live on `cms2`: `/hivelog/ai-provider-configs?enabled=1`
      and `/hivelog/sensor-devices?scope=hive` both narrow correctly,
      with a working Reset.
- [ ] phpcs clean; phpstan clean — run across the whole module tree
      (`modules/` included), not just core, per this repo's own CI
      parity rule (AGENTS.md "CI Pipeline").

## Implementation notes
- Key files: `modules/nexus/src/Form/AiProviderConfigFilterForm.php`
  (new), `modules/nexus/src/AiProviderConfigListBuilder.php`,
  `modules/nanoprobe/src/Form/SensorDeviceFilterForm.php` (new),
  `modules/nanoprobe/src/SensorDeviceListBuilder.php`, one new kernel
  test file per submodule.
- No entity schema change → **no update hook required**.
- A boolean field's own filter (`enabled`) has no `allowed_values` to
  read generically — build its select options literally (`'' => '- Any
  -'`, `'1' => 'Yes'`, `'0' => 'No'`), there's no existing precedent
  filtering a boolean field in this codebase to follow instead.

## Related
- Project:: [[collection-page-filter-coverage]]
- Tasks:: [[0132-filters-on-hive-inspection-observation-lists]],
  [[0155-apiary-and-queen-list-filters]]
- Commits::
