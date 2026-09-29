<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Hive Component add/edit forms.
 *
 * Mirrors CalendarActionItemRequirementForm's shape (same-apiary
 * validation, cancel redirect to the parent's canonical page), plus the
 * ADR-0106 §2 Amendment's stock-availability guard and item-picker
 * scoping — deliberately not reusing `ApiaryScopedAutocompleteTrait`
 * for the item field, since that trait hardcodes
 * `default:hivelog_apiary_scoped` as the selection handler; this form
 * needs the dedicated `default:hivelog_hive_component_item` handler
 * instead (see `HiveComponentAvailableItemSelection`).
 */
class HiveComponentForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    // Scope the item autocomplete to the hive's own apiary, and to items
    // with at least one unit still available — excluding this row's own
    // prior quantity from that availability check on an edit, so it
    // doesn't count against itself (see
    // InventoryItem::getAvailableForHiveAssignmentQuantity()). Like
    // ApiaryScopedAutocompleteTrait's own apiary-scoping, this only
    // reflects the hive known at form-build time — not a gap, since the
    // hive-scoped add route (the normal navigation path) always has it,
    // and an edit form always has the entity's current saved value.
    if (isset($form['item']['widget'][0]['target_id'])) {
      $hive = $this->entity->get('hive')->entity;
      $form['item']['widget'][0]['target_id']['#selection_handler'] = 'default:hivelog_hive_component_item';
      $form['item']['widget'][0]['target_id']['#selection_settings']['apiary_id'] = $hive ? $hive->get('apiary')->target_id : NULL;
      $form['item']['widget'][0]['target_id']['#selection_settings']['exclude_hive_component_id'] = $this->entity->isNew() ? NULL : $this->entity->id();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $item_id = $this->getNullableFieldValue($form_state, 'item');
    $hive_id = $this->getNullableFieldValue($form_state, 'hive');
    $quantity = $this->getNullableFieldValue($form_state, 'quantity');

    if ($item_id === NULL) {
      return;
    }
    /** @var \Drupal\hivelog\Entity\InventoryItem|null $item */
    $item = $this->entityTypeManager->getStorage('inventory_item')->load($item_id);
    if (!$item) {
      return;
    }

    if ($hive_id !== NULL) {
      $hive = $this->entityTypeManager->getStorage('hive')->load($hive_id);
      if ($hive && (int) $item->get('apiary')->target_id !== (int) $hive->get('apiary')->target_id) {
        $form_state->setErrorByName('item', $this->t('The selected item must belong to the same apiary as this hive.'));
        return;
      }
    }

    if ($quantity !== NULL) {
      $exclude_id = $this->entity->isNew() ? NULL : (int) $this->entity->id();
      $available = $item->getAvailableForHiveAssignmentQuantity($exclude_id);
      if ((float) $quantity > $available) {
        $form_state->setErrorByName('quantity', $this->t('Only @available of "@item" @is available, not @requested.', [
          '@available' => rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.'),
          '@item' => $item->label(),
          '@is' => $available == 1 ? $this->t('is') : $this->t('are'),
          '@requested' => rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.'),
        ]));
      }
    }
  }

  /**
   * Extracts a scalar value from an entity form field, or NULL if empty.
   *
   * Mirrors the equivalent helper in CalendarActionItemRequirementForm /
   * InventoryPurchaseForm — handles both the
   * `[0]['target_id']`/`[0]['value']` and flat `['value']` shapes a
   * widget might produce.
   */
  protected function getNullableFieldValue(FormStateInterface $form_state, string $field_name): mixed {
    $value = $form_state->getValue($field_name);
    if (is_array($value)) {
      $value = $value[0]['target_id'] ?? $value[0]['value'] ?? $value['value'] ?? NULL;
    }
    if ($value === '' || $value === NULL) {
      return NULL;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Component %label has been added.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Component %label has been updated.', [
        '%label' => $entity->label(),
      ]));
    }

    // Redirect to the parent hive view, which embeds the components
    // table.
    $hive_id = $entity->get('hive')->target_id;
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    else {
      $form_state->setRedirect('entity.hive.collection');
    }
  }

}
