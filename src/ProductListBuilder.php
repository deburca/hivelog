<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a list builder for Product entities.
 *
 * Builds the table using the hivelog:entity-table SDC component (rather
 * than the inherited #type => 'table') and its own "Add Product" heading,
 * matching every other list page in the module — see
 * InventoryItemListBuilder::render().
 */
class ProductListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['apiary'] = $this->t('Apiary');
    $header['unit'] = $this->t('Unit');
    $header['expected_unit_price'] = $this->t('Expected Unit Price');
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

    $row['unit'] = $entity->get('unit')->value;

    $price = $entity->get('expected_unit_price')->value;
    $row['expected_unit_price'] = $price !== NULL && $price !== '' ? number_format((float) $price, 2) : '';

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Product'),
        'url' => Url::fromRoute('entity.product.add_form')->toString(),
        'variant' => 'primary',
      ],
    ];
  }

}
