<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Hive add/edit forms.
 */
class HiveForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['hive_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Hive details'),
      '#weight' => 10,
    ];

    $sections = [
      'hive_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['name', 'apiary', 'status', 'uid'],
      ],
      'hive_equipment' => [
        'title' => $this->t('Equipment'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['hive_type', 'hive_material'],
      ],
      'hive_colony' => [
        'title' => $this->t('Colony'),
        'weight' => 2,
        'open' => FALSE,
        'fields' => ['bee_breed', 'temperament'],
      ],
      'hive_notes' => [
        'title' => $this->t('Notes & photos'),
        'weight' => 3,
        'open' => FALSE,
        'fields' => ['notes', 'images'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'hive_sections',
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
      $this->messenger()->addStatus($this->t('Hive %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Hive %name has been updated.', [
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
