<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogListBuilder;

/**
 * Provides a list builder for Sensor Device entities.
 *
 * Mirrors `\Drupal\collective\ApiClientListBuilder`'s shape (the
 * `hivelog:entity-table` SDC component, its own "Add" heading) — reusing
 * core's `HivelogListBuilder` base class, since `nanoprobe` depends on
 * `hivelog`. Per-row `access('view')` filtering (SensorDevice is
 * apiary-scoped and multi-tenant) lives in `HivelogListBuilder::load()`,
 * the way `SensorPanelBuilder::loadAccessibleDevices()` already filters
 * elsewhere.
 */
class SensorDeviceListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['location'] = $this->t('Apiary / Hive');
    $header['scope'] = $this->t('Scope');
    $header['device_type'] = $this->t('Device Type');
    $header['enabled'] = $this->t('Enabled');
    $header['last_seen'] = $this->t('Last Seen');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\nanoprobe\Entity\SensorDevice $entity */
    $row['label'] = $entity->toLink()->toString();

    $hive = $entity->get('hive')->entity;
    $apiary = $entity->get('apiary')->entity;
    $row['location'] = $hive ? $hive->toLink()->toString() : ($apiary ? $apiary->toLink()->toString() : $this->t('Not set'));

    $scope = $entity->get('scope')->value;
    $row['scope'] = $entity->get('scope')->getSetting('allowed_values')[$scope] ?? $scope;

    $device_type = $entity->get('device_type')->value;
    $row['device_type'] = $device_type
      ? ($entity->get('device_type')->getSetting('allowed_values')[$device_type] ?? $device_type)
      : $this->t('Not set');

    $row['enabled'] = $entity->get('enabled')->value ? $this->t('Yes') : $this->t('No');

    $last_seen = $entity->get('last_seen')->value;
    $row['last_seen'] = $last_seen ? $this->dateFormatter()->format((int) $last_seen) : $this->t('Never');

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Sensor Device'),
        'url' => Url::fromRoute('entity.sensor_device.add_form')->toString(),
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
