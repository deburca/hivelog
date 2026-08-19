<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Shared inventory-usage integration for HiveActionLogForm/ApiaryActionLogForm.
 *
 * Adds pre-filled, editable "how much did you actually use" quantity
 * fields — one per consumable CalendarActionItemRequirement on the log's
 * calendar action — and, on save, creates/updates/removes the
 * corresponding InventoryUsage rows. Structurally the same shape as
 * HiveActionLogForm::createLinkedInspection() (a "done" report optionally
 * creating related records as a side effect of saving), generalised to
 * cover both log types since neither needs anything hive/apiary-specific
 * here.
 *
 * Requires the consuming form to be a ContentEntityForm (for
 * $this->entityTypeManager and $this->t()).
 */
trait InventoryUsageFormTrait {

  /**
   * Adds the "Inventory Used" fieldset, if any consumable requirements exist.
   *
   * @param array $form
   *   The form render array, altered by reference.
   * @param \Drupal\Core\Entity\EntityInterface $log
   *   The HiveActionLog or ApiaryActionLog entity being edited.
   * @param string $vertical_tabs_group
   *   The #group name of the form's vertical_tabs element, so the new
   *   fieldset joins the same tab set as the rest of the form.
   */
  protected function buildInventoryUsageFields(array &$form, EntityInterface $log, string $vertical_tabs_group): void {
    $requirements = $this->consumableRequirementsForLog($log);
    if (!$requirements) {
      return;
    }

    $existing_by_item = $this->existingUsageByItem($log);

    $field_names = [];
    foreach ($requirements as $requirement) {
      $item = $requirement->get('item')->entity;
      $item_id = $item->id();
      $default = isset($existing_by_item[$item_id])
        ? $existing_by_item[$item_id]->get('quantity')->value
        : $requirement->get('quantity')->value;

      $field_name = 'inventory_usage_' . $item_id;
      $form[$field_name] = [
        '#type' => 'number',
        '#title' => $item->label(),
        '#field_suffix' => $item->get('unit')->value,
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

    $form['inventory_usage_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Inventory Used'),
      '#description' => $this->t('Pre-filled from the calendar action\'s recipe — adjust to what was actually used. Leave a field at 0 to record none used.'),
      '#group' => $vertical_tabs_group,
      '#weight' => 2,
      '#open' => FALSE,
    ];
    foreach ($field_names as $field_name) {
      $form[$field_name]['#group'] = 'inventory_usage_fieldset';
    }
  }

  /**
   * Creates/updates/removes InventoryUsage rows to match submitted quantities.
   *
   * Called after the log itself has been saved. Re-validates the
   * submitted status server-side — never trusts the fields' #states
   * client-side visibility alone, matching createLinkedInspection()'s
   * discipline. If the log is no longer `done`, any previously recorded
   * usage for it is removed entirely, since it no longer represents a
   * real consumption event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $log
   *   The just-saved HiveActionLog or ApiaryActionLog.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   */
  protected function syncInventoryUsage(EntityInterface $log, FormStateInterface $form_state): void {
    $existing_by_item = $this->existingUsageByItem($log);

    if ($log->get('status')->value !== 'done') {
      foreach ($existing_by_item as $usage) {
        if ($usage->access('delete')) {
          $usage->delete();
        }
      }
      return;
    }

    $requirements = $this->consumableRequirementsForLog($log);
    if (!$requirements) {
      return;
    }

    $usage_storage = $this->entityTypeManager->getStorage('inventory_usage');
    $access_handler = $this->entityTypeManager->getAccessControlHandler('inventory_usage');
    $log_field = $log->getEntityTypeId() === 'hive_action_log' ? 'hive_action_log' : 'apiary_action_log';

    foreach ($requirements as $requirement) {
      $item = $requirement->get('item')->entity;
      $item_id = $item->id();
      $submitted = $form_state->getValue('inventory_usage_' . $item_id);
      $quantity = ($submitted === '' || $submitted === NULL) ? 0.0 : (float) $submitted;
      $existing = $existing_by_item[$item_id] ?? NULL;

      if ($quantity > 0) {
        if ($existing) {
          if ($existing->access('update')) {
            $existing->set('quantity', $quantity);
            $existing->save();
          }
        }
        elseif ($access_handler->createAccess(NULL, $this->currentUser())) {
          $usage_storage->create([
            'item' => $item_id,
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
   * Loads the consumable-item requirements for a log's calendar action.
   *
   * @return \Drupal\hivelog\Entity\CalendarActionItemRequirement[]
   *   Requirements whose item is a consumable, keyed by requirement id.
   */
  protected function consumableRequirementsForLog(EntityInterface $log): array {
    $calendar_action = $log->get('calendar_action')->entity;
    if (!$calendar_action) {
      return [];
    }

    $requirement_storage = $this->entityTypeManager->getStorage('calendar_action_item_requirement');
    $requirement_ids = $requirement_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('calendar_action', $calendar_action->id())
      ->execute();
    if (!$requirement_ids) {
      return [];
    }

    $requirements = $requirement_storage->loadMultiple($requirement_ids);
    return array_filter($requirements, function ($requirement) {
      $item = $requirement->get('item')->entity;
      return $item && $item->get('item_type')->value === 'consumable';
    });
  }

  /**
   * Loads existing InventoryUsage rows for a log, keyed by item id.
   *
   * @return \Drupal\hivelog\Entity\InventoryUsage[]
   *   Existing usage rows, keyed by their item's entity id.
   */
  protected function existingUsageByItem(EntityInterface $log): array {
    if ($log->isNew()) {
      return [];
    }

    $log_field = $log->getEntityTypeId() === 'hive_action_log' ? 'hive_action_log' : 'apiary_action_log';
    $usage_storage = $this->entityTypeManager->getStorage('inventory_usage');
    $usage_ids = $usage_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($log_field, $log->id())
      ->execute();
    if (!$usage_ids) {
      return [];
    }

    $by_item = [];
    foreach ($usage_storage->loadMultiple($usage_ids) as $usage) {
      $by_item[$usage->get('item')->target_id] = $usage;
    }
    return $by_item;
  }

}
