<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a list builder for Inventory Purchase entities.
 *
 * Builds the table using the hivelog:entity-table SDC component and its
 * own "Add Purchase" heading — see InventoryItemListBuilder for the full
 * rationale (mirrors ApiaryListBuilder/QueenListBuilder).
 */
class InventoryPurchaseListBuilder extends HivelogListBuilder {

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
    $header['supplier'] = $this->t('Supplier');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $item = $entity->get('item')->entity;
    $row['item'] = $item ? $item->toLink()->toString() : '';

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';

    $row['purchase_date'] = $entity->get('purchase_date')->value ?? '';
    $row['quantity'] = $entity->get('quantity')->value ?? '';
    $row['unit_price'] = $entity->get('unit_price')->value ?? '';
    $row['total_cost'] = $entity->get('total_cost')->value ?? '';
    $row['supplier'] = $entity->get('supplier')->value ?? '';

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * "Add Purchase" plus a cross-link to Inventory Items — a real
   * workflow shortcut, kept per task 0126's heading cross-link review.
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Purchase'),
        'url' => Url::fromRoute('entity.inventory_purchase.add_form')->toString(),
        'variant' => 'primary',
      ],
      [
        'label' => (string) $this->t('View Inventory Items'),
        'url' => Url::fromRoute('entity.inventory_item.collection')->toString(),
      ],
    ];
  }

}
