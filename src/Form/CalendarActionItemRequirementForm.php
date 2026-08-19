<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Calendar Action Item Requirement add/edit forms.
 */
class CalendarActionItemRequirementForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $item_id = $this->getNullableFieldValue($form_state, 'item');
    $calendar_action_id = $this->getNullableFieldValue($form_state, 'calendar_action');
    if ($item_id !== NULL && $calendar_action_id !== NULL) {
      $item = $this->entityTypeManager->getStorage('inventory_item')->load($item_id);
      $calendar_action = $this->entityTypeManager->getStorage('calendar_action')->load($calendar_action_id);
      if ($item && $calendar_action && (int) $item->get('apiary')->target_id !== (int) $calendar_action->get('apiary')->target_id) {
        $form_state->setErrorByName('item', $this->t('The selected item must belong to the same apiary as this calendar action.'));
      }
    }
  }

  /**
   * Extracts a scalar value from an entity form field, or NULL if empty.
   *
   * Mirrors the equivalent helper in InventoryPurchaseForm — handles both
   * the `[0]['target_id']`/`[0]['value']` and flat `['value']` shapes a
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
      $this->messenger()->addStatus($this->t('Requirement %label has been added.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Requirement %label has been updated.', [
        '%label' => $entity->label(),
      ]));
    }

    // Redirect to the parent calendar action view, which embeds the
    // requirements table — unlike InventoryItem/InventoryPurchase, this
    // parent page genuinely shows the saved record.
    $calendar_action_id = $entity->get('calendar_action')->target_id;
    if ($calendar_action_id) {
      $form_state->setRedirect('entity.calendar_action.canonical', ['calendar_action' => $calendar_action_id]);
    }
    else {
      $form_state->setRedirect('entity.apiary.collection');
    }
  }

}
