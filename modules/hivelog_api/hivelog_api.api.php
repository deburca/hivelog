<?php

/**
 * @file
 * Hooks defined by the HiveLog API module.
 */

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hivelog\Entity\Hive;

/**
 * Contributes a hive's latest insight to the API.
 *
 * The same optional-submodule-extends-without-a-dependency pattern as
 * hivelog core's own hooks (see hivelog.api.php and ADR-0099), applied to
 * `GET /hivelog/api/v1/computed/hive/{hive}/insight`: `hivelog_api` defines
 * and invokes this hook and implements nothing here itself, so it never
 * depends on `nexus`. Return plain data, never markup.
 *
 * Implementations are responsible for their own access checks: the hive is
 * already one the current user may view, which does not imply they may see
 * whatever an implementation would return.
 *
 * @param \Drupal\hivelog\Entity\Hive $hive
 *   The hive.
 * @param \Drupal\Core\Cache\CacheableMetadata $cache
 *   Cacheability collector: add whatever the answer depends on.
 *
 * @return array
 *   The insight as data: `verdict` (machine key), `verdict_label`,
 *   `recommendation`, `signals` (a list of strings), `confidence` and
 *   `confidence_label` (or NULL), `generated` (ISO 8601) and `stale` (bool).
 *   An empty array when there is nothing to show.
 */
function hook_hivelog_api_hive_insight(Hive $hive, CacheableMetadata $cache) {
  return [];
}
