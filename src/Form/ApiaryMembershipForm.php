<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryMembership;

/**
 * Form handler for ApiaryMembership add/edit forms.
 */
class ApiaryMembershipForm extends ContentEntityForm {

  use HivelogEntityFormTrait;

  /**
   * Title of the add-member page, naming the apiary it adds to.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary from the route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public static function addTitle(Apiary $apiary) {
    return new TranslatableMarkup('Add member to @apiary', ['@apiary' => $apiary->label()]);
  }

  /**
   * Title of the edit-member page, naming the member.
   *
   * @param \Drupal\hivelog\Entity\ApiaryMembership $apiary_membership
   *   The membership from the route.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public static function editTitle(ApiaryMembership $apiary_membership) {
    $member = $apiary_membership->getMember();
    return new TranslatableMarkup('Edit role of @member', [
      '@member' => $member ? $member->getDisplayName() : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // The shared entity-form spacing and button styles, as on the Hive and
    // apiary forms. The library also brings in the button styles the Cancel
    // action needs; without it Cancel rendered as a bare link.
    $form['#prefix'] = '<div class="hivelog-entity-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'hivelog/forms';

    // The apiary comes from the route and the creator is set on save, so
    // neither is ever asked for.
    $form['apiary']['#access'] = FALSE;
    $form['uid']['#access'] = FALSE;

    /** @var \Drupal\hivelog\Entity\ApiaryMembership $membership */
    $membership = $this->entity;
    if ($membership->isNew()) {
      $apiary = $this->getRouteMatch()->getParameter('apiary');
      if ($apiary) {
        $membership->set('apiary', $apiary);
      }
    }
    else {
      // A membership belongs to one person; to change who, remove it and add
      // another, so only the role is editable.
      $form['account']['#disabled'] = TRUE;
    }

    $form['account']['widget'][0]['target_id']['#placeholder'] = $this->t('Start typing a username');
    $form['account']['widget'][0]['target_id']['#required'] = TRUE;
    // Option labels in the interface language (the stored list holds the
    // English source strings).
    foreach (array_keys(ApiaryMembership::ROLES) as $role) {
      if (isset($form['role']['widget']['#options'][$role])) {
        $form['role']['widget']['#options'][$role] = ApiaryMembership::roleLabel($role);
      }
    }
    $form['role']['widget']['#description'] = $this->t('Novice: can see everything but change nothing. Beekeeper: can do everything on the apiary and its hives except manage members and owners, change the apiary settings and delete the apiary. Owner: all of that too.');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\hivelog\Entity\ApiaryMembership $entity */
    $entity = parent::buildEntity($form, $form_state);

    // The apiary widget is hidden (#access FALSE) on the add form, so its
    // submitted value is empty and would overwrite the route-provided apiary.
    if ($entity->isNew() && $entity->get('apiary')->isEmpty()) {
      $apiary = $this->getRouteMatch()->getParameter('apiary');
      if ($apiary) {
        $entity->set('apiary', $apiary);
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\hivelog\Entity\ApiaryMembership $entity */
    $entity = $this->buildEntity($form, $form_state);
    // An apiary always keeps an owner: refuse to demote the last one.
    if (!$entity->isNew() && $entity->getRole() !== 'owner' && $entity->isLastOwner(TRUE)) {
      $form_state->setErrorByName('role', $this->t('The last owner of an apiary cannot be given another role. Add another owner first.'));
    }
    if (!$entity->isNew() || $entity->get('apiary')->isEmpty() || $entity->get('account')->isEmpty()) {
      return;
    }
    $existing = $this->entityTypeManager->getStorage('apiary_membership')
      ->getQuery()
      ->condition('apiary', $entity->get('apiary')->target_id)
      ->condition('account', $entity->get('account')->target_id)
      ->accessCheck(FALSE)
      ->execute();
    if ($existing) {
      $form_state->setErrorByName('account', $this->t('This user is already a member of this apiary.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Membership has been created.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Membership has been updated.'));
    }

    $form_state->setRedirectUrl($this->cancelUrl());

    return $status;
  }

  /**
   * {@inheritdoc}
   *
   * A membership has no page of its own (its canonical template is not
   * routed), so Cancel and the post-save redirect return to where members
   * are managed.
   */
  protected function cancelUrl(): Url {
    /** @var \Drupal\hivelog\Entity\ApiaryMembership $membership */
    $membership = $this->entity;
    $apiary_id = $membership->get('apiary')->target_id
      ?? $this->getRouteMatch()->getRawParameter('apiary');
    return Url::fromRoute(
      'entity.apiary.edit_form',
      ['apiary' => $apiary_id],
      ['fragment' => 'members']
    );
  }

}
