<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a list builder for Calendar Action entities.
 *
 * The registered handler for `entity.calendar_action`, but the live
 * `/hivelog/calendar-actions` route (`entity.calendar_action.collection`)
 * is `CalendarActionController::collection()` instead, for its filter
 * form — checked as part of task 0126 and kept: this class is still
 * reachable through the generic entity API (and is exercised directly by
 * `HiveCalendarChecklistTest`, which compares the controller's
 * scope/enabled-filtered checklist view against this list builder's
 * unfiltered "management view" of every calendar action). Shows every
 * row regardless of the `enabled` flag (with a visible "Disabled"
 * indicator) so a beekeeper can find and re-enable a calendar action
 * that isn't currently appearing on the apiary/hive views.
 *
 * No context-free add route exists for CalendarAction (always added from
 * an apiary's own page), so the collection page has no heading action —
 * see AGENTS.md "Routing, controllers and forms".
 */
class CalendarActionListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Title');
    $header['apiary'] = $this->t('Apiary');
    $header['scope'] = $this->t('Scope');
    $header['category'] = $this->t('Category');
    $header['weeks'] = $this->t('Week(s)');
    $header['enabled'] = $this->t('Enabled');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = $entity->toLink()->toString();

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';

    $scope = $entity->get('scope')->value;
    $row['scope'] = $scope
      ? ($entity->get('scope')->getSetting('allowed_values')[$scope] ?? $scope)
      : '';

    $category = $entity->get('category')->value;
    $row['category'] = $category
      ? ($entity->get('category')->getSetting('allowed_values')[$category] ?? $category)
      : '';

    $week_start = $entity->get('week_start')->value;
    $week_end = $entity->get('week_end')->value;
    if ($week_end !== NULL && $week_end !== '' && (int) $week_end !== (int) $week_start) {
      $row['weeks'] = $this->t('@start–@end', ['@start' => $week_start, '@end' => $week_end]);
    }
    else {
      $row['weeks'] = (string) $week_start;
    }

    $row['enabled'] = $entity->get('enabled')->value ? $this->t('Yes') : $this->t('Disabled');

    return $row;
  }

}
