<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a list builder for HiveReminder entities.
 */
class HiveReminderListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['hive'] = $this->t('Hive');
    $header['title'] = $this->t('Title');
    $header['due_date'] = $this->t('Due date');
    $header['status'] = $this->t('Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSortableColumns(): array {
    return [
      'title' => 'title',
      'due_date' => 'due_date',
      'status' => 'status',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\hivelog\Entity\HiveReminder $entity */
    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink()->toString() : '';

    $row['title'] = $entity->get('title')->value ?? '';
    $row['due_date'] = $entity->get('due_date')->value ?? '';
    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row;
  }

}
