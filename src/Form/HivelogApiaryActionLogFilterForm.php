<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Filter form for `/hivelog/apiary-action-logs` (task 0168).
 */
class HivelogApiaryActionLogFilterForm extends HivelogActionLogFilterFormBase {

  protected const ENTITY_TYPE = 'apiary_action_log';

  protected const PARENT_FIELD = 'apiary';

  protected const FORM_ID = 'hivelog_apiary_action_log_filter_form';

  /**
   * {@inheritdoc}
   */
  protected function parentFilterLabel(): TranslatableMarkup {
    return $this->t('Apiary contains');
  }

}
