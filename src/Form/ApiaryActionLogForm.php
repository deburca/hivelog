<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Apiary Action Log add/edit forms.
 *
 * Mirrors HiveActionLogForm minus the "also create a hive inspection"
 * checkbox and its supporting logic — there is no apiary-level equivalent
 * of a hive inspection (see ApiaryActionLog's class docblock). Inventory
 * usage integration (InventoryUsageFormTrait) and harvest yield
 * integration (HarvestYieldFormTrait) are both shared with
 * HiveActionLogForm as-is, since neither the fields nor the sync logic
 * need anything hive/apiary-specific.
 */
class ApiaryActionLogForm extends ContentEntityForm {

  use InventoryUsageFormTrait;
  use HarvestYieldFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    $form['apiary_action_log_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Action log details'),
      '#weight' => 10,
    ];

    $sections = [
      'apiary_action_log_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['apiary', 'calendar_action', 'year', 'status'],
      ],
      'apiary_action_log_details' => [
        'title' => $this->t('Details'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['week_completed', 'notes', 'uid'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'apiary_action_log_sections',
        '#weight' => $section['weight'],
        '#open' => $section['open'],
      ];
      foreach ($section['fields'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#group'] = $section_key;
        }
      }
    }

    $this->buildInventoryUsageFields($form, $this->entity, 'apiary_action_log_sections');
    $this->buildYieldFields($form, $this->entity, 'apiary_action_log_sections');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\hivelog\Entity\ApiaryActionLog $entity */
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('%label has been recorded.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('%label has been updated.', [
        '%label' => $entity->label(),
      ]));
    }

    $this->syncInventoryUsage($entity, $form_state);
    $this->syncHarvestYield($entity, $form_state);

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
