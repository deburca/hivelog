<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\Entity\InventoryItem;

/**
 * Scopes HiveComponent.item's autocomplete to items with available stock.
 *
 * ADR-0106 §2 Amendment: a HiveComponent can never be assigned past how
 * many units of that catalog item actually exist. "Available > 0" is a
 * computed value (purchased minus already-assigned), not a stored field,
 * so it can't be expressed as an entity-query `condition()` the way
 * `ApiaryScopedSelection`'s own apiary/discontinued filters are —
 * extends that plugin for its existing query scoping, then post-filters
 * the loaded result set instead.
 *
 * Deliberately a separate plugin, not folded into `ApiaryScopedSelection`
 * itself: that plugin is shared by `InventoryPurchaseForm`,
 * `CalendarActionItemRequirementForm` and `CalendarActionProductYieldForm`,
 * where an availability-for-hive-assignment filter would be actively
 * wrong (recording a purchase, or a calendar-action requirement, must
 * never be blocked by how much of an item is already built into a hive).
 */
#[EntityReferenceSelection(
  id: "default:hivelog_hive_component_item",
  label: new TranslatableMarkup("Hivelog: hive component items with available stock"),
  group: "default",
  weight: 0,
  entity_types: ["inventory_item"],
)]
class HiveComponentAvailableItemSelection extends ApiaryScopedSelection {

  /**
   * {@inheritdoc}
   *
   * Post-filters out any item with zero (or negative) units available and
   * appends an "(N available)" suffix to each remaining option's label —
   * the same inline-hint idiom `InventoryItemController::view()`'s
   * existing "(Low Stock)" suffix already establishes, extended to a new
   * context.
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $options = parent::getReferenceableEntities($match, $match_operator, $limit);
    if (!$options) {
      return $options;
    }

    $exclude_id = $this->getConfiguration()['exclude_hive_component_id'] ?? NULL;
    $exclude_id = $exclude_id !== NULL ? (int) $exclude_id : NULL;

    // array_values() first: $options is keyed by bundle name (a string),
    // and spreading a string-keyed array into a variadic call passes
    // each element as a *named* argument in PHP 8.1+ — array_merge()
    // doesn't declare parameters with arbitrary bundle names, so it
    // throws ArgumentCountError without this.
    $item_ids = array_merge(...array_values(array_map('array_keys', $options)));
    $items = $this->entityTypeManager->getStorage('inventory_item')->loadMultiple($item_ids);

    $filtered = [];
    foreach ($options as $bundle => $bundle_options) {
      foreach ($bundle_options as $item_id => $label) {
        /** @var \Drupal\hivelog\Entity\InventoryItem|null $item */
        $item = $items[$item_id] ?? NULL;
        if (!$item instanceof InventoryItem) {
          continue;
        }
        $available = $item->getAvailableForHiveAssignmentQuantity($exclude_id);
        if ($available <= 0) {
          continue;
        }
        $available_display = rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.');
        $filtered[$bundle][$item_id] = $label . ' ' . Html::escape('(' . $available_display . ' available)');
      }
    }

    return $filtered;
  }

}
