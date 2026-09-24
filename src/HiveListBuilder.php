<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a list builder for Hive entities.
 *
 * No context-free add route exists for Hive (always added from an
 * apiary's own page), so the collection page has no heading action — see
 * AGENTS.md "Routing, controllers and forms".
 */
class HiveListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Hive');
    $header['apiary'] = $this->t('Apiary');
    $header['breed'] = $this->t('Breed');
    $header['status'] = $this->t('Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink()->toString();
    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';
    // Breed lives on the active queen, not the hive — see
    // Hive::getActiveQueen().
    $queen = $entity->getActiveQueen();
    $breed = $queen ? $queen->get('breed')->value : NULL;
    $row['breed'] = $breed ? ($queen->get('breed')->getSetting('allowed_values')[$breed] ?? $breed) : '';
    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;
    return $row;
  }

}
