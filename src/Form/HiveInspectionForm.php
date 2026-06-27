<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Hive Inspection add/edit forms.
 */
class HiveInspectionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Apply sensible defaults for new inspections before building widgets so
    // the defaults flow through the standard entity field widgets.
    if ($this->entity->isNew()) {
      $this->applyNewEntityDefaults();
    }

    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['inspection_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Inspection sections'),
      '#weight' => 2,
    ];

    $sections = [
      'overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['hive', 'inspection_date', 'uid'],
      ],
      'external_check_section' => [
        'title' => $this->t('External check'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['external_check'],
      ],
      'queen_status' => [
        'title' => $this->t('Queen status'),
        'weight' => 2,
        'open' => FALSE,
        'fields' => ['queen_seen', 'queen_cells', 'eggs_seen'],
      ],
      'brood_and_stores' => [
        'title' => $this->t('Brood, honey and pollen'),
        'weight' => 3,
        'open' => FALSE,
        'fields' => ['brood_pattern', 'honey_stores', 'pollen_stores'],
      ],
      'colony_condition' => [
        'title' => $this->t('Colony condition'),
        'weight' => 4,
        'open' => FALSE,
        'fields' => ['temperament', 'population'],
      ],
      'health' => [
        'title' => $this->t('Varroa and disease'),
        'weight' => 5,
        'open' => FALSE,
        'fields' => ['varroa_check', 'varroa_count', 'disease_signs'],
      ],
      'management' => [
        'title' => $this->t('Management'),
        'weight' => 6,
        'open' => FALSE,
        'fields' => ['weight', 'fed', 'feed_type', 'supers', 'action_taken'],
      ],
      'notes_section' => [
        'title' => $this->t('Notes'),
        'weight' => 7,
        'open' => FALSE,
        'fields' => ['notes'],
      ],
      'photos_section' => [
        'title' => $this->t('Photos'),
        'weight' => 8,
        'open' => FALSE,
        'fields' => ['images'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'inspection_sections',
        '#weight' => $section['weight'],
        '#open' => $section['open'],
      ];

      foreach ($section['fields'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#group'] = $section_key;
        }
      }
    }

    // Hide dependent fields when their controlling boolean is unchecked so
    // users are not asked for information that is not applicable. With JS
    // disabled these fields stay visible (non-JS safe) and the server
    // normalises irrelevant values on submit.
    if (isset($form['feed_type'])) {
      $form['feed_type']['#states'] = [
        'visible' => [
          ':input[name="fed[value]"]' => ['checked' => TRUE],
        ],
      ];
    }
    if (isset($form['varroa_count'])) {
      $form['varroa_count']['#states'] = [
        'visible' => [
          ':input[name="varroa_check[value]"]' => ['checked' => TRUE],
        ],
      ];
    }

    return $form;
  }

  /**
   * Applies sensible defaults on a fresh inspection entity.
   */
  protected function applyNewEntityDefaults(): void {
    $entity = $this->entity;
    if ($entity->get('inspection_date')->isEmpty()) {
      $entity->set('inspection_date', date('Y-m-d'));
    }
    if ($entity->get('disease_signs')->isEmpty()) {
      $entity->set('disease_signs', 'none');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Normalise values of hidden dependent fields before the rest of the
    // validation runs, so a user who typed a value and then unchecked the
    // controlling field isn't punished for something the UI hides anyway.
    $this->normaliseDependentFields($form_state);

    parent::validateForm($form, $form_state);
    $this->validateDependentFields($form_state);
  }

  /**
   * Clears dependent field values when their controlling field is unchecked.
   */
  protected function normaliseDependentFields(FormStateInterface $form_state): void {
    if (!$this->getBooleanFieldValue($form_state, 'fed')) {
      $this->clearScalarFieldValue($form_state, 'feed_type', '');
    }
    if (!$this->getBooleanFieldValue($form_state, 'varroa_check')) {
      $this->clearScalarFieldValue($form_state, 'varroa_count', NULL);
    }
  }

  /**
   * Validates dependent inspection fields for consistent combinations.
   */
  protected function validateDependentFields(FormStateInterface $form_state): void {
    $fed = $this->getBooleanFieldValue($form_state, 'fed');
    $feed_type = trim($this->getStringFieldValue($form_state, 'feed_type'));
    if ($fed && $feed_type === '') {
      $form_state->setErrorByName(
        'feed_type',
        $this->t('Feed type is required when the colony was fed.')
      );
    }

    $varroa_check = $this->getBooleanFieldValue($form_state, 'varroa_check');
    $varroa_count = $this->getNullableFieldValue($form_state, 'varroa_count');
    if ($varroa_check && $varroa_count === NULL) {
      $form_state->setErrorByName(
        'varroa_count',
        $this->t('Varroa count is required when a varroa check was performed.')
      );
    }
  }

  /**
   * Extracts a boolean value from an entity form field.
   */
  protected function getBooleanFieldValue(FormStateInterface $form_state, string $field_name): bool {
    $value = $form_state->getValue($field_name);
    if (is_array($value)) {
      $value = $value[0]['value'] ?? $value['value'] ?? NULL;
    }
    return (bool) $value;
  }

  /**
   * Extracts a string value from an entity form field.
   */
  protected function getStringFieldValue(FormStateInterface $form_state, string $field_name): string {
    $value = $form_state->getValue($field_name);
    if (is_array($value)) {
      $value = $value[0]['value'] ?? $value['value'] ?? '';
    }
    return (string) ($value ?? '');
  }

  /**
   * Extracts an optional scalar field value.
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
   * Replaces a scalar field value, preserving the original shape.
   */
  protected function clearScalarFieldValue(FormStateInterface $form_state, string $field_name, mixed $cleared): void {
    $current = $form_state->getValue($field_name);
    if (is_array($current)) {
      if (isset($current[0]) && is_array($current[0]) && array_key_exists('value', $current[0])) {
        $current[0]['value'] = $cleared;
      }
      elseif (array_key_exists('value', $current)) {
        $current['value'] = $cleared;
      }
      else {
        $current = [['value' => $cleared]];
      }
      $form_state->setValue($field_name, $current);
    }
    else {
      $form_state->setValue($field_name, $cleared);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Inspection has been recorded.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Inspection has been updated.'));
    }

    // Redirect to the parent hive view.
    $hive_id = $entity->get('hive')->target_id;
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    else {
      $form_state->setRedirect('entity.apiary.collection');
    }
  }

}
