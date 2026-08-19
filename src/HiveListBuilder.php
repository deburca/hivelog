<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Hive entities.
 */
class HiveListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Hive');
    $header['apiary'] = $this->t('Apiary');
    $header['breed'] = $this->t('Breed');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink();
    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink() : '';
    // Breed lives on the active queen, not the hive — see
    // Hive::getActiveQueen().
    $queen = $entity->getActiveQueen();
    $breed = $queen ? $queen->get('breed')->value : NULL;
    $row['breed'] = $breed ? ($queen->get('breed')->getSetting('allowed_values')[$breed] ?? $breed) : '';
    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;
    return $row + parent::buildRow($entity);
  }

}
