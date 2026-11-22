<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\HiveReminder;

/**
 * Form handler for HiveReminder add/edit forms.
 */
class HiveReminderForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // The hive page's Done and Dismiss links carry ?status=. Prefill it on
    // the edit form only; nothing changes until the user saves.
    $wanted = $this->getRequest()->query->get('status');
    if (!$this->entity->isNew() && in_array($wanted, ['done', 'dismissed'], TRUE)) {
      $this->entity->set('status', $wanted);
    }
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
    $status = parent::save($form, $form_state);

    $form_state->setRedirectUrl(
      Url::fromRoute('entity.hive_reminder.collection')
    );

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('The hive reminder has been created.'));
    }
    else {
      $this->messenger()->addStatus($this->t('The hive reminder has been updated.'));
    }

    return $status;
  }

  /**
   * Title callback for the edit form.
   */
  public static function editTitle(HiveReminder $hive_reminder): TranslatableMarkup {
    return new TranslatableMarkup('Edit @title', ['@title' => (string) $hive_reminder->label()]);
  }

  /**
   * Title callback for the delete form.
   */
  public static function deleteTitle(HiveReminder $hive_reminder): TranslatableMarkup {
    return new TranslatableMarkup('Delete @title', ['@title' => (string) $hive_reminder->label()]);
  }

}
