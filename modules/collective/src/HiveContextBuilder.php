<?php

declare(strict_types=1);

namespace Drupal\collective;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;

/**
 * Builds the minimised "hive context" an InsightAgent reads.
 *
 * Backs `GET /hivelog/api/hive-insights/contexts`
 * (task 0090). Exactly the shape
 * docs/project-management/tasks/0085-sensor-less-insight-prototype.md
 * validated and
 * docs/project-management/decisions/0087-ai-insights-hosting-and-privacy-model.md's
 * data-minimisation table governs — this class is the one place that
 * table is actually enforced in code: no hive/apiary name (id only), no
 * location, no `HiveInspection.notes`/`action_taken` free text, no
 * `HarvestYield`/`InventoryUsage` data, no raw sensor readings.
 */
class HiveContextBuilder {

  /**
   * How many of a hive's most recent inspections to include.
   *
   * Not specified numerically by any ADR; 5 is a reasonable "recent
   * history" window — enough for a trend (per
   * [[0085-sensor-less-insight-prototype]]'s own findings, even a
   * handful of structured inspections carry real signal), small enough
   * to stay a genuinely minimised payload.
   */
  protected const RECENT_INSPECTIONS_LIMIT = 5;

  /**
   * How many weeks past its window an action still counts as "recently done".
   *
   * Not specified numerically by any ADR; 4 weeks is a reasonable
   * "still relevant to reason about" window.
   */
  protected const RECENTLY_DONE_WEEKS = 4;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Builds the context object for one hive.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive to build context for.
   *
   * @return array
   *   The context object, ready for `json_encode()`, matching
   *   ADR-0088 §2's exact shape.
   */
  public function buildContext(Hive $hive): array {
    /** @var \Drupal\hivelog\Entity\Apiary $apiary */
    $apiary = $hive->get('apiary')->entity;
    $current_week = (int) date('W');
    $year = (int) date('Y');

    return [
      'hive_id' => (int) $hive->id(),
      'current_week' => $current_week,
      'calendar_status' => $this->computeCalendarStatus($hive, $apiary, $current_week, $year),
      'inspections' => $this->collectRecentInspections($hive),
      'queen' => $this->collectQueenSummary($hive),
    ];
  }

  /**
   * Computes which of this hive's calendar actions are due/overdue/recent.
   *
   * Covers both hive-scoped actions (checked against this hive's own
   * `HiveActionLog`) and apiary-scoped actions (checked against the
   * apiary's `ApiaryActionLog`) — a hive's context includes both, since
   * either kind is something a beekeeper visiting this hive would have
   * in mind.
   *
   * @return array
   *   `['due_this_week' => string[], 'overdue' => string[],
   *   'recently_done' => string[]]` — each a list of calendar action
   *   titles, not ids (plan/recipe copy, not personal data, so no
   *   minimisation concern applies to including it as text).
   */
  protected function computeCalendarStatus(Hive $hive, Apiary $apiary, int $current_week, int $year): array {
    $result = ['due_this_week' => [], 'overdue' => [], 'recently_done' => []];

    $action_storage = $this->entityTypeManager->getStorage('calendar_action');
    $ids = $action_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary->id())
      ->condition('enabled', TRUE)
      ->execute();
    if (empty($ids)) {
      return $result;
    }
    $actions = $action_storage->loadMultiple($ids);

    $apiary_scoped = array_filter($actions, fn($a) => $a->get('scope')->value === 'apiary');
    $hive_scoped = array_filter($actions, fn($a) => $a->get('scope')->value === 'hive');

    if ($apiary_scoped) {
      $logs = $this->indexLogs('apiary_action_log', 'apiary', (int) $apiary->id(), array_keys($apiary_scoped), $year);
      foreach ($apiary_scoped as $action) {
        $this->classifyAction($action, $logs[$action->id()] ?? NULL, $current_week, $result);
      }
    }

    if ($hive_scoped) {
      $logs = $this->indexLogs('hive_action_log', 'hive', (int) $hive->id(), array_keys($hive_scoped), $year);
      foreach ($hive_scoped as $action) {
        $this->classifyAction($action, $logs[$action->id()] ?? NULL, $current_week, $result);
      }
    }

    return $result;
  }

  /**
   * Loads action-log rows for a set of calendar actions, keyed by action id.
   */
  protected function indexLogs(string $log_entity_type, string $parent_field, int $parent_id, array $action_ids, int $year): array {
    $storage = $this->entityTypeManager->getStorage($log_entity_type);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($parent_field, $parent_id)
      ->condition('calendar_action', $action_ids, 'IN')
      ->condition('year', $year)
      ->execute();

    $logs = [];
    foreach ($ids ? $storage->loadMultiple($ids) : [] as $log) {
      $logs[$log->get('calendar_action')->target_id] = $log;
    }
    return $logs;
  }

  /**
   * Sorts one calendar action into $result's due/overdue/recent buckets.
   */
  protected function classifyAction(CalendarAction $action, $log, int $current_week, array &$result): void {
    $status = $log ? $log->get('status')->value : 'pending';
    $week_start = (int) $action->get('week_start')->value;
    $week_end = $this->effectiveWeekEnd($action);

    if ($status === 'pending') {
      if ($current_week > $week_end) {
        $result['overdue'][] = $action->label();
      }
      elseif ($current_week >= $week_start) {
        $result['due_this_week'][] = $action->label();
      }
      return;
    }

    if ($status === 'done' && $current_week >= $week_end && ($current_week - $week_end) <= self::RECENTLY_DONE_WEEKS) {
      $result['recently_done'][] = $action->label();
    }
  }

  /**
   * A calendar action's effective end week.
   *
   * `week_end` if set, else `week_start` itself (a single-week action).
   * Mirrors `DashboardController::effectiveWeekEnd()` exactly; that
   * method is core-private, so this is an independent, identical
   * reimplementation, not shared code.
   */
  protected function effectiveWeekEnd(CalendarAction $action): int {
    $raw = $action->get('week_end')->value;
    return ($raw !== NULL && $raw !== '') ? (int) $raw : (int) $action->get('week_start')->value;
  }

  /**
   * Collects the hive's most recent inspections, structured fields only.
   *
   * Oldest first — a natural reading order for a model looking for a
   * trend across inspections, matching
   * `SensorPanelBuilder`'s own chronological-ascending point ordering.
   *
   * @return array[]
   *   A list of inspection summaries, per ADR-0088 §2's shape.
   */
  protected function collectRecentInspections(Hive $hive): array {
    $storage = $this->entityTypeManager->getStorage('hive_inspection');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive', $hive->id())
      ->sort('inspection_date', 'DESC')
      ->range(0, self::RECENT_INSPECTIONS_LIMIT)
      ->execute();
    if (empty($ids)) {
      return [];
    }

    $summaries = [];
    foreach ($storage->loadMultiple($ids) as $inspection) {
      $timestamp = strtotime((string) $inspection->get('inspection_date')->value);
      $summaries[] = [
        'week' => $timestamp ? (int) date('W', $timestamp) : NULL,
        'population' => $this->emptyToNull($inspection, 'population'),
        'brood_pattern' => $this->emptyToNull($inspection, 'brood_pattern'),
        'honey_stores' => $this->emptyToNull($inspection, 'honey_stores'),
        'pollen_stores' => $this->emptyToNull($inspection, 'pollen_stores'),
        'queen_seen' => (bool) $inspection->get('queen_seen')->value,
        'queen_cells' => (bool) $inspection->get('queen_cells')->value,
        'varroa_check' => (bool) $inspection->get('varroa_check')->value,
        'varroa_count' => $inspection->get('varroa_count')->isEmpty() ? NULL : (int) $inspection->get('varroa_count')->value,
        'disease_signs' => $this->emptyToNull($inspection, 'disease_signs'),
        'supers' => $inspection->get('supers')->isEmpty() ? NULL : (int) $inspection->get('supers')->value,
        'weight_kg' => $inspection->get('weight')->isEmpty() ? NULL : (float) $inspection->get('weight')->value,
      ];
    }

    return array_reverse($summaries);
  }

  /**
   * Returns a field's string value, or NULL if it's empty.
   */
  protected function emptyToNull($entity, string $field_name): ?string {
    return $entity->get($field_name)->isEmpty() ? NULL : $entity->get($field_name)->value;
  }

  /**
   * Summarises the hive's active queen, if any.
   *
   * @return array|null
   *   `['queen_year' => int|null, 'status' => string]`, or NULL if the
   *   hive has no active queen recorded.
   */
  protected function collectQueenSummary(Hive $hive): ?array {
    $queen = $hive->getActiveQueen();
    if (!$queen) {
      return NULL;
    }

    return [
      'queen_year' => $queen->get('queen_year')->isEmpty() ? NULL : (int) $queen->get('queen_year')->value,
      'status' => $queen->get('status')->value,
    ];
  }

}
