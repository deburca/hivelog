<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Inventory Item entities.
 *
 * A plain, default-table list builder for now — the self-built "Add"
 * heading + hivelog:entity-table SDC upgrade (matching ApiaryListBuilder/
 * QueenListBuilder) is scoped to
 * docs/project-management/tasks/0029-inventory-catalog-and-purchase-ledger-ui.md,
 * not this entity/schema task.
 */
class InventoryItemListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['apiary'] = $this->t('Apiary');
    $header['category'] = $this->t('Category');
    $header['unit'] = $this->t('Unit');
    $header['item_type'] = $this->t('Type');
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

    $category = $entity->get('category')->value;
    $row['category'] = $category
      ? ($entity->get('category')->getSetting('allowed_values')[$category] ?? $category)
      : '';

    $row['unit'] = $entity->get('unit')->value;

    $item_type = $entity->get('item_type')->value;
    $row['item_type'] = $entity->get('item_type')->getSetting('allowed_values')[$item_type] ?? $item_type;

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row + parent::buildRow($entity);
  }

}
