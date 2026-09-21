<?php

/**
 * @file
 * Hooks provided by the HiveLog module.
 */

use Drupal\Core\Cache\CacheableMetadata;
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
 * @} End of "addtogroup hooks".
 */
