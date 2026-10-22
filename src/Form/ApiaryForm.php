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

    // Group the flat list of fields. `#group` on a `details` element moves
    // the field into it at render time, so no field is re-parented and form
    // values keep their parents. Submodules (collective's AI-insights
    // disclosure) join a group by naming it.
    $groups = [
      'hivelog_details' => [$this->t('Details'), -20, ['name', 'location', 'notes']],
      'hivelog_map' => [$this->t('Map'), -10, ['geolocation']],
      'hivelog_settings' => [$this->t('Settings'), 0, ['visibility', 'ai_insights_enabled', 'uid']],
    ];
    foreach ($groups as $key => [$title, $weight, $fields]) {
      $form[$key] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => TRUE,
        '#weight' => $weight,
      ];
      foreach ($fields as $field) {
        if (isset($form[$field])) {
          $form[$field]['#group'] = $key;
        }
      }
    }

    // Embed the member management section on edit pages, after the buttons
    // (EntityForm gives the actions a weight of 100) so that saving the
    // apiary is not lost among the member controls.
    if (!$this->entity->isNew()) {
      $build = $this->memberManager->render($this->entity->id());
      if ($build) {
        $form['members'] = $build + ['#weight' => 200];
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
