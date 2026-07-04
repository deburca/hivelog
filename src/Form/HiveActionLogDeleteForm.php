<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for Hive Action Log delete forms.
 */
class HiveActionLogDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $hive_id = $this->entity->get('hive')->target_id;
    if ($hive_id) {
      return new Url('entity.hive.canonical', ['hive' => $hive_id]);
    }
    return new Url('entity.hive.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $hive_id = $this->entity->get('hive')->target_id;
    $label = $this->entity->label();
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('%label has been deleted.', [
      '%label' => $label,
    ]));

    if ($hive_id) {
      $form_state->setRedirectUrl(new Url('entity.hive.canonical', ['hive' => $hive_id]));
    }
    else {
      $form_state->setRedirectUrl(new Url('entity.hive.collection'));
    }
  }

}
