<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Filter form for `/hivelog/hive-action-logs` (task 0168).
 */
class HivelogHiveActionLogFilterForm extends HivelogActionLogFilterFormBase {

  protected const ENTITY_TYPE = 'hive_action_log';

  protected const PARENT_FIELD = 'hive';

  protected const FORM_ID = 'hivelog_hive_action_log_filter_form';

  /**
   * {@inheritdoc}
   */
  protected function parentFilterLabel(): TranslatableMarkup {
    return $this->t('Hive contains');
  }

}
