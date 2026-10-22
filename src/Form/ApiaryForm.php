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

    // Vertical tabs, one per concern. `#group` on a `details` element moves
    // the field into it at render time, so no field is re-parented and form
    // values keep their parents. Submodules (collective's AI-insights
    // disclosure) join a tab by naming its group. The three access settings
    // (who can see the apiary, who owns it, who the members are) share one
    // tab, in that order.
    $form['hivelog_tabs'] = [
      '#type' => 'vertical_tabs',
      '#weight' => -20,
    ];
    $tabs = [
      'hivelog_details' => [$this->t('Details'), 0, ['name', 'location', 'notes']],
      'hivelog_map' => [$this->t('Map'), 1, ['geolocation']],
      'hivelog_access' => [$this->t('Access'), 2, ['visibility', 'uid']],
      'hivelog_ai' => [$this->t('AI insights'), 3, ['ai_insights_enabled']],
    ];
    foreach ($tabs as $key => [$title, $weight, $fields]) {
      $form[$key] = [
        '#type' => 'details',
        '#title' => $title,
        '#group' => 'hivelog_tabs',
        '#weight' => $weight,
      ];
      foreach ($fields as $field) {
        if (isset($form[$field])) {
          $form[$field]['#group'] = $key;
        }
      }
    }

    // Members belong with the other access settings, after them, as a child
    // of that tab (a form item has no #group processing of its own): the form
    // display gives visibility weight 6 and owner weight 8, and a display's
    // weights are re-applied after form(), so this follows those rather than
    // re-weighting the fields. The section is only there once the apiary
    // exists.
    if (!$this->entity->isNew()) {
      $build = $this->memberManager->render($this->entity->id());
      if ($build) {
        $form['hivelog_access']['members'] = $build + ['#weight' => 9];
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
