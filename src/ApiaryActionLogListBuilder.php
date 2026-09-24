<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a list builder for Apiary Action Log entities.
 *
 * No context-free add route exists for ApiaryActionLog (always added
 * from an apiary + calendar action context), so the collection page has
 * no heading action — see AGENTS.md "Routing, controllers and forms".
 */
class ApiaryActionLogListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['apiary'] = $this->t('Apiary');
    $header['calendar_action'] = $this->t('Calendar Action');
    $header['year'] = $this->t('Year');
    $header['status'] = $this->t('Status');
    $header['week_completed'] = $this->t('Week Completed');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $apiary = $entity->get('apiary')->entity;
    $calendar_action = $entity->get('calendar_action')->entity;

    // Keys must be assigned in the same order as buildHeader() so the
    // rendered table columns line up correctly.
    //
    // The calendar-action column links to this log's own canonical page
    // (there is no other natural "primary" text field to hang the link
    // off); the apiary column separately links to the apiary itself,
    // mirroring HiveActionLogListBuilder's hive/calendar-action split.
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';
    $row['calendar_action'] = $entity->toLink($calendar_action ? $calendar_action->label() : $this->t('Unknown action'))->toString();

    $row['year'] = (string) $entity->get('year')->value;

    $status = $entity->get('status')->value;
    $row['status'] = $status
      ? ($entity->get('status')->getSetting('allowed_values')[$status] ?? $status)
      : '';

    $week_completed = $entity->get('week_completed')->value;
    $row['week_completed'] = ($week_completed !== NULL && $week_completed !== '')
      ? (string) $week_completed
      : '';

    return $row;
  }

}
