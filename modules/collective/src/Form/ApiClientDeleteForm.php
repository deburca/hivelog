<?php

declare(strict_types=1);

namespace Drupal\collective\Form;

use Drupal\hivelog\Form\HivelogEntityDeleteForm;

/**
 * Form handler for API Client delete forms.
 */
class ApiClientDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. Any process still using this credential will start getting 401 responses from the API endpoint immediately.');
  }

}
