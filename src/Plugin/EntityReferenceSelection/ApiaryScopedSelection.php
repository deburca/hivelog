<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Scopes InventoryItem/Product autocomplete suggestions to one apiary.
 *
 * Both `inventory_item` and `product` carry a direct `apiary` entity
 * reference field, so one generic handler covers every field that
 * references either of them (`InventoryPurchase.item`,
 * `CalendarActionItemRequirement.item`,
 * `CalendarActionProductYield.product`) — see
 * docs/project-management/tasks/0041-scope-item-and-product-autocomplete-to-current-apiary.md.
 *
 * Requesting code sets `apiary_id` in `#selection_settings` on the
 * `entity_autocomplete` form element to activate the filter; with no
 * `apiary_id` present, this behaves identically to the parent
 * `DefaultSelection` (unfiltered), which is what a standalone add form
 * with no apiary context yet should still get.
 */
#[EntityReferenceSelection(
  id: "default:hivelog_apiary_scoped",
  label: new TranslatableMarkup("Hivelog: scoped to one apiary"),
  group: "default",
  weight: 0,
  entity_types: ["inventory_item", "product"],
)]
class ApiaryScopedSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $apiary_id = $this->getConfiguration()['apiary_id'] ?? NULL;
    if ($apiary_id) {
      $query->condition('apiary', $apiary_id);
    }

    return $query;
  }

}
