<?php

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Hive delete forms.
 */
class HiveDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete hive %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.hive.canonical', ['hive' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $apiary_id = $this->entity->get('apiary')->target_id;
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Hive %name has been deleted.', [
      '%name' => $this->entity->label(),
    ]));

    if ($apiary_id) {
      $form_state->setRedirectUrl(new Url('entity.apiary.canonical', ['apiary' => $apiary_id]));
    }
    else {
      $form_state->setRedirectUrl(new Url('entity.apiary.collection'));
    }
  }

}
