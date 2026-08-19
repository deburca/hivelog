<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Queen Observation add/edit forms.
 */
class QueenObservationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Default observation_date to today for new observations so the user
    // can usually skip straight to the content.
    if ($this->entity->isNew() && $this->entity->get('observation_date')->isEmpty()) {
      $this->entity->set('observation_date', date('Y-m-d'));
    }

    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['observation_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Observation details'),
      '#weight' => 10,
    ];

    $sections = [
      'observation_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['queen', 'observation_date', 'uid'],
      ],
      'observation_assessments' => [
        'title' => $this->t('Assessments'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['health', 'temperament', 'active'],
      ],
      'observation_notes' => [
        'title' => $this->t('Notes & photos'),
        'weight' => 2,
        'open' => FALSE,
        'fields' => ['notes', 'images'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'observation_sections',
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
      $this->messenger()->addStatus($this->t('Queen observation has been recorded.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Queen observation has been updated.'));
    }

    // Redirect to the parent hive when the queen currently belongs to one
    // — observations are shown there, below the inspections table, so
    // that's where the beekeeper came from and where they'll want to keep
    // working. Fall back to the queen's own page, then the queen
    // collection, when there's no hive to return to.
    $queen = $entity->get('queen')->entity;
    $hive_id = $queen ? $queen->get('hive')->target_id : NULL;
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    elseif ($queen) {
      $form_state->setRedirect('entity.queen.canonical', ['queen' => $queen->id()]);
    }
    else {
      $form_state->setRedirect('entity.queen.collection');
    }
  }

}
