<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Scopes InventoryItem/Product autocomplete suggestions.
 *
 * Both `inventory_item` and `product` carry a direct `apiary` entity
 * reference field, so one generic handler covers every field that
 * references either of them (`InventoryPurchase.item`,
 * `CalendarActionItemRequirement.item`,
 * `CalendarActionProductYield.product`) — see
 * docs/project-management/tasks/0041-scope-item-and-product-autocomplete-to-current-apiary.md.
 *
 * Two independent filters, both narrowing what a widget *offers* for new
 * selection only — neither ever touches existing references:
 * - Apiary scoping: requesting code sets `apiary_id` in
 *   `#selection_settings` on the `entity_autocomplete` form element to
 *   activate this; with no `apiary_id` present, suggestions span every
 *   apiary, which is what a standalone add form with no apiary context
 *   yet should still get.
 * - Discontinued-status filtering: always applied, unconditionally —
 *   see docs/project-management/tasks/0043-hide-discontinued-items-and-products-from-selection.md.
 *   Mirrors `CalendarAction.enabled`: hidden going forward, existing
 *   references (and the widget's own current value on an edit form)
 *   are untouched, since `EntityReferenceAutocompleteWidget` reads the
 *   current value straight off the entity rather than through this
 *   query.
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

    $query->condition('status', 'discontinued', '<>');

    return $query;
  }

}
