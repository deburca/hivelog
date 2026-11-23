<?php

declare(strict_types=1);

namespace Drupal\hivelog\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Keeps a parent's temperament in step with its latest record (ADR-0111).
 *
 * Temperament is recorded at each visit, on an inspection or a queen
 * observation. The hive's and the queen's own `temperament` (read by the hive
 * page's tile and the list filters, and part of the v1 API contract) are a
 * cache of the latest such record: when a record is saved and it is the
 * latest for its parent, its value is copied to the parent. A back-dated
 * record, or one with no temperament, changes nothing. It runs on entity save,
 * so the web forms, the API and scripts all behave the same.
 */
trait LatestTemperamentTrait {

  /**
   * Copies this record's temperament to its parent when it is the latest.
   *
   * @param string $parent_field
   *   The reference field naming the parent (`hive` or `queen`).
   * @param string $date_field
   *   The date field that orders the records.
   */
  protected function syncParentTemperament(string $parent_field, string $date_field): void {
    $temperament = $this->get('temperament')->value;
    $parent = $this->get($parent_field)->entity;
    if (!$temperament || !$parent instanceof FieldableEntityInterface
      || $parent->get('temperament')->value === $temperament) {
      return;
    }
    $storage = \Drupal::entityTypeManager()->getStorage($this->getEntityTypeId());
    assert($storage instanceof EntityStorageInterface);
    $latest = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($parent_field, $parent->id())
      ->sort($date_field, 'DESC')
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    if ((string) reset($latest) !== (string) $this->id()) {
      return;
    }
    $parent->set('temperament', $temperament)->save();
  }

}
