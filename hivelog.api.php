<?php

/**
 * @file
 * Hooks provided by the HiveLog module.
 */

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Contributes a read-only section to the Hive canonical page.
 *
 * Per docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md
 * — the mechanism an optional submodule (e.g. `nanoprobe`, `collective`)
 * uses to add content to `HiveController::view()` without hivelog core
 * ever depending on that submodule. `hivelog` core itself implements
 * nothing here; it only defines and invokes this hook.
 *
 * Implementations are responsible for their own access checks — this
 * hook fires for any hive the current user can already view, but that
 * does not imply they may see whatever an implementation would show
 * here (e.g. a beekeeper with `view own hive` but not `view own sensor
 * device` must still see nothing from `nanoprobe`'s implementation).
 *
 * @param \Drupal\hivelog\Entity\Hive $hive
 *   The hive being displayed.
 *
 * @return array
 *   A render array keyed by a unique, module-prefixed key (e.g.
 *   `nanoprobe_sensors`) to avoid colliding with another
 *   implementation's panel. Each panel should set its own `#weight` to
 *   control where it lands on the page. Return an empty array to
 *   contribute nothing (e.g. no relevant data exists, or the current
 *   user lacks access to it).
 */
function hook_hivelog_hive_view_panels(Hive $hive) {
  return [
    'my_module_panel' => [
      '#type' => 'container',
      '#weight' => 15,
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => t('My Panel'),
      ],
    ],
  ];
}

/**
 * Contributes a read-only section to the Apiary canonical page.
 *
 * The apiary-scoped counterpart to hook_hivelog_hive_view_panels() —
 * see that hook's documentation for the full contract (access is the
 * implementation's own responsibility; keys must be unique and
 * module-prefixed; each panel sets its own `#weight`).
 *
 * @param \Drupal\hivelog\Entity\Apiary $apiary
 *   The apiary being displayed.
 *
 * @return array
 *   A render array keyed by a unique, module-prefixed key.
 */
function hook_hivelog_apiary_view_panels(Apiary $apiary) {
  return [];
}

/**
 * Contributes a read-only section to the Hive's dedicated Insights page.
 *
 * Task 0111: `/hivelog/hive/{hive}` was getting too busy for its own
 * "at a glance" purpose once the AI Insight and Sensors panels grew
 * full trend charts/recommendation text alongside the stat tiles that
 * already summarise them — so those two full panels moved off the main
 * page onto `entity.hive.insights` (`HiveController::insights()`)
 * instead, reached from the stat tiles' own links. Same contract as
 * hook_hivelog_hive_view_panels() in every other respect (access is the
 * implementation's own responsibility; keys must be unique and
 * module-prefixed; each panel sets its own `#weight`) — this is a
 * second, separate injection point on a different page, not a
 * replacement for that hook, which still serves panels meant to stay on
 * the main hive page.
 *
 * @param \Drupal\hivelog\Entity\Hive $hive
 *   The hive being displayed.
 *
 * @return array
 *   A render array keyed by a unique, module-prefixed key. Return an
 *   empty array to contribute nothing.
 */
function hook_hivelog_hive_insights_panels(Hive $hive) {
  return [];
}

/**
 * Contributes rows to the dashboard's "Needs attention" queue.
 *
 * The same optional-submodule-extends-core-without-a-dependency problem
 * as hook_hivelog_hive_view_panels() (see
 * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md),
 * applied to `DashboardController::view()`'s merged-alert-passes queue
 * instead of a canonical page — `hivelog` core defines and invokes this
 * hook; it implements nothing here itself.
 *
 * Implementations are responsible for their own access checks — $apiaries
 * is already filtered to ones the current user may view, but that does
 * not imply access to whatever data an implementation would alert on
 * (e.g. sensor devices need their own `view` access check).
 *
 * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
 *   Every apiary the current user may view, keyed by entity id.
 * @param \Drupal\Core\Cache\CacheableMetadata $cache
 *   Cacheability collector — implementations should add their own cache
 *   tags/dependencies to it, mirroring
 *   `DashboardController::collectLowStockAlerts()`'s own pattern.
 *
 * @return array[]
 *   A list of alert rows, each shaped exactly like
 *   `DashboardController::collectLowStockAlerts()`'s own rows: `severity`
 *   (`critical`|`warning`), `chip` (a short translated label),
 *   `title` (a string), `context` (a render array — build one matching
 *   `.hivelog-attention__ctx` markup, e.g. apiary/hive links + a detail
 *   string), `action_label` + `action_url` (or an `actions` list for a
 *   Done/Ignored-style button group), and `sort` (a `[tier, secondary]`
 *   array — see `DashboardController::attentionTiming()` for the
 *   existing tier numbering: `0` = critical/most urgent, higher = less
 *   urgent).
 */
function hook_hivelog_needs_attention_alerts(array $apiaries, CacheableMetadata $cache) {
  return [];
}

/**
 * Contributes a whole new section to the dashboard, its own landing page.
 *
 * The same optional-submodule-extends-core-without-a-dependency problem
 * as hook_hivelog_hive_view_panels()/hook_hivelog_needs_attention_alerts()
 * (see docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md),
 * applied here to a new page region rather than an existing one — unlike
 * those two hooks, this doesn't feed into an existing merged pass
 * (`DashboardController` has no equivalent of its own to contribute
 * into), so an implementation returns a complete, ready-to-place render
 * array instead of a list of rows. `hivelog` core defines and invokes
 * this hook; it implements nothing here itself.
 *
 * Implementations are responsible for their own access checks — $apiaries
 * is already filtered to ones the current user may view, but that does
 * not imply access to whatever data an implementation would build a
 * section from (e.g. an AI-insight section needs its own per-hive/
 * per-entity `view` access checks).
 *
 * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
 *   Every apiary the current user may view, keyed by entity id.
 * @param \Drupal\Core\Cache\CacheableMetadata $cache
 *   Cacheability collector — implementations should add their own cache
 *   tags/dependencies to it, mirroring
 *   `DashboardController::collectLowStockAlerts()`'s own pattern.
 *
 * @return array
 *   A render array keyed by a unique, module-prefixed key (e.g.
 *   `nexus_ai_insights`) to avoid colliding with another
 *   implementation's section. Each section should set its own `#weight`
 *   to control where it lands on the page. Return an empty array to
 *   contribute nothing (e.g. no relevant data exists, or the current
 *   user lacks access to it) — an empty array renders as nothing, never
 *   an empty-state stub.
 */
function hook_hivelog_dashboard_sections(array $apiaries, CacheableMetadata $cache) {
  return [];
}

/**
 * Contributes destinations to `hivelog`'s in-app secondary navigation.
 *
 * The same optional-submodule-extends-core-without-a-dependency problem
 * as the other hooks in this file, applied here to `hivelog`'s own
 * theme-independent nav strip (task 0105) — `HivelogAppNavBuilder`
 * builds this from `hivelog` core's own 8 built-in destinations plus
 * every implementation of this hook. Unlike the panel/needs-attention/
 * dashboard-section hooks, an implementation returns plain link
 * *descriptors*, not a render array — `hivelog` itself builds the
 * actual link markup and performs the access check (via
 * `\Drupal\Core\Url::access()`) uniformly for every item, built-in or
 * contributed, so implementations don't each need to reimplement route
 * access checking for what's always just a link to one of their own
 * collection pages.
 *
 * @return array
 *   A list of nav item descriptors, each `['title' =>
 *   \Drupal\Core\StringTranslation\TranslatableMarkup, 'url' =>
 *   \Drupal\Core\Url, 'weight' => int]`, keyed by a unique,
 *   module-prefixed key to avoid colliding with another
 *   implementation's item. `hivelog` core's own built-in items use
 *   weights 0–7 (matching `hivelog.links.menu.yml`'s own weights);
 *   start contributed items at 8 or above unless deliberately
 *   interleaving with a specific core item.
 */
function hook_hivelog_app_nav_items() {
  return [
    'my_module_things' => [
      'title' => t('My Things'),
      'url' => Url::fromRoute('entity.my_thing.collection'),
      'weight' => 20,
    ],
  ];
}

/**
 * Contributes a stat tile to the Hive canonical page's summary row.
 *
 * The same optional-submodule-extends-core-without-a-dependency problem
 * as the other hooks in this file, applied here to a small "at a glance"
 * tile row at the top of `HiveController::view()` — mirrors
 * `hook_hivelog_app_nav_items()`'s own reasoning exactly: an
 * implementation returns plain tile *descriptors*, not a render array,
 * so `HivelogStatTileBuilder` can build every tile's markup uniformly
 * (the same `hivelog:stat-tile` component the dashboard's own stat
 * tiles use) and lay them out in one shared grid, regardless of which
 * module contributed each one.
 *
 * Implementations are responsible for their own access checks — this
 * hook fires for any hive the current user can already view, but that
 * does not imply they may see whatever an implementation would show
 * here (e.g. a beekeeper with `view own hive` but not `view own sensor
 * device` must still see nothing from `nanoprobe`'s implementation).
 *
 * @param \Drupal\hivelog\Entity\Hive $hive
 *   The hive being displayed.
 *
 * @return array
 *   A list of tile descriptors, each `['value' => string, 'label' =>
 *   \Drupal\Core\StringTranslation\TranslatableMarkup, 'url' =>
 *   \Drupal\Core\Url, 'sublabel' => string|
 *   \Drupal\Core\StringTranslation\TranslatableMarkup (optional),
 *   'sublabel_variant' => string (optional: default|critical|warning),
 *   'weight' => int (optional)]`, keyed by a unique, module-prefixed key
 *   to avoid colliding with another implementation's tile. Return an
 *   empty array to contribute nothing (e.g. no relevant data exists, or
 *   the current user lacks access to it) — a hive with nothing to show
 *   contributes no tile, never an empty/placeholder one.
 */
function hook_hivelog_hive_stat_tiles(Hive $hive) {
  return [
    'my_module_thing' => [
      'value' => '3',
      'label' => t('My Things'),
      'url' => Url::fromRoute('entity.my_thing.collection'),
      'sublabel' => t('2 need attention'),
      'sublabel_variant' => 'warning',
      'weight' => 10,
    ],
  ];
}

/**
 * Contributes a stat tile to the Apiary canonical page's summary row.
 *
 * The apiary-scoped counterpart to hook_hivelog_hive_stat_tiles() — see
 * that hook's documentation for the full contract.
 *
 * @param \Drupal\hivelog\Entity\Apiary $apiary
 *   The apiary being displayed.
 *
 * @return array
 *   A list of tile descriptors, shaped exactly like
 *   hook_hivelog_hive_stat_tiles()'s own return value.
 */
function hook_hivelog_apiary_stat_tiles(Apiary $apiary) {
  return [];
}

/**
 * @} End of "addtogroup hooks".
 */
