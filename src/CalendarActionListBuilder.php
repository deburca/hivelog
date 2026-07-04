<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Calendar Action entities.
 *
 * Shows every row regardless of the `enabled` flag (with a visible
 * "Disabled" indicator) so a beekeeper can find and re-enable a
 * calendar action that isn't currently appearing on the apiary/hive
 * views.
 */
class CalendarActionListBuilder extends EntityListBuilder {

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
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = $entity->toLink();

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink() : '';

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

    return $row + parent::buildRow($entity);
  }

}
