<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a list builder for Queen entities.
 *
 * Columns follow issue #51: colour, hive, date of introduction.
 */
class QueenListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Queen');
    $header['colour'] = $this->t('Colour');
    $header['hive'] = $this->t('Hive');
    $header['introduced'] = $this->t('Introduced');
    $header['status'] = $this->t('Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink()->toString();

    $colour = $entity->get('queen_colour')->value;
    $row['colour'] = $colour ? ($entity->get('queen_colour')->getSetting('allowed_values')[$colour] ?? $colour) : '';

    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink()->toString() : '';

    $row['introduced'] = $entity->get('introduction_date')->value ?? '';

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * Self-built rather than relying on the core Local Actions block, since
   * this page moved onto the site's front-end main menu where that block
   * isn't guaranteed to be placed.
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Queen'),
        'url' => Url::fromRoute('entity.queen.add_form')->toString(),
        'variant' => 'primary',
      ],
    ];
  }

}
