<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Product add/edit forms.
 */
class ProductForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Product %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Product %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    // Redirect to the parent apiary — the embedded Products table there
    // is where the saved product is actually visible, matching Hive/
    // InventoryItem's redirect-to-embedding-parent choice.
    $apiary = $entity->get('apiary')->entity;
    if ($apiary) {
      $form_state->setRedirectUrl($apiary->toUrl());
    }
    else {
      $form_state->setRedirect('entity.product.collection');
    }
  }

}
