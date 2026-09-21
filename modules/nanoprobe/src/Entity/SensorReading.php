<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\nanoprobe\SensorReadingAccessControlHandler;

/**
 * Defines the Sensor Reading entity.
 *
 * A single machine-written time-series data point submitted by a
 * `SensorDevice` — the data half of automated sensor ingestion (see
 * `SensorDevice` for the credential half). No add/edit form is ever
 * built for this entity type: rows are created exclusively by the
 * ingestion endpoint (see
 * docs/project-management/tasks/0077-sensor-ingestion-endpoint-and-device-auth.md),
 * never by a human through the UI.
 *
 * See docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * for the full design and
 * docs/project-management/decisions/0098-nanoprobe-collective-locutus-submodule-split.md
 * for why this lives in the `nanoprobe` submodule rather than `hivelog`
 * core.
 */
#[ContentEntityType(
  id: 'sensor_reading',
  label: new TranslatableMarkup('Sensor Reading'),
  label_collection: new TranslatableMarkup('Sensor Readings'),
  label_singular: new TranslatableMarkup('sensor reading'),
  label_plural: new TranslatableMarkup('sensor readings'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => SensorReadingAccessControlHandler::class,
  ],
  base_table: 'nanoprobe_sensor_reading',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
class SensorReading extends ContentEntityBase {

  /**
   * The code-defined set of metrics a SensorReading may report.
   *
   * Keyed by machine name, valued by human-readable label — per ADR-0003
   * (code-defined entity schema), adding a new metric here requires a
   * Drupal update hook, not a data migration, since `metric` is a
   * `list_string` field whose allowed values come from this constant.
   */
  const METRIC_TYPES = [
    'weight_kg' => 'Weight (kg)',
    'temp_internal_c' => 'Internal Temperature (°C)',
    'temp_external_c' => 'External Temperature (°C)',
    'humidity_internal_pct' => 'Internal Humidity (%)',
    'humidity_external_pct' => 'External Humidity (%)',
    'battery_voltage' => 'Battery Voltage (V)',
    'signal_rssi' => 'Signal RSSI',
  ];

  /**
   * {@inheritdoc}
   */
  public function label() {
    $device = $this->get('sensor_device')->entity;
    $metric = self::METRIC_TYPES[$this->get('metric')->value] ?? $this->get('metric')->value;
    return t('@metric — @device', [
      '@metric' => $metric,
      '@device' => $device ? $device->label() : t('Unknown device'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $metric = $this->get('metric')->value;
    if (!isset(self::METRIC_TYPES[$metric])) {
      throw new \InvalidArgumentException("SensorReading metric '$metric' is not a recognised metric type.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['sensor_device'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sensor Device'))
      ->setDescription(t('The device that produced this reading.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'sensor_device')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['metric'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Metric'))
      ->setDescription(t('Which quantity this reading reports.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', self::METRIC_TYPES)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['value'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Value'))
      ->setDescription(t('The numeric reading, in the unit implied by metric.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['recorded'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Recorded'))
      ->setDescription(t('When the device/bridge captured this reading — distinct from `created`, which is when HiveLog received it.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time HiveLog received this reading.'));

    return $fields;
  }

}
