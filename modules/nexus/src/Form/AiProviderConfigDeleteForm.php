<?php

declare(strict_types=1);

namespace Drupal\nexus\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for AI Provider Config delete forms.
 */
class AiProviderConfigDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete AI provider config %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. nexus_cron() will simply stop generating insights using this config — it does not delete the Key entity it referenced, or any HiveInsight rows it already produced.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $this->entity->label();
    $this->entity->delete();
    $this->messenger()->addStatus($this->t('AI provider config %label has been deleted.', [
      '%label' => $label,
    ]));
    $form_state->setRedirect('entity.ai_provider_config.collection');
  }

}
