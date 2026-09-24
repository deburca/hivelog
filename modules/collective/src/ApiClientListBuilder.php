<?php

declare(strict_types=1);

namespace Drupal\collective;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogListBuilder;

/**
 * Provides a list builder for API Client entities.
 *
 * Mirrors `\Drupal\hivelog\ProductListBuilder`'s shape exactly (the
 * `hivelog:entity-table` SDC component, its own "Add" heading) — reusing
 * core's `HivelogListBuilder` base class, since `collective` depends on
 * `hivelog`.
 */
class ApiClientListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['enabled'] = $this->t('Enabled');
    $header['last_run'] = $this->t('Last Run');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->toLink()->toString();
    $row['enabled'] = $entity->get('enabled')->value ? $this->t('Yes') : $this->t('No');

    $last_run = $entity->get('last_run')->value;
    $row['last_run'] = $last_run ? $this->dateFormatter()->format((int) $last_run) : $this->t('Never');

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add API Client'),
        'url' => Url::fromRoute('entity.api_client.add_form')->toString(),
        'variant' => 'primary',
      ],
    ];
  }

  /**
   * The date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter.
   */
  protected function dateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
