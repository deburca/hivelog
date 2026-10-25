<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Stamps the apiary a history record was written under (task 0231).
 *
 * An inspection, a queen observation or an action log reaches its apiary
 * through its hive, so when a hive moves to another apiary every record the
 * hive ever had would follow it. History is never rewritten: the record
 * carries the apiary it was written under, set when it is created and never
 * changed afterwards, so the old apiary's history stays with the old apiary.
 *
 * The using entity names how to find the apiary for a new record.
 */
trait ApiaryStampTrait {

  /**
   * The field definition to add to `baseFieldDefinitions()` as `apiary`.
   *
   * Read-only: forms and the API never ask for it.
   */
  protected static function apiaryStampField(): BaseFieldDefinition {
    return BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this record was written under. Set when it is created and never changed, so moving a hive does not rewrite history.'))
      ->setSetting('target_type', 'apiary')
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('view', TRUE);
  }

  /**
   * Finds the apiary a new record is written under.
   *
   * @return int|string|null
   *   The apiary id, or NULL when there is none yet (a queen with no hive).
   */
  abstract protected function computeApiaryStamp(): int|string|NULL;

  /**
   * Sets the stamp on a new record and keeps it on an existing one.
   *
   * Call from `preSave()`.
   */
  protected function stampApiary(EntityStorageInterface $storage): void {
    if (!$this->isNew()) {
      $original = $this->original ?? NULL;
      if ($original && !$original->get('apiary')->isEmpty()) {
        // Never changed after it is written.
        $this->set('apiary', $original->get('apiary')->target_id);
        return;
      }
    }
    if ($this->get('apiary')->isEmpty()) {
      $apiary_id = $this->computeApiaryStamp();
      if ($apiary_id) {
        $this->set('apiary', $apiary_id);
      }
    }
  }

}
