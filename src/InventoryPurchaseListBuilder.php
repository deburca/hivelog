<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list builder for Inventory Purchase entities.
 *
 * A plain, default-table list builder for now — see the note in
 * InventoryItemListBuilder about the hivelog:entity-table SDC upgrade
 * being scoped to task 0029, not this entity/schema task.
 */
class InventoryPurchaseListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['item'] = $this->t('Item');
    $header['apiary'] = $this->t('Apiary');
    $header['purchase_date'] = $this->t('Date');
    $header['quantity'] = $this->t('Quantity');
    $header['unit_price'] = $this->t('Unit Price');
    $header['total_cost'] = $this->t('Total Cost');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $item = $entity->get('item')->entity;
    $row['item'] = $item ? $item->toLink() : '';

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink() : '';

    $row['purchase_date'] = $entity->get('purchase_date')->value ?? '';
    $row['quantity'] = $entity->get('quantity')->value ?? '';
    $row['unit_price'] = $entity->get('unit_price')->value ?? '';
    $row['total_cost'] = $entity->get('total_cost')->value ?? '';

    return $row + parent::buildRow($entity);
  }

}
