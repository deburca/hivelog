<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Calendar Action add/edit forms.
 */
class CalendarActionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['calendar_action_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Calendar action details'),
      '#weight' => 10,
    ];

    $sections = [
      'calendar_action_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['apiary', 'title', 'category', 'enabled', 'scope'],
      ],
      'calendar_action_schedule' => [
        'title' => $this->t('Schedule'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['week_start', 'week_end', 'recurring'],
      ],
      'calendar_action_description' => [
        'title' => $this->t('Description'),
        'weight' => 2,
        'open' => FALSE,
        'fields' => ['description', 'uid'],
      ],
    ];

    if (!$this->entity->isNew()) {
      $sections['calendar_action_yield'] = [
        'title' => $this->t('Products & Yields'),
        'weight' => 3,
        'open' => FALSE,
        'fields' => [],
      ];
    }

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'calendar_action_sections',
        '#weight' => $section['weight'],
        '#open' => $section['open'],
      ];
      foreach ($section['fields'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#group'] = $section_key;
        }
      }
    }

    // Required Items and Expected Yield are separate entities keyed to an
    // existing calendar action id, so they can't live on this form (in
    // particular, not on the add form, before the entity has an id) —
    // point to where they're actually managed instead. See task discussion:
    // long-term these should become their own vertical tab rendered inline;
    // this is the interim pointer.
    if (!$this->entity->isNew()) {
      $form['calendar_action_yield_info'] = [
        '#type' => 'container',
        '#group' => 'calendar_action_yield',
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t("Required Items and Expected Yield are managed from this calendar action's own page, not this edit form."),
        ],
        'link' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Go to Required Items & Expected Yield'),
            'url' => $this->entity->toUrl('canonical')->toString(),
            'variant' => 'primary',
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $week_start = $this->getNullableFieldValue($form_state, 'week_start');
    $week_end = $this->getNullableFieldValue($form_state, 'week_end');
    if ($week_start !== NULL && $week_end !== NULL && (int) $week_end < (int) $week_start) {
      $form_state->setErrorByName('week_end', $this->t('The end week must be the same as or later than the start week.'));
    }
  }

  /**
   * Extracts a scalar value from an entity form field, or NULL if empty.
   *
   * Mirrors the equivalent helper in HiveInspectionForm — handles both the
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
      $this->messenger()->addStatus($this->t('Calendar action %title has been created.', [
        '%title' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Calendar action %title has been updated.', [
        '%title' => $entity->label(),
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
