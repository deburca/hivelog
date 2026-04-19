<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Queen entities.
 *
 * Columns follow issue #51: colour, hive, date of introduction.
 */
class QueenListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Queen');
    $header['colour'] = $this->t('Colour');
    $header['hive'] = $this->t('Hive');
    $header['introduced'] = $this->t('Introduced');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink();

    $colour = $entity->get('queen_colour')->value;
    $row['colour'] = $colour ? ($entity->get('queen_colour')->getSetting('allowed_values')[$colour] ?? $colour) : '';

    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink() : '';

    $row['introduced'] = $entity->get('introduction_date')->value ?? '';

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row + parent::buildRow($entity);
  }

}
