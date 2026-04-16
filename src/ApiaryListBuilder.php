<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Apiary entities.
 */
class ApiaryListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['location'] = $this->t('Location');
    $header['owner'] = $this->t('Owner');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink();
    $row['location'] = $entity->get('location')->value ? mb_strimwidth($entity->get('location')->value, 0, 60, '...') : '';
    $row['owner'] = $entity->getOwner() ? $entity->getOwner()->getDisplayName() : '';
    return $row + parent::buildRow($entity);
  }

}
