<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Inventory Purchase add/edit forms.
 */
class InventoryPurchaseForm extends ContentEntityForm {

  use ApiaryScopedAutocompleteTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    // total_cost is auto-derived in InventoryPurchase::preSave() — never
    // shown as an editable widget.
    if (isset($form['total_cost'])) {
      $form['total_cost']['#access'] = FALSE;
    }

    // Scope the item autocomplete to this purchase's own apiary — see
    // ApiaryScopedAutocompleteTrait for why this doesn't live-update if
    // the apiary field itself is changed afterwards.
    $this->scopeAutocompleteToApiary($form, 'item', $this->entity->get('apiary')->target_id);

    $form['inventory_purchase_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Purchase details'),
      '#weight' => 10,
    ];

    $sections = [
      'inventory_purchase_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['apiary', 'item', 'purchase_date'],
      ],
      'inventory_purchase_cost' => [
        'title' => $this->t('Cost'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['quantity', 'unit_price', 'supplier'],
      ],
      'inventory_purchase_disposal' => [
        'title' => $this->t('Disposal'),
        'weight' => 2,
        'open' => FALSE,
        'fields' => ['disposal_date', 'disposal_reason'],
      ],
      'inventory_purchase_notes' => [
        'title' => $this->t('Notes'),
        'weight' => 3,
        'open' => FALSE,
        'fields' => ['notes', 'uid'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'inventory_purchase_sections',
        '#weight' => $section['weight'],
        '#open' => $section['open'],
      ];
      foreach ($section['fields'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#group'] = $section_key;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $item_id = $this->getNullableFieldValue($form_state, 'item');
    $apiary_id = $this->getNullableFieldValue($form_state, 'apiary');
    $item = $item_id !== NULL ? $this->entityTypeManager->getStorage('inventory_item')->load($item_id) : NULL;
    if ($item && $apiary_id !== NULL && (int) $item->get('apiary')->target_id !== (int) $apiary_id) {
      $form_state->setErrorByName('item', $this->t('The selected item must belong to the same apiary as this purchase.'));
    }

    // Read the massaged, string-formatted date values off a freshly built
    // entity clone rather than $form_state->getValue() directly — the
    // datetime widget's raw form value is a DrupalDateTime object at this
    // point, not yet the 'Y-m-d' string WidgetInterface::extractFormValues()
    // produces, and buildEntity() is what runs that massaging.
    $entity = $this->buildEntity($form, $form_state);
    $disposal_date = $entity->get('disposal_date')->value;
    if ($disposal_date) {
      if ($item && $item->get('item_type')->value !== 'durable') {
        $form_state->setErrorByName('disposal_date', $this->t('A disposal date can only be recorded for a purchase of a durable item.'));
      }
      $purchase_date = $entity->get('purchase_date')->value;
      if ($purchase_date && $disposal_date < $purchase_date) {
        $form_state->setErrorByName('disposal_date', $this->t('The disposal date cannot be before the purchase date.'));
      }
    }
  }

  /**
   * Extracts a scalar value from an entity form field, or NULL if empty.
   *
   * Mirrors the equivalent helper in CalendarActionForm — handles both the
   * `[0]['value']`/`[0]['target_id']` and flat `['value']` shapes a widget
   * might produce.
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
      $this->messenger()->addStatus($this->t('Purchase %label has been recorded.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Purchase %label has been updated.', [
        '%label' => $entity->label(),
      ]));
    }

    // Redirect to the purchase collection, not the parent apiary — see
    // the equivalent note in InventoryItemForm::save().
    $form_state->setRedirect('entity.inventory_purchase.collection');
  }

}
