<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a list builder for Queen Observation entities.
 *
 * No context-free add route exists for QueenObservation (always added
 * from a queen's own page), so the collection page has no heading action
 * — see AGENTS.md "Routing, controllers and forms".
 */
class QueenObservationListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['date'] = $this->t('Date');
    $header['queen'] = $this->t('Queen');
    $header['health'] = $this->t('Health');
    $header['temperament'] = $this->t('Temperament');
    $header['active'] = $this->t('Active');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['date'] = $entity->toLink($entity->get('observation_date')->value ?: $this->t('N/A'))->toString();

    $queen = $entity->get('queen')->entity;
    $row['queen'] = $queen ? $queen->toLink()->toString() : '';

    $health = $entity->get('health')->value;
    $row['health'] = $health ? ($entity->get('health')->getSetting('allowed_values')[$health] ?? $health) : '';

    $temperament = $entity->get('temperament')->value;
    $row['temperament'] = $temperament ? ($entity->get('temperament')->getSetting('allowed_values')[$temperament] ?? $temperament) : '';

    $row['active'] = $entity->get('active')->value ? $this->t('Yes') : $this->t('No');

    return $row;
  }

}
