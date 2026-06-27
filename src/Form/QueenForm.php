<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Queen add/edit forms.
 */
class QueenForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['queen_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Queen details'),
      '#weight' => 10,
    ];

    $sections = [
      'queen_identity' => [
        'title' => $this->t('Identity'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['name', 'queen_year', 'queen_colour', 'breed', 'temperament'],
      ],
      'queen_hive_status' => [
        'title' => $this->t('Hive & status'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['hive', 'status', 'introduction_date'],
      ],
      'queen_provenance' => [
        'title' => $this->t('Provenance'),
        'weight' => 2,
        'open' => FALSE,
        'fields' => ['origin', 'purchase_cost', 'purchase_date'],
      ],
      'queen_notes' => [
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
        '#group' => 'queen_sections',
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
      $this->messenger()->addStatus($this->t('Queen %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Queen %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the associated hive when the queen is attached to one;
    // otherwise fall back to the global queen collection.
    $hive_id = $entity->get('hive')->target_id;
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    else {
      $form_state->setRedirect('entity.queen.collection');
    }
  }

}
