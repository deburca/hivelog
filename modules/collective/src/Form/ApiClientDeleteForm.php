<?php

declare(strict_types=1);

namespace Drupal\collective\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for API Client delete forms.
 */
class ApiClientDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete API client %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. Any process still using this credential will start getting 401 responses from the API endpoint immediately.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $this->entity->label();
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('API client %label has been deleted.', [
      '%label' => $label,
    ]));
    $form_state->setRedirect('entity.api_client.collection');
  }

}
