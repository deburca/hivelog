<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hivelog\Entity\HiveReminder;

/**
 * Form handler for Hive Inspection add/edit forms.
 */
class HiveInspectionForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

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

    // Next visit (task 0274): a reminder written with the inspection, offered
    // on a new inspection only, so editing one never creates a second.
    if ($this->canAddNextVisit()) {
      $form['next_visit_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Next visit'),
        '#group' => 'inspection_sections',
        '#weight' => 9,
        '#open' => FALSE,
      ];
      $form['next_visit_section']['next_visit_date'] = [
        '#type' => 'date',
        '#title' => $this->t('Remind me on'),
        '#description' => $this->t('Optional. Creates a reminder on this hive for that day, shown on the hive page and in Needs attention.'),
      ];
      $form['next_visit_section']['next_visit_note'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reminder'),
        '#maxlength' => 255,
        '#placeholder' => $this->t('Next visit'),
        '#description' => $this->t('What to do or look for. Left empty, the reminder reads "Next visit".'),
      ];
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

    // The "required when" rules (feed type when fed, varroa count when a
    // check was done) are entity constraints now, run by the parent.
    parent::validateForm($form, $form_state);

    $this->validateNextVisit($form_state);
  }

  /**
   * Refuses a next-visit date before today.
   */
  public function validateNextVisit(FormStateInterface $form_state): void {
    $next_visit = (string) $form_state->getValue('next_visit_date');
    if ($next_visit !== '' && $next_visit < date('Y-m-d')) {
      $form_state->setErrorByName('next_visit_date', $this->t('The next visit cannot be before today.'));
    }
  }

  /**
   * Whether this form offers a next-visit reminder.
   *
   * Only a new inspection offers one, and only to someone who may create a
   * reminder.
   */
  protected function canAddNextVisit(): bool {
    return $this->entity->isNew()
      && $this->entityTypeManager->getAccessControlHandler('hive_reminder')->createAccess();
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
    // Read before saving: the inspection is no longer new afterwards.
    $offer_next_visit = $this->canAddNextVisit();
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Inspection has been recorded.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Inspection has been updated.'));
    }

    $hive_id = $entity->get('hive')->target_id;
    if ($offer_next_visit && $hive_id) {
      $this->createNextVisitReminder((int) $hive_id, $form_state);
    }

    // Redirect to the parent hive view.
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    else {
      $form_state->setRedirect('entity.apiary.collection');
    }
  }

  /**
   * Creates the "Next visit" reminder, if one was asked for.
   *
   * The reminder is validated like any other write, so a hive the user may not
   * update gets a message and no reminder rather than an exception; the
   * inspection itself is already saved.
   */
  protected function createNextVisitReminder(int $hive_id, FormStateInterface $form_state): void {
    $due = (string) $form_state->getValue('next_visit_date');
    if ($due === '') {
      return;
    }
    $title = trim((string) $form_state->getValue('next_visit_note'));
    $reminder = HiveReminder::create([
      'hive' => $hive_id,
      'title' => $title !== '' ? $title : (string) $this->t('Next visit'),
      'due_date' => $due,
    ]);
    if ($reminder->validate()->count() > 0) {
      $this->messenger()->addWarning($this->t('The inspection was saved, but the reminder could not be created.'));
      return;
    }
    $reminder->save();
    $this->messenger()->addStatus($this->t('Reminder set for @date.', ['@date' => $due]));
  }

}
