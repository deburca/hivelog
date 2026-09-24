<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a list builder for Hive Inspection entities.
 *
 * No context-free add route exists for HiveInspection (always added from
 * a hive's own page), so the collection page has no heading action — see
 * AGENTS.md "Routing, controllers and forms".
 */
class HiveInspectionListBuilder extends HivelogListBuilder {

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
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['date'] = $entity->toLink($entity->get('inspection_date')->value ?: $this->t('N/A'))->toString();
    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink()->toString() : '';
    $weight = $entity->get('weight')->value;
    $row['weight'] = $weight !== NULL ? $weight . ' kg' : '';
    $row['queen'] = $entity->get('queen_seen')->value ? $this->t('Yes') : $this->t('No');
    $honey = $entity->get('honey_stores')->value;
    $row['honey'] = $honey ? ($entity->get('honey_stores')->getSetting('allowed_values')[$honey] ?? $honey) : '';
    $row['inspector'] = $entity->getOwner() ? $entity->getOwner()->getDisplayName() : '';
    return $row;
  }

}
