<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

/**
 * Form handler for Inventory Item delete forms.
 */
class InventoryItemDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   *
   * Appends a warning when this item has historical purchase/usage
   * records referencing it — deleting doesn't touch those rows (entity
   * reference fields just go empty), but it does blank out the item's
   * name on every past report and log line that used it. Not a hard
   * block: durable catalog cleanup is a legitimate need, and a
   * mistakenly created, never-used item stays a single-step delete with
   * no added friction. See
   * docs/project-management/tasks/0045-warn-before-deleting-referenced-items-and-products.md.
   */
  public function getDescription() {
    $count = $this->countHistoricalReferences();
    if ($count === 0) {
      return parent::getDescription();
    }
    return $this->formatPlural(
      $count,
      'This action cannot be undone. This item has 1 historical purchase/usage record referencing it — deleting it will make that record show as "Unknown item" wherever it appears.',
      'This action cannot be undone. This item has @count historical purchase/usage records referencing it — deleting it will make those records show as "Unknown item" wherever they appear.'
    );
  }

  /**
   * Counts InventoryPurchase/InventoryUsage rows referencing this item.
   */
  protected function countHistoricalReferences(): int {
    $purchase_count = (int) $this->entityTypeManager->getStorage('inventory_purchase')->getQuery()
      ->accessCheck(FALSE)
      ->condition('item', $this->entity->id())
      ->count()
      ->execute();
    $usage_count = (int) $this->entityTypeManager->getStorage('inventory_usage')->getQuery()
      ->accessCheck(FALSE)
      ->condition('item', $this->entity->id())
      ->count()
      ->execute();
    return $purchase_count + $usage_count;
  }

}
