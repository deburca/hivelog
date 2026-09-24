<?php

declare(strict_types=1);

namespace Drupal\nexus\Form;

use Drupal\hivelog\Form\HivelogEntityDeleteForm;

/**
 * Form handler for AI Provider Config delete forms.
 */
class AiProviderConfigDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. nexus_cron() will simply stop generating insights using this config — it does not delete the Key entity it referenced, or any HiveInsight rows it already produced.');
  }

}
