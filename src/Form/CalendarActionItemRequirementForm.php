<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Calendar Action Item Requirement add/edit forms.
 */
class CalendarActionItemRequirementForm extends ContentEntityForm {

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

    // Scope the item autocomplete to the calendar action's own apiary —
    // see ApiaryScopedAutocompleteTrait for why this doesn't live-update
    // if the calendar_action field itself is changed afterwards.
    $calendar_action = $this->entity->get('calendar_action')->entity;
    $this->scopeAutocompleteToApiary($form, 'item', $calendar_action ? $calendar_action->get('apiary')->target_id : NULL);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Requirement %label has been added.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Requirement %label has been updated.', [
        '%label' => $entity->label(),
      ]));
    }

    // Redirect to the parent calendar action view, which embeds the
    // requirements table — unlike InventoryItem/InventoryPurchase, this
    // parent page genuinely shows the saved record.
    $calendar_action_id = $entity->get('calendar_action')->target_id;
    if ($calendar_action_id) {
      $form_state->setRedirect('entity.calendar_action.canonical', ['calendar_action' => $calendar_action_id]);
    }
    else {
      $form_state->setRedirect('entity.apiary.collection');
    }
  }

}
