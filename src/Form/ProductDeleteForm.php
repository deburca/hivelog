<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Product delete forms.
 */
class ProductDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete product %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $apiary = $this->entity->get('apiary')->entity;
    return $apiary ? $apiary->toUrl() : parent::getCancelUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $apiary = $this->entity->get('apiary')->entity;
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Product %name has been deleted.', [
      '%name' => $this->entity->label(),
    ]));

    if ($apiary) {
      $form_state->setRedirectUrl($apiary->toUrl());
    }
    else {
      $form_state->setRedirect('entity.product.collection');
    }
  }

}
