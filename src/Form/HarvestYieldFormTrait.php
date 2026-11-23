<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Product;

/**
 * Shared harvest-yield integration for HiveActionLogForm/ApiaryActionLogForm.
 *
 * Adds the "Harvest" block to a log's form (ADR-0110, task 0287): a date, a
 * pre-filled, editable quantity per product the action expects
 * (CalendarActionProductYield) or already has a harvest of, and a row to
 * add a product it did not plan for. On save it creates/updates/removes the
 * corresponding HarvestYield rows. Structurally identical to InventoryUsageFormTrait,
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
   * Adds the "Harvest" block: what was taken when the action was done.
   *
   * The same three things as "Record harvest" (ADR-0110, task 0287): a
   * product, a quantity and a date. One quantity per product the action
   * expects, pre-filled with the expected amount; one per product already
   * recorded for this log; and one more row to add a product the action did
   * not plan for, so an expected harvest is a convenience and not a
   * prerequisite. One date for the block: a log is one occasion.
   *
   * The block is offered when the action has an expected harvest, when the
   * log already has a harvest, or when the action is a harvest by category;
   * a varroa treatment does not grow a Harvest tab.
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
    $existing_by_product = $this->existingYieldByProduct($log);
    if (!$yield_recipes && !$existing_by_product && !$this->isHarvestAction($log)) {
      return;
    }
    $done_only = ['visible' => [':input[name="status"]' => ['value' => 'done']]];

    // Product id => [product, default quantity]: expected first, then any
    // harvest already recorded for a product nobody planned.
    $rows = [];
    foreach ($yield_recipes as $yield_recipe) {
      $product = $yield_recipe->get('product')->entity;
      if ($product) {
        $rows[$product->id()] = [$product, $yield_recipe->get('quantity')->value];
      }
    }
    $date = NULL;
    foreach ($existing_by_product as $product_id => $existing) {
      $product = $existing->get('product')->entity;
      if ($product) {
        $rows[$product_id] = [$product, $existing->get('quantity')->value];
      }
      $date ??= $existing->get('harvest_date')->value;
    }

    $field_names = [];
    $form['harvest_yield_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Harvest date'),
      '#default_value' => $date ?: HarvestYield::defaultDateForLog($log),
      '#states' => $done_only,
    ];
    $field_names[] = 'harvest_yield_date';

    foreach ($rows as $product_id => [$product, $default]) {
      $field_name = 'harvest_yield_' . $product_id;
      $form[$field_name] = [
        '#type' => 'number',
        '#title' => $product->label(),
        '#field_suffix' => $product->get('unit')->value,
        '#min' => 0,
        '#step' => 'any',
        '#default_value' => $default,
        '#states' => $done_only,
      ];
      $field_names[] = $field_name;
    }

    // One more product, for what the action did not plan. A plain select and
    // a number: no JavaScript, one extra product a save.
    $others = array_diff_key($this->harvestableProducts($log), $rows);
    if ($others) {
      $form['harvest_yield_new_product'] = [
        '#type' => 'select',
        '#title' => $rows ? $this->t('Another product') : $this->t('Product'),
        '#options' => array_map(
          fn($product) => $this->t('@product (@unit)', [
            '@product' => $product->label(),
            '@unit' => $product->get('unit')->value,
          ]),
          $others,
        ),
        '#empty_option' => $this->t('- None -'),
        '#states' => $done_only,
      ];
      $form['harvest_yield_new_quantity'] = [
        '#type' => 'number',
        '#title' => $this->t('Quantity'),
        '#min' => 0,
        '#step' => 'any',
        '#states' => $done_only,
      ];
      $field_names[] = 'harvest_yield_new_product';
      $field_names[] = 'harvest_yield_new_quantity';
    }

    $form['harvest_yield_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Harvest'),
      '#description' => $yield_recipes
        ? $this->t("Pre-filled from the calendar action's expected harvest — adjust to what was actually taken. Leave a field at 0 to record none.")
        : $this->t('What was taken. Leave a field empty or at 0 to record none.'),
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
   * A quantity is read for every product the action expects, every product
   * already recorded for the log, and the one product added in the block
   * (task 0287). The added product must be one of the apiary's own active
   * products; anything else is ignored. The block's date is written to every
   * row; with no date submitted a new row takes the entity's default and an
   * existing row keeps its own.
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

    // Product id => submitted quantity.
    $submitted = [];
    foreach ($this->yieldRecipesForLog($log) as $yield_recipe) {
      $product_id = $yield_recipe->get('product')->target_id;
      if ($product_id) {
        $submitted[$product_id] = $form_state->getValue('harvest_yield_' . $product_id);
      }
    }
    foreach (array_keys($existing_by_product) as $product_id) {
      $submitted[$product_id] ??= $form_state->getValue('harvest_yield_' . $product_id);
    }
    $new_product = $form_state->getValue('harvest_yield_new_product');
    if ($new_product && !isset($submitted[$new_product]) && isset($this->harvestableProducts($log)[$new_product])) {
      $submitted[$new_product] = $form_state->getValue('harvest_yield_new_quantity');
    }
    if (!$submitted) {
      return;
    }

    $date = $form_state->getValue('harvest_yield_date');
    $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : NULL;

    $yield_storage = $this->entityTypeManager->getStorage('harvest_yield');
    $access_handler = $this->entityTypeManager->getAccessControlHandler('harvest_yield');
    $log_field = $log->getEntityTypeId() === 'hive_action_log' ? 'hive_action_log' : 'apiary_action_log';

    foreach ($submitted as $product_id => $value) {
      $quantity = ($value === '' || $value === NULL) ? 0.0 : (float) $value;
      $existing = $existing_by_product[$product_id] ?? NULL;

      if ($quantity > 0) {
        if ($existing) {
          if ($existing->access('update')) {
            $existing->set('quantity', $quantity);
            if ($date) {
              $existing->set('harvest_date', $date);
            }
            $existing->save();
          }
        }
        elseif ($access_handler->createAccess(NULL, $this->currentUser())) {
          $yield_storage->create([
            'product' => $product_id,
            'quantity' => $quantity,
            $log_field => $log->id(),
          ] + ($date ? ['harvest_date' => $date] : []))->save();
        }
      }
      elseif ($existing && $existing->access('delete')) {
        $existing->delete();
      }
    }
  }

  /**
   * Whether the log's calendar action is a harvest by category.
   */
  protected function isHarvestAction(EntityInterface $log): bool {
    $calendar_action = $log instanceof FieldableEntityInterface ? $log->get('calendar_action')->entity : NULL;
    return $calendar_action instanceof FieldableEntityInterface
      && str_starts_with((string) $calendar_action->get('category')->value, 'harvest');
  }

  /**
   * The products a harvest for this log may name, keyed by product id.
   *
   * The active products of the apiary the log belongs to: a hive log's own
   * apiary stamp (its hive's apiary before it is saved), an apiary log's
   * apiary.
   *
   * @return \Drupal\hivelog\Entity\Product[]
   *   Products, by name.
   */
  protected function harvestableProducts(EntityInterface $log): array {
    if (!$log instanceof FieldableEntityInterface) {
      return [];
    }
    $apiary_id = $log->get('apiary')->target_id;
    if (!$apiary_id && $log->hasField('hive')) {
      $hive = $log->get('hive')->entity;
      $apiary_id = $hive instanceof FieldableEntityInterface ? $hive->get('apiary')->target_id : NULL;
    }
    if (!$apiary_id) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('product');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_id)
      ->condition('status', 'discontinued', '<>')
      ->sort('name')
      ->execute();
    $products = [];
    foreach ($storage->loadMultiple($ids) as $product) {
      if ($product instanceof Product) {
        $products[$product->id()] = $product;
      }
    }
    return $products;
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
