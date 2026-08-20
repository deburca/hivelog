<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Shared harvest-yield integration for HiveActionLogForm/ApiaryActionLogForm.
 *
 * Adds pre-filled, editable "how much did you actually produce" quantity
 * fields — one per CalendarActionProductYield on the log's calendar
 * action — and, on save, creates/updates/removes the corresponding
 * HarvestYield rows. Structurally identical to InventoryUsageFormTrait,
 * one level removed (outputs instead of inputs); kept as a deliberate
 * parallel sibling rather than unifying the two into one generic trait,
 * consistent with how HiveActionLog/ApiaryActionLog are themselves
 * parallel siblings rather than a shared polymorphic base.
 *
 * Both traits are used together on the same two forms — a harvest action
 * can need items (jars, via InventoryUsageFormTrait) and yield products
 * (honey, via this trait) at once — so field-name prefixes
 * (`harvest_yield_*` vs. `inventory_usage_*`) and fieldset #weights are
 * kept distinct to avoid collision.
 *
 * Requires the consuming form to be a ContentEntityForm (for
 * $this->entityTypeManager and $this->t()).
 */
trait HarvestYieldFormTrait {

  /**
   * Adds the "Yield Produced" fieldset, if any yield recipe rows exist.
   *
   * @param array $form
   *   The form render array, altered by reference.
   * @param \Drupal\Core\Entity\EntityInterface $log
   *   The HiveActionLog or ApiaryActionLog entity being edited.
   * @param string $vertical_tabs_group
   *   The #group name of the form's vertical_tabs element, so the new
   *   fieldset joins the same tab set as the rest of the form.
   */
  protected function buildYieldFields(array &$form, EntityInterface $log, string $vertical_tabs_group): void {
    $yield_recipes = $this->yieldRecipesForLog($log);
    if (!$yield_recipes) {
      return;
    }

    $existing_by_product = $this->existingYieldByProduct($log);

    $field_names = [];
    foreach ($yield_recipes as $yield_recipe) {
      $product = $yield_recipe->get('product')->entity;
      $product_id = $product->id();
      $default = isset($existing_by_product[$product_id])
        ? $existing_by_product[$product_id]->get('quantity')->value
        : $yield_recipe->get('quantity')->value;

      $field_name = 'harvest_yield_' . $product_id;
      $form[$field_name] = [
        '#type' => 'number',
        '#title' => $product->label(),
        '#field_suffix' => $product->get('unit')->value,
        '#min' => 0,
        '#step' => 'any',
        '#default_value' => $default,
        '#states' => [
          'visible' => [
            ':input[name="status"]' => ['value' => 'done'],
          ],
        ],
      ];
      $field_names[] = $field_name;
    }

    $form['harvest_yield_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Yield Produced'),
      '#description' => $this->t('Pre-filled from the calendar action\'s expected yield — adjust to what was actually produced. Leave a field at 0 to record none produced.'),
      '#group' => $vertical_tabs_group,
      '#weight' => 3,
      '#open' => FALSE,
    ];
    foreach ($field_names as $field_name) {
      $form[$field_name]['#group'] = 'harvest_yield_fieldset';
    }
  }

  /**
   * Creates/updates/removes HarvestYield rows to match submitted quantities.
   *
   * Called after the log itself has been saved. Re-validates the
   * submitted status server-side — never trusts the fields' #states
   * client-side visibility alone, matching InventoryUsageFormTrait::
   * syncInventoryUsage()'s discipline. If the log is no longer `done`,
   * any previously recorded yield for it is removed entirely, since it
   * no longer represents a real production event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $log
   *   The just-saved HiveActionLog or ApiaryActionLog.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   */
  protected function syncHarvestYield(EntityInterface $log, FormStateInterface $form_state): void {
    $existing_by_product = $this->existingYieldByProduct($log);

    if ($log->get('status')->value !== 'done') {
      foreach ($existing_by_product as $yield) {
        if ($yield->access('delete')) {
          $yield->delete();
        }
      }
      return;
    }

    $yield_recipes = $this->yieldRecipesForLog($log);
    if (!$yield_recipes) {
      return;
    }

    $yield_storage = $this->entityTypeManager->getStorage('harvest_yield');
    $access_handler = $this->entityTypeManager->getAccessControlHandler('harvest_yield');
    $log_field = $log->getEntityTypeId() === 'hive_action_log' ? 'hive_action_log' : 'apiary_action_log';

    foreach ($yield_recipes as $yield_recipe) {
      $product = $yield_recipe->get('product')->entity;
      $product_id = $product->id();
      $submitted = $form_state->getValue('harvest_yield_' . $product_id);
      $quantity = ($submitted === '' || $submitted === NULL) ? 0.0 : (float) $submitted;
      $existing = $existing_by_product[$product_id] ?? NULL;

      if ($quantity > 0) {
        if ($existing) {
          if ($existing->access('update')) {
            $existing->set('quantity', $quantity);
            $existing->save();
          }
        }
        elseif ($access_handler->createAccess(NULL, $this->currentUser())) {
          $yield_storage->create([
            'product' => $product_id,
            'quantity' => $quantity,
            $log_field => $log->id(),
          ])->save();
        }
      }
      elseif ($existing && $existing->access('delete')) {
        $existing->delete();
      }
    }
  }

  /**
   * Loads the yield-recipe rows for a log's calendar action.
   *
   * @return \Drupal\hivelog\Entity\CalendarActionProductYield[]
   *   Yield recipe rows, keyed by their own entity id.
   */
  protected function yieldRecipesForLog(EntityInterface $log): array {
    $calendar_action = $log->get('calendar_action')->entity;
    if (!$calendar_action) {
      return [];
    }

    $yield_storage = $this->entityTypeManager->getStorage('calendar_action_product_yield');
    $yield_ids = $yield_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('calendar_action', $calendar_action->id())
      ->execute();
    if (!$yield_ids) {
      return [];
    }

    return $yield_storage->loadMultiple($yield_ids);
  }

  /**
   * Loads existing HarvestYield rows for a log, keyed by product id.
   *
   * @return \Drupal\hivelog\Entity\HarvestYield[]
   *   Existing yield rows, keyed by their product's entity id.
   */
  protected function existingYieldByProduct(EntityInterface $log): array {
    if ($log->isNew()) {
      return [];
    }

    $log_field = $log->getEntityTypeId() === 'hive_action_log' ? 'hive_action_log' : 'apiary_action_log';
    $yield_storage = $this->entityTypeManager->getStorage('harvest_yield');
    $yield_ids = $yield_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($log_field, $log->id())
      ->execute();
    if (!$yield_ids) {
      return [];
    }

    $by_product = [];
    foreach ($yield_storage->loadMultiple($yield_ids) as $yield) {
      $by_product[$yield->get('product')->target_id] = $yield;
    }
    return $by_product;
  }

}
