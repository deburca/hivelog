<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the seasonal calendar checklist shared by the Apiary/Hive pages (task 0130).
 *
 * `ApiaryController` and `HiveController` each embedded their own,
 * near-identical copy of this checklist (query enabled calendar actions
 * for the right scope → filter by view access → join the matching
 * action-log rows for the selected year → apply the status filter),
 * plus the filter-extraction, timing-label and empty-message helpers
 * around it. `DashboardController`/`InventoryReportController` each had
 * their own copy of `secondsUntilNextIsoWeek()` / `viewableApiaries()`
 * for the same reason. One service now owns all of it, mirroring
 * `HivelogStatTileBuilder`'s own plain-service shape (task 0110) rather
 * than either controller's.
 */
class HivelogCalendarChecklistBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Extracts and validates the calendar checklist's status/year filters.
   *
   * Defaults to the "unreported, current year" view when the query
   * string is absent or holds an invalid value — this is what makes
   * that the checklist's default view rather than an optional
   * refinement, per ADR-0025. Shared identically by the apiary and
   * hive pages — there was never a real difference between the two
   * copies this replaces.
   *
   * @return array{status: string, year: int}
   *   `status` is one of `pending`/`done`/`ignored`/`all`; `year` is
   *   one of the current year, the previous year, or the next year.
   */
  public function extractCalendarFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query : NULL;
    $current_year = (int) date('Y');

    $status = $query ? (string) $query->get('status', 'pending') : 'pending';
    if (!in_array($status, ['pending', 'done', 'ignored', 'all'], TRUE)) {
      $status = 'pending';
    }

    $year = $query ? (int) $query->get('year', (string) $current_year) : $current_year;
    if (!in_array($year, [$current_year - 1, $current_year, $current_year + 1], TRUE)) {
      $year = $current_year;
    }

    return ['status' => $status, 'year' => $year];
  }

  /**
   * Builds the checklist for one apiary's own apiary-scoped duties.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary to build a checklist for.
   * @param int $year
   *   Which annual occurrence of each calendar action to check against.
   * @param string $status_filter
   *   One of `pending`, `done`, `ignored`, or `all`.
   *
   * @return array{total_enabled: int, rows: array}
   *   See `buildChecklist()`'s own return docblock.
   */
  public function buildForApiary(Apiary $apiary, int $year, string $status_filter): array {
    return $this->buildChecklist(
      'apiary',
      $apiary->id(),
      'apiary_action_log',
      'apiary',
      $apiary->id(),
      $year,
      $status_filter,
    );
  }

  /**
   * Builds the checklist for one hive's hive-scoped duties.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive to build a checklist for.
   * @param int $year
   *   Which annual occurrence of each calendar action to check against.
   * @param string $status_filter
   *   One of `pending`, `done`, `ignored`, or `all`.
   *
   * @return array{total_enabled: int, rows: array}
   *   See `buildChecklist()`'s own return docblock.
   */
  public function buildForHive(Hive $hive, int $year, string $status_filter): array {
    $apiary_id = $hive->get('apiary')->target_id;
    if (!$apiary_id) {
      return ['total_enabled' => 0, 'rows' => []];
    }
    return $this->buildChecklist(
      'hive',
      $apiary_id,
      'hive_action_log',
      'hive',
      $hive->id(),
      $year,
      $status_filter,
    );
  }

  /**
   * Cross-references enabled calendar actions against their action logs.
   *
   * Shared shape behind `buildForApiary()`/`buildForHive()`: query
   * enabled calendar actions matching `$calendar_scope` on the apiary
   * they belong to, filter by view access, then join `$log_entity_type`
   * rows for `$year` owned by `$log_owner_id` (via `$log_field`),
   * defaulting an unreported combination to a synthetic `pending`
   * status. No rows are ever pre-materialised (see ADR-0025); absence
   * of a log, or one with `status = pending`, means "unreported". When
   * multiple logs exist for the same `(owner, calendar_action, year)`
   * (allowed by design), the most recently changed one wins for
   * display purposes.
   *
   * @param string $calendar_scope
   *   `CalendarAction::scope` to match — `'apiary'` or `'hive'`.
   * @param int|string $apiary_id
   *   The apiary whose enabled calendar actions to query — the hive's
   *   own apiary, for `buildForHive()`.
   * @param string $log_entity_type
   *   Either `'apiary_action_log'` or `'hive_action_log'`.
   * @param string $log_field
   *   The log entity's own field naming its owner — `'apiary'` or
   *   `'hive'`.
   * @param int|string $log_owner_id
   *   The apiary's or hive's own ID, matched against `$log_field`.
   * @param int $year
   *   Which annual occurrence of each calendar action to check against.
   * @param string $status_filter
   *   One of `pending`, `done`, `ignored`, or `all`.
   *
   * @return array{total_enabled: int, rows: array}
   *   `total_enabled` is the count of enabled, correctly-scoped calendar
   *   actions on the apiary *before* the status filter is applied —
   *   used to tell "nothing pending" apart from "no calendar actions
   *   exist at all" for the empty-state message. `rows` is an array
   *   keyed by calendar action id, each entry an associative array with
   *   `calendar_action` (the `CalendarAction`), `log` (the matching log
   *   entity, or NULL if unreported), and `status` (the effective
   *   status string used for filtering/display).
   */
  protected function buildChecklist(
    string $calendar_scope,
    int|string $apiary_id,
    string $log_entity_type,
    string $log_field,
    int|string $log_owner_id,
    int $year,
    string $status_filter,
  ): array {
    $calendar_action_ids = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_id)
      ->condition('enabled', TRUE)
      ->condition('scope', $calendar_scope)
      ->sort('week_start', 'ASC')
      ->execute();

    if (!$calendar_action_ids) {
      return ['total_enabled' => 0, 'rows' => []];
    }

    $calendar_actions = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->loadMultiple($calendar_action_ids);
    $calendar_actions = array_filter(
      $calendar_actions,
      fn($calendar_action) => $calendar_action->access('view'),
    );
    $total_enabled = count($calendar_actions);
    if (!$calendar_actions) {
      return ['total_enabled' => $total_enabled, 'rows' => []];
    }

    // Load every log for this owner + year against these calendar
    // actions in one query, then index by calendar_action id —
    // last-changed wins if more than one log exists for the same
    // calendar action.
    $log_ids = $this->entityTypeManager
      ->getStorage($log_entity_type)
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition($log_field, $log_owner_id)
      ->condition('calendar_action', array_keys($calendar_actions), 'IN')
      ->condition('year', $year)
      ->sort('changed', 'ASC')
      ->execute();

    $logs_by_action = [];
    if ($log_ids) {
      foreach ($this->entityTypeManager->getStorage($log_entity_type)->loadMultiple($log_ids) as $log) {
        // Later iterations (sorted ascending by `changed`) overwrite
        // earlier ones, so the most recently changed log wins.
        // Every registered log entity type is content, always
        // FieldableEntityInterface — EntityInterface itself doesn't
        // declare get().
        // @phpstan-ignore-next-line
        $logs_by_action[$log->get('calendar_action')->target_id] = $log;
      }
    }

    $rows = [];
    foreach ($calendar_actions as $calendar_action) {
      $log = $logs_by_action[$calendar_action->id()] ?? NULL;
      // @phpstan-ignore-next-line
      $effective_status = $log ? $log->get('status')->value : 'pending';
      if ($status_filter !== 'all' && $effective_status !== $status_filter) {
        continue;
      }
      $rows[$calendar_action->id()] = [
        'calendar_action' => $calendar_action,
        'log' => $log,
        'status' => $effective_status,
      ];
    }

    return ['total_enabled' => $total_enabled, 'rows' => $rows];
  }

  /**
   * Describes an unreported calendar action's timing versus the current week.
   *
   * `CalendarAction` never wraps across the year boundary (`week_end`
   * must be `>= week_start`, enforced by `CalendarAction::preSave()`),
   * so plain integer comparison is sufficient — no modulo/wraparound
   * arithmetic is needed. Only called for `pending` (unreported) rows,
   * so "Overdue" is always actionable — it can never apply to
   * something already done or ignored.
   *
   * @param int $week_start
   *   The calendar action's start week.
   * @param int|string|null $week_end
   *   The calendar action's end week, or NULL/empty for a single week.
   * @param int $current_week
   *   The current ISO week number to compare against.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   "Upcoming", "Due now", or "Overdue".
   */
  public function pendingActionTimingLabel(int $week_start, $week_end, int $current_week): TranslatableMarkup {
    $effective_end = ($week_end !== NULL && $week_end !== '') ? (int) $week_end : $week_start;

    if ($current_week < $week_start) {
      return $this->t('Upcoming');
    }
    if ($current_week > $effective_end) {
      return $this->t('Overdue');
    }
    return $this->t('Due now');
  }

  /**
   * Builds the empty-state message for the calendar checklist table.
   *
   * Distinguishes "no calendar actions exist at all" from "none match
   * the current status filter", per the task's explicit requirement to
   * tell the two apart. Scope-aware wording, via a literal `match()`
   * rather than a variable-keyed array so every string stays statically
   * extractable (translated strings must be, per Drupal coding
   * standards) — matches `HivelogEntityDeleteForm::detachConsequenceNote()`'s
   * own established pattern for the same reason.
   *
   * Consolidating the apiary and hive copies of this message also fixed
   * a latent copy-paste bug: the hive page's zero-total message read
   * "This apiary has no calendar actions set up yet." — the wrong noun
   * for a hive page. Now correctly says "This hive…".
   *
   * @param string $scope
   *   Either `'apiary'` or `'hive'`.
   * @param int $total_enabled
   *   Count of enabled, correctly-scoped calendar actions on the
   *   apiary, before the status filter is applied (from
   *   `buildForApiary()`/`buildForHive()`).
   * @param string $status_filter
   *   The active status filter (`pending`/`done`/`ignored`/`all`).
   */
  public function emptyMessage(string $scope, int $total_enabled, string $status_filter): TranslatableMarkup {
    if ($total_enabled === 0) {
      return match ($scope) {
        'apiary' => $this->t('This apiary has no apiary-scoped calendar actions set up yet.'),
        'hive' => $this->t('This hive has no calendar actions set up yet.'),
      };
    }

    $messages = match ($scope) {
      'apiary' => [
        'pending' => $this->t('No pending seasonal actions for this apiary.'),
        'done' => $this->t('No actions have been reported as done for this apiary.'),
        'ignored' => $this->t('No actions have been reported as ignored for this apiary.'),
      ],
      'hive' => [
        'pending' => $this->t('No pending seasonal actions for this hive.'),
        'done' => $this->t('No actions have been reported as done for this hive.'),
        'ignored' => $this->t('No actions have been reported as ignored for this hive.'),
      ],
    };

    return $messages[$status_filter] ?? $this->t('No calendar actions match the current filters.');
  }

  /**
   * Seconds remaining until the ISO week changes (next Monday, midnight).
   *
   * Used to bound the cache max-age for any render that surfaces the
   * current week or a week-relative timing label, so a cached page
   * never shows a stale week after the boundary passes.
   *
   * @return int
   *   Seconds until the next ISO week boundary.
   */
  public function secondsUntilNextIsoWeek(): int {
    $now = new \DateTimeImmutable('now');
    $next_boundary = new \DateTimeImmutable('next monday midnight');
    return max(0, $next_boundary->getTimestamp() - $now->getTimestamp());
  }

  /**
   * Loads every apiary `$account` may view, keyed by id.
   *
   * Takes the account explicitly rather than injecting `current_user`
   * itself — the four callers this method used to be duplicated across
   * already have their own current-user access sorted out one of two
   * equivalent ways (an injected `AccountInterface` property, or
   * `ControllerBase::currentUser()`), and passing it in avoids this
   * service needing an opinion on which.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check `view` access for.
   *
   * @return \Drupal\hivelog\Entity\Apiary[]
   *   Viewable apiaries keyed by entity id.
   */
  public function viewableApiaries(AccountInterface $account): array {
    $storage = $this->entityTypeManager->getStorage('apiary');
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $apiaries = $ids ? $storage->loadMultiple($ids) : [];
    return array_filter(
      $apiaries,
      fn($apiary) => $apiary->access('view', $account),
    );
  }

}
