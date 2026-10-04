<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Inventory Purchase add/edit forms.
 */
class InventoryPurchaseForm extends ContentEntityForm {

  use HivelogEntityFormTrait;
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
