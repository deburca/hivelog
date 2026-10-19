<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Apiary add/edit forms.
 */
class ApiaryForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Embed the member management section on edit pages.
    if (!$this->entity->isNew()) {
      /** @var \Drupal\hivelog\ApiaryMemberManagerForm $member_manager */
      $member_manager = \Drupal::service('hivelog.apiary_member_manager');
      $build = $member_manager->render($this->entity->id());
      if ($build) {
        $form['members'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['js-hivelog-members']],
          '#weight' => 100,
        ] + $build;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Apiary %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Apiary %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    $form_state->setRedirect('entity.apiary.canonical', ['apiary' => $entity->id()]);
  }

}
