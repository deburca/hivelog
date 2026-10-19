<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for ApiaryMembership add/edit forms.
 */
class ApiaryMembershipForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['apiary']['#disabled'] = TRUE;
    $form['account']['#required'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t('Membership has been saved.'));
    $form_state->setRedirect('entity.apiary.canonical', ['apiary' => $entity->get('apiary')->target_id]);

    return $status;
  }

}
