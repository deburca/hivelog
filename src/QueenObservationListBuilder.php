<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Queen Observation entities.
 */
class QueenObservationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['date'] = $this->t('Date');
    $header['queen'] = $this->t('Queen');
    $header['health'] = $this->t('Health');
    $header['temperament'] = $this->t('Temperament');
    $header['active'] = $this->t('Active');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['date'] = $entity->toLink($entity->get('observation_date')->value ?: $this->t('N/A'));

    $queen = $entity->get('queen')->entity;
    $row['queen'] = $queen ? $queen->toLink() : '';

    $health = $entity->get('health')->value;
    $row['health'] = $health ? ($entity->get('health')->getSetting('allowed_values')[$health] ?? $health) : '';

    $temperament = $entity->get('temperament')->value;
    $row['temperament'] = $temperament ? ($entity->get('temperament')->getSetting('allowed_values')[$temperament] ?? $temperament) : '';

    $row['active'] = $entity->get('active')->value ? $this->t('Yes') : $this->t('No');

    return $row + parent::buildRow($entity);
  }

}
