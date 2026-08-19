<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Inventory Item add/edit forms.
 */
class InventoryItemForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['inventory_item_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Inventory item details'),
      '#weight' => 10,
    ];

    $sections = [
      'inventory_item_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['apiary', 'name', 'category', 'unit', 'status'],
      ],
      'inventory_item_depreciation' => [
        'title' => $this->t('Type & Depreciation'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['item_type', 'useful_life_years', 'uid'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'inventory_item_sections',
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

    $item_type = $this->getNullableFieldValue($form_state, 'item_type');
    $useful_life_years = $this->getNullableFieldValue($form_state, 'useful_life_years');
    if ($item_type === 'durable' && $useful_life_years === NULL) {
      $form_state->setErrorByName('useful_life_years', $this->t('Durable items must have a useful life (in years) set.'));
    }
  }

  /**
   * Extracts a scalar value from an entity form field, or NULL if empty.
   *
   * Mirrors the equivalent helper in CalendarActionForm — handles both the
   * `[0]['value']` and flat `['value']` shapes a widget might produce.
   */
  protected function getNullableFieldValue(FormStateInterface $form_state, string $field_name): mixed {
    $value = $form_state->getValue($field_name);
    if (is_array($value)) {
      $value = $value[0]['value'] ?? $value['value'] ?? NULL;
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
      $this->messenger()->addStatus($this->t('Inventory item %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Inventory item %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the parent apiary view.
    $apiary_id = $entity->get('apiary')->target_id;
    if ($apiary_id) {
      $form_state->setRedirect('entity.apiary.canonical', ['apiary' => $apiary_id]);
    }
    else {
      $form_state->setRedirect('entity.apiary.collection');
    }
  }

}
