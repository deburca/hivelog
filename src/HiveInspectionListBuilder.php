<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Hive Inspection entities.
 */
class HiveInspectionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['date'] = $this->t('Date');
    $header['hive'] = $this->t('Hive');
    $header['weight'] = $this->t('Weight');
    $header['queen'] = $this->t('Queen Seen');
    $header['honey'] = $this->t('Honey');
    $header['inspector'] = $this->t('Inspector');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['date'] = $entity->toLink($entity->get('inspection_date')->value ?: $this->t('N/A'));
    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink() : '';
    $weight = $entity->get('weight')->value;
    $row['weight'] = $weight !== NULL ? $weight . ' kg' : '';
    $row['queen'] = $entity->get('queen_seen')->value ? $this->t('Yes') : $this->t('No');
    $honey = $entity->get('honey_stores')->value;
    $row['honey'] = $honey ? ($entity->get('honey_stores')->getSetting('allowed_values')[$honey] ?? $honey) : '';
    $row['inspector'] = $entity->getOwner() ? $entity->getOwner()->getDisplayName() : '';
    return $row + parent::buildRow($entity);
  }

}
