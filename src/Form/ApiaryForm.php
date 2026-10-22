<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for Apiary add/edit forms.
 */
class ApiaryForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

  /**
   * Builds the Members section shown on the edit form.
   *
   * @var \Drupal\hivelog\Form\ApiaryMemberManagerForm
   */
  protected $memberManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->memberManager = $container->get('hivelog.apiary_member_manager');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Embed the member management section on edit pages.
    if (!$this->entity->isNew()) {
      $build = $this->memberManager->render($this->entity->id());
      if ($build) {
        $form['members'] = $build + ['#weight' => 100];
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
