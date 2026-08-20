<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Calendar Action Product Yield delete forms.
 */
class CalendarActionProductYieldDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete yield %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $calendar_action_id = $this->entity->get('calendar_action')->target_id;
    if ($calendar_action_id) {
      return new Url('entity.calendar_action.canonical', ['calendar_action' => $calendar_action_id]);
    }
    return new Url('entity.apiary.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $calendar_action_id = $this->entity->get('calendar_action')->target_id;
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Yield %label has been deleted.', [
      '%label' => $this->entity->label(),
    ]));

    if ($calendar_action_id) {
      $form_state->setRedirectUrl(new Url('entity.calendar_action.canonical', ['calendar_action' => $calendar_action_id]));
    }
    else {
      $form_state->setRedirectUrl(new Url('entity.apiary.collection'));
    }
  }

}
