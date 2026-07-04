<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Hive Action Log entities.
 */
class HiveActionLogListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['hive'] = $this->t('Hive');
    $header['calendar_action'] = $this->t('Calendar Action');
    $header['year'] = $this->t('Year');
    $header['status'] = $this->t('Status');
    $header['week_completed'] = $this->t('Week Completed');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $hive = $entity->get('hive')->entity;
    $calendar_action = $entity->get('calendar_action')->entity;

    // Keys must be assigned in the same order as buildHeader() so the
    // rendered table columns line up correctly.
    //
    // The calendar-action column links to this log's own canonical page
    // (there is no other natural "primary" text field to hang the link
    // off); the hive column separately links to the hive itself, mirroring
    // QueenObservationListBuilder's date/queen split.
    $row['hive'] = $hive ? $hive->toLink() : '';
    $row['calendar_action'] = $entity->toLink($calendar_action ? $calendar_action->label() : $this->t('Unknown action'));

    $row['year'] = (string) $entity->get('year')->value;

    $status = $entity->get('status')->value;
    $row['status'] = $status
      ? ($entity->get('status')->getSetting('allowed_values')[$status] ?? $status)
      : '';

    $week_completed = $entity->get('week_completed')->value;
    $row['week_completed'] = ($week_completed !== NULL && $week_completed !== '')
      ? (string) $week_completed
      : '';

    return $row + parent::buildRow($entity);
  }

}
