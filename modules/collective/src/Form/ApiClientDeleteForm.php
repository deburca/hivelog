<?php

declare(strict_types=1);

namespace Drupal\collective\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Insight Agent delete forms.
 */
class InsightAgentDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete insight agent %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. Any external agent process still using this credential will start getting 401 responses from both API endpoints immediately.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $this->entity->label();
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('Insight agent %label has been deleted.', [
      '%label' => $label,
    ]));
    $form_state->setRedirect('entity.insight_agent.collection');
  }

}
