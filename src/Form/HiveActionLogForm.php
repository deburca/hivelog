<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;

/**
 * Form handler for Hive Action Log add/edit forms.
 */
class HiveActionLogForm extends ContentEntityForm {

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

    // The inspection link is only ever set programmatically by this form's
    // own save() (via the "create inspection" checkbox below) — never
    // edited directly.
    if (isset($form['inspection'])) {
      $form['inspection']['#access'] = FALSE;
    }

    // Optional "also create a hive inspection" checkbox. Gated server-side
    // by the same permission HiveInspectionAccessControlHandler::
    // checkCreateAccess() requires, so it is simply absent for users who
    // could not create the inspection anyway, rather than visible but
    // guaranteed to fail. Its visibility additionally narrows to
    // status = done via #states — a client-side UX nicety only; save()
    // re-validates the actual submitted status server-side regardless of
    // what the client currently shows.
    if ($this->canCreateLinkedInspection() && $this->entity->get('inspection')->isEmpty()) {
      $form['create_inspection'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Also create a hive inspection record for this'),
        '#description' => $this->t('Creates a linked inspection dated today, with a note summarising this report, that you can immediately flesh out with weight, brood, queen and other details.'),
        '#default_value' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="status"]' => ['value' => 'done'],
          ],
        ],
      ];
    }

    $form['hive_action_log_sections'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Action log details'),
      '#weight' => 10,
    ];

    $sections = [
      'hive_action_log_overview' => [
        'title' => $this->t('Overview'),
        'weight' => 0,
        'open' => TRUE,
        'fields' => ['hive', 'calendar_action', 'year', 'status'],
      ],
      'hive_action_log_details' => [
        'title' => $this->t('Details'),
        'weight' => 1,
        'open' => FALSE,
        'fields' => ['week_completed', 'notes', 'create_inspection', 'uid'],
      ],
    ];

    foreach ($sections as $section_key => $section) {
      $form[$section_key] = [
        '#type' => 'details',
        '#title' => $section['title'],
        '#group' => 'hive_action_log_sections',
        '#weight' => $section['weight'],
        '#open' => $section['open'],
      ];
      foreach ($section['fields'] as $field_name) {
        if (isset($form[$field_name])) {
          $form[$field_name]['#group'] = $section_key;
        }
      }
    }

    $this->buildInventoryUsageFields($form, $this->entity, 'hive_action_log_sections');
    $this->buildYieldFields($form, $this->entity, 'hive_action_log_sections');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\hivelog\Entity\HiveActionLog $entity */
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

    // Optionally create a linked hive inspection. Re-validates both the
    // submitted status and the create permission server-side — never
    // trust the checkbox's client-side #states visibility alone.
    if ($form_state->getValue('create_inspection')
      && $this->canCreateLinkedInspection()
      && $entity->get('status')->value === 'done'
      && $entity->get('inspection')->isEmpty()) {
      $inspection = $this->createLinkedInspection($entity);
      if ($inspection) {
        $form_state->setRedirect('entity.hive_inspection.edit_form', ['hive_inspection' => $inspection->id()]);
        return;
      }
    }

    // Redirect to the parent hive view.
    $hive_id = $entity->get('hive')->target_id;
    if ($hive_id) {
      $form_state->setRedirect('entity.hive.canonical', ['hive' => $hive_id]);
    }
    else {
      $form_state->setRedirect('entity.hive.collection');
    }
  }

  /**
   * Whether the current user could create a hive inspection at all.
   *
   * Mirrors HiveInspectionAccessControlHandler::checkCreateAccess(), which
   * only checks these two permissions (create access is not apiary-scoped
   * in this module — only view/update/delete are).
   */
  protected function canCreateLinkedInspection(): bool {
    return $this->currentUser()->hasPermission('add hive inspection')
      || $this->currentUser()->hasPermission('administer hivelog');
  }

  /**
   * Creates a HiveInspection stub linked to the given HiveActionLog.
   *
   * Only `hive` and `inspection_date` are required on HiveInspection, so
   * the stub is valid as-is; the beekeeper is redirected to its edit form
   * to add weight/brood/queen/etc. detail while it's fresh.
   *
   * @param \Drupal\hivelog\Entity\HiveActionLog $log
   *   The just-saved hive action log to link the new inspection to.
   *
   * @return \Drupal\hivelog\Entity\HiveInspection|null
   *   The newly created inspection, or NULL if create access was denied
   *   (defence in depth — the caller should already have checked this).
   */
  protected function createLinkedInspection(HiveActionLog $log): ?HiveInspection {
    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('hive_inspection');
    if (!$access_handler->createAccess(NULL, $this->currentUser())) {
      return NULL;
    }

    $calendar_action = $log->get('calendar_action')->entity;
    $notes = trim((string) $log->get('notes')->value);
    $action_taken = $calendar_action
      ? $calendar_action->label() . ($notes !== '' ? ': ' . $notes : '')
      : $notes;

    /** @var \Drupal\hivelog\Entity\HiveInspection $inspection */
    $inspection = HiveInspection::create([
      'hive' => $log->get('hive')->target_id,
      'inspection_date' => date('Y-m-d'),
      'action_taken' => $action_taken,
    ]);
    $inspection->save();

    $log->set('inspection', $inspection->id());
    $log->save();

    $this->messenger()->addStatus($this->t('A linked hive inspection has been created — add any further details below.'));

    return $inspection;
  }

}
