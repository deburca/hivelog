<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a list builder for Inventory Item entities.
 *
 * Builds the table using the hivelog:entity-table SDC component (rather
 * than the inherited #type => 'table') and its own "Add Inventory Item"
 * heading, matching every other list page in the module — see
 * ApiaryListBuilder::render(). The heading is self-built rather than
 * relying on the core Local Actions block, since this page moved onto the
 * site's front-end main menu where that block isn't guaranteed to be
 * placed.
 */
class InventoryItemListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['apiary'] = $this->t('Apiary');
    $header['category'] = $this->t('Category');
    $header['unit'] = $this->t('Unit');
    $header['item_type'] = $this->t('Type');
    $header['stock'] = $this->t('Stock on Hand');
    $header['status'] = $this->t('Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink()->toString();

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';

    $category = $entity->get('category')->value;
    $row['category'] = $category
      ? ($entity->get('category')->getSetting('allowed_values')[$category] ?? $category)
      : '';

    $row['unit'] = $entity->get('unit')->value;

    $item_type = $entity->get('item_type')->value;
    $row['item_type'] = $entity->get('item_type')->getSetting('allowed_values')[$item_type] ?? $item_type;

    /** @var \Drupal\hivelog\Entity\InventoryItem $entity */
    $stock = $entity->getStockOnHand();
    $row['stock'] = $stock === NULL ? '' : rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . $entity->get('unit')->value;

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * "Add Inventory Item" plus a cross-link to Purchases — a real
   * workflow shortcut (recording a purchase is the very next step after
   * cataloguing an item), kept per task 0126's heading cross-link review.
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Inventory Item'),
        'url' => Url::fromRoute('entity.inventory_item.add_form')->toString(),
        'variant' => 'primary',
      ],
      [
        'label' => (string) $this->t('View Purchases'),
        'url' => Url::fromRoute('entity.inventory_purchase.collection')->toString(),
      ],
    ];
  }

}
