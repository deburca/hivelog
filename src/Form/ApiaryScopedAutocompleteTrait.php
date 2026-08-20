<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

/**
 * Scopes an entity_reference_autocomplete widget to a single apiary.
 *
 * Shared by InventoryPurchaseForm, CalendarActionItemRequirementForm, and
 * CalendarActionProductYieldForm — each computes its own apiary id
 * differently (a direct field vs. via `calendar_action`), but the widget
 * override itself is identical. Pairs with
 * \Drupal\hivelog\Plugin\EntityReferenceSelection\ApiaryScopedSelection.
 *
 * This scopes the widget to whatever apiary the entity has *at form
 * build time* (the pre-filled value on add, the saved value on edit) —
 * it does not live-update if the apiary/calendar_action field is
 * changed afterwards without reloading the form. See
 * docs/project-management/tasks/0041-scope-item-and-product-autocomplete-to-current-apiary.md
 * for why that's an accepted, deliberate limitation rather than a gap:
 * the apiary/calendar_action-scoped add routes (the normal navigation
 * path) always have the apiary known at render time, and edit forms
 * always have the entity's current saved value.
 */
trait ApiaryScopedAutocompleteTrait {

  /**
   * Scopes a reference field's autocomplete widget to one apiary.
   *
   * A no-op if the apiary isn't known yet (e.g. a standalone add form
   * with no apiary/calendar_action pre-filled) or the widget isn't in
   * the expected single-value `entity_autocomplete` shape.
   *
   * @param array $form
   *   The form render array, altered by reference.
   * @param string $field_name
   *   The reference field to scope (e.g. `item`, `product`).
   * @param int|string|null $apiary_id
   *   The apiary id to scope to, or NULL/empty to leave unfiltered.
   */
  protected function scopeAutocompleteToApiary(array &$form, string $field_name, mixed $apiary_id): void {
    if (!$apiary_id || !isset($form[$field_name]['widget'][0]['target_id'])) {
      return;
    }
    $form[$field_name]['widget'][0]['target_id']['#selection_handler'] = 'default:hivelog_apiary_scoped';
    $form[$field_name]['widget'][0]['target_id']['#selection_settings']['apiary_id'] = $apiary_id;
  }

}
