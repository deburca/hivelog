<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

/**
 * Form handler for Product delete forms.
 */
class ProductDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   *
   * Appends a warning when this product has historical yield records
   * referencing it — mirrors InventoryItemDeleteForm::getDescription().
   * See
   * docs/project-management/tasks/0045-warn-before-deleting-referenced-items-and-products.md.
   */
  public function getDescription() {
    $count = $this->countHistoricalReferences();
    if ($count === 0) {
      return parent::getDescription();
    }
    return $this->formatPlural(
      $count,
      'This action cannot be undone. This product has 1 historical yield record referencing it — deleting it will make that record show as "Unknown product" wherever it appears.',
      'This action cannot be undone. This product has @count historical yield records referencing it — deleting it will make those records show as "Unknown product" wherever they appear.'
    );
  }

  /**
   * Counts HarvestYield rows referencing this product.
   */
  protected function countHistoricalReferences(): int {
    return (int) $this->entityTypeManager->getStorage('harvest_yield')->getQuery()
      ->accessCheck(FALSE)
      ->condition('product', $this->entity->id())
      ->count()
      ->execute();
  }

}
