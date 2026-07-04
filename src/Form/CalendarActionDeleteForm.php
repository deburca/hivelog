<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Calendar Action delete forms.
 */
class CalendarActionDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete calendar action %title?', [
      '%title' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $apiary_id = $this->entity->get('apiary')->target_id;
    if ($apiary_id) {
      return new Url('entity.apiary.canonical', ['apiary' => $apiary_id]);
    }
    return new Url('entity.apiary.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $apiary_id = $this->entity->get('apiary')->target_id;
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Calendar action %title has been deleted.', [
      '%title' => $this->entity->label(),
    ]));

    if ($apiary_id) {
      $form_state->setRedirectUrl(new Url('entity.apiary.canonical', ['apiary' => $apiary_id]));
    }
    else {
      $form_state->setRedirectUrl(new Url('entity.apiary.collection'));
    }
  }

}
