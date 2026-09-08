<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Base list builder for HiveLog entity collection pages.
 *
 * Renders the row "Operations" column as a hivelog:button-group cluster
 * (Edit + Delete, Delete in the danger variant) instead of Drupal core's
 * collapsed dropbutton widget, so every HiveLog collection page matches
 * the flat action-button style already used on the canonical pages, the
 * embedded child lists and the dashboard (ADR-0012, task 0068).
 *
 * Subclasses that render their own table via the hivelog:entity-table SDC
 * call buildOperations() directly for the cell; the ones that keep core's
 * `#type => 'table'` get it automatically through
 * EntityListBuilder::buildRow().
 */
abstract class HivelogListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * A hivelog:button-group cluster in place of core's dropbutton.
   */
  public function buildOperations(EntityInterface $entity) {
    $buttons = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Edit'),
        'url' => $entity->toUrl('edit-form')->toString(),
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      // Core's `#type => 'table'` (used by the plain subclasses) does not
      // pull in the button library on its own.
      '#attached' => ['library' => ['hivelog/buttons']],
    ];
  }

}
