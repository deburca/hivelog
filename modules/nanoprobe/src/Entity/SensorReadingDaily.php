<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\nanoprobe\SensorReadingDailyAccessControlHandler;

/**
 * Defines the Sensor Reading Daily entity — a persisted per-day rollup.
 *
 * One row per (sensor_device, metric, date): the min/max/avg of every
 * raw `SensorReading` recorded that day, plus how many contributed. Per
 * docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §5's retention policy — raw readings are not kept forever, but a
 * long-term trend must still be answerable after they're purged. Rows
 * are computed and upserted by `SensorReadingRetentionService` (task
 * 0082), never written by hand and never exposed through an add/edit
 * form, same "machine-written only" shape as `SensorReading` itself.
 *
 * Reuses `SensorReading`'s own `view own/any sensor reading` and
 * `delete own/any sensor reading` permissions rather than minting new
 * ones — a rollup is the same underlying data at a coarser grain, not a
 * distinct capability a beekeeper would want to grant separately.
 */
#[ContentEntityType(
  id: 'sensor_reading_daily',
  label: new TranslatableMarkup('Sensor Reading Daily Rollup'),
  label_collection: new TranslatableMarkup('Sensor Reading Daily Rollups'),
  label_singular: new TranslatableMarkup('sensor reading daily rollup'),
  label_plural: new TranslatableMarkup('sensor reading daily rollups'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => SensorReadingDailyAccessControlHandler::class,
  ],
  base_table: 'nanoprobe_sensor_reading_daily',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
class SensorReadingDaily extends ContentEntityBase implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $device = $this->get('sensor_device')->entity;
    $metric = SensorReading::METRIC_TYPES[$this->get('metric')->value] ?? $this->get('metric')->value;
    return t('@metric daily rollup for @date — @device', [
      '@metric' => $metric,
      '@date' => $this->get('date')->value,
      '@device' => $device ? $device->label() : t('Unknown device'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['sensor_device'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sensor Device'))
      ->setDescription(t('The device this rollup summarises.'))
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
      ->setDescription(t('Which quantity this rollup summarises.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', SensorReading::METRIC_TYPES)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['date'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Date'))
      ->setDescription(t('The calendar date this rollup covers, as YYYY-MM-DD (UTC).'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 10)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['min_value'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Minimum'))
      ->setDescription(t('The lowest raw reading value that day.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['max_value'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Maximum'))
      ->setDescription(t('The highest raw reading value that day.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['avg_value'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Average'))
      ->setDescription(t('The mean of every raw reading value that day.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['sample_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Sample count'))
      ->setDescription(t('How many raw readings contributed to this rollup.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this rollup was first computed.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this rollup was last recomputed (e.g. after late-arriving data).'));

    return $fields;
  }

}
