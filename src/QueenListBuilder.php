<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Provides a list builder for Queen entities.
 *
 * Columns follow issue #51: colour, hive, date of introduction.
 */
class QueenListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * Adds its own "Add Queen" heading (matching ApiaryListBuilder's
   * pattern) rather than relying on the core Local Actions block, since
   * this page moved onto the site's front-end main menu where a Local
   * Actions block isn't guaranteed to be placed.
   */
  public function render() {
    $build = parent::render();
    $build['heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => -90,
      '#attached' => ['library' => ['hivelog/buttons']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Queens'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'add' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Add Queen'),
          'url' => Url::fromRoute('entity.queen.add_form')->toString(),
          'variant' => 'primary',
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ],
    ];
    return $build;
  }

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
