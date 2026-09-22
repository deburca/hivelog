<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogEntityStorage;
use Drupal\nanoprobe\Form\SensorDeviceDeleteForm;
use Drupal\nanoprobe\Form\SensorDeviceForm;
use Drupal\nanoprobe\SensorDeviceAccessControlHandler;
use Drupal\nanoprobe\SensorDeviceListBuilder;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Sensor Device entity.
 *
 * A SensorDevice is a registered piece of automated data-collection
 * hardware — a physical sensor node, or the receiver/bridge acting on its
 * behalf — belonging to a single apiary, or to one specific hive within
 * it. It is the credential half of automated sensor ingestion: its
 * `token` authenticates the ingestion endpoint (`SensorReading` is the
 * data half).
 *
 * See docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * for the full design and
 * docs/project-management/decisions/0098-nanoprobe-collective-locutus-submodule-split.md
 * for why this lives in the `nanoprobe` submodule rather than `hivelog`
 * core.
 */
#[ContentEntityType(
  id: 'sensor_device',
  label: new TranslatableMarkup('Sensor Device'),
  label_collection: new TranslatableMarkup('Sensor Devices'),
  label_singular: new TranslatableMarkup('sensor device'),
  label_plural: new TranslatableMarkup('sensor devices'),
  handlers: [
    'storage' => HivelogEntityStorage::class,
    'access' => SensorDeviceAccessControlHandler::class,
    'list_builder' => SensorDeviceListBuilder::class,
    'form' => [
      'default' => SensorDeviceForm::class,
      'add' => SensorDeviceForm::class,
      'edit' => SensorDeviceForm::class,
      'delete' => SensorDeviceDeleteForm::class,
    ],
  ],
  base_table: 'nanoprobe_sensor_device',
  admin_permission: 'administer hivelog',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  links: [
    'canonical' => '/hivelog/sensor-device/{sensor_device}',
    'add-form' => '/hivelog/sensor-device/add',
    'edit-form' => '/hivelog/sensor-device/{sensor_device}/edit',
    'delete-form' => '/hivelog/sensor-device/{sensor_device}/delete',
    'collection' => '/hivelog/sensor-devices',
  ],
)]
class SensorDevice extends ContentEntityBase implements EntityChangedInterface, EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * The config descriptor's `transport.suggested_interval_seconds` default.
   *
   * 1800 seconds (30 minutes) sits mid-band within
   * [[0074-sensor-data-ingestion-architecture]] §5's recommended 15–60
   * minute sampling interval — advisory only, firmware may use a
   * different value; the ingestion endpoint never enforces it.
   */
  public const DEFAULT_SUGGESTED_INTERVAL_SECONDS = 1800;

  /**
   * Maps `device_type` to the `SensorReading::METRIC_TYPES` it reports.
   *
   * `multi` and `other` are deliberately absent — device types with no
   * known restriction fall back to the full metric taxonomy in
   * `getConfigMetrics()`, rather than guessing a subset. Every mapped
   * type also gets `battery_voltage`/`signal_rssi` — diagnostic metrics
   * useful regardless of what the device primarily senses.
   */
  public const DEVICE_TYPE_METRICS = [
    'weight' => ['weight_kg', 'battery_voltage', 'signal_rssi'],
    'temperature_humidity' => [
      'temp_internal_c',
      'temp_external_c',
      'humidity_internal_pct',
      'humidity_external_pct',
      'battery_voltage',
      'signal_rssi',
    ],
    'acoustic' => ['battery_voltage', 'signal_rssi'],
    'entrance_counter' => ['battery_voltage', 'signal_rssi'],
    'gps' => ['battery_voltage', 'signal_rssi'],
  ];

  /**
   * The plaintext token generated in this request, if any.
   *
   * Never persisted — the `token` field always stores a `password_hash()`
   * digest, never the plaintext value. This holds the plaintext only
   * immediately after `generateToken()` (called automatically on insert,
   * or explicitly to regenerate) runs in the same request, the same way a
   * password-reset flow exposes a one-time value without ever storing it.
   * NULL after a normal load from storage — the plaintext is not
   * recoverable once this request ends.
   */
  protected ?string $plainTextToken = NULL;

  /**
   * Generates a new random token, stores its hash, and returns the plaintext.
   *
   * Called automatically from `preSave()` on insert if no token has been
   * set; call directly to regenerate an existing device's token, which
   * immediately invalidates the previous one (the old hash is simply
   * overwritten).
   *
   * @return string
   *   The plaintext token — the only time it is ever available. The
   *   caller (a management UI, a kernel test) must capture it now; it
   *   cannot be recovered from storage afterwards.
   */
  public function generateToken(): string {
    $plaintext = bin2hex(random_bytes(32));
    $this->set('token', password_hash($plaintext, PASSWORD_DEFAULT));
    $this->plainTextToken = $plaintext;
    return $plaintext;
  }

  /**
   * The plaintext token generated in this request, or NULL.
   *
   * NULL for a `SensorDevice` freshly loaded from storage — only
   * populated in the same request `generateToken()` was called in.
   *
   * @return string|null
   *   The plaintext token, or NULL if none was generated in this request.
   */
  public function getPlainTextToken(): ?string {
    return $this->plainTextToken;
  }

  /**
   * Whether $provided matches this device's stored token hash.
   *
   * @param string $provided
   *   The bearer token presented by a client (e.g. the ingestion
   *   endpoint's `Authorization: Bearer <token>` header value).
   *
   * @return bool
   *   TRUE if $provided hashes to the same value stored on this device.
   */
  public function verifyToken(string $provided): bool {
    $hash = $this->get('token')->value;
    return is_string($hash) && $hash !== '' && password_verify($provided, $hash);
  }

  /**
   * The `SensorReading::METRIC_TYPES` this device is expected to report.
   *
   * @return string[]
   *   Metric machine names, per `DEVICE_TYPE_METRICS`'s mapping for this
   *   device's `device_type`; the full taxonomy if `device_type` is
   *   `multi`, `other`, or unset.
   */
  public function getConfigMetrics(): array {
    $device_type = $this->get('device_type')->value;
    if ($device_type !== NULL && isset(self::DEVICE_TYPE_METRICS[$device_type])) {
      return self::DEVICE_TYPE_METRICS[$device_type];
    }
    return array_keys(SensorReading::METRIC_TYPES);
  }

  /**
   * Builds the device configuration descriptor.
   *
   * Per docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
   * §3. Deliberately excludes which physical pin reads which sensor, and
   * any calibration constant — firmware/hardware-specific, stays in
   * firmware's own local configuration.
   *
   * @param string $token
   *   The plaintext bearer token to embed — the caller is responsible for
   *   having just generated it (e.g. via `generateToken()`); this method
   *   never reads or regenerates a token itself.
   *
   * @return array
   *   The descriptor, ready for `json_encode()`.
   */
  public function buildConfigDescriptor(string $token): array {
    return [
      'config_version' => 1,
      'device' => [
        'id' => (int) $this->id(),
        'label' => $this->label(),
      ],
      'endpoint' => [
        'url' => Url::fromRoute('nanoprobe.sensor_reading.ingest', [], ['absolute' => TRUE])->toString(),
        'method' => 'POST',
        'content_type' => 'application/json',
      ],
      'auth' => [
        'type' => 'bearer',
        'token' => $token,
      ],
      'transport' => [
        'batch' => TRUE,
        'suggested_interval_seconds' => self::DEFAULT_SUGGESTED_INTERVAL_SECONDS,
      ],
      'metrics' => $this->getConfigMetrics(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Defensive invariant: hive is required when scope = hive, and must
    // be unset when scope = apiary — mirrors CalendarAction's own
    // scope field, applied here to a real reference rather than a
    // fan-out rule.
    $scope = $this->get('scope')->value;
    $hive_id = $this->get('hive')->target_id;
    if ($scope === 'hive' && !$hive_id) {
      throw new \InvalidArgumentException('SensorDevice hive is required when scope is hive.');
    }
    if ($scope === 'apiary' && $hive_id) {
      throw new \InvalidArgumentException('SensorDevice hive must not be set when scope is apiary.');
    }

    // Auto-generate a token on insert if none was explicitly set (e.g. by
    // a test constructing a device with a known token).
    if ($this->isNew() && $this->get('token')->isEmpty()) {
      $this->generateToken();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('A short, human-readable name for this device, e.g. "VV-01 Scale".'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['apiary'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Apiary'))
      ->setDescription(t('The apiary this device belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'apiary')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['scope'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Scope'))
      ->setDescription(t('A hive-scoped device monitors one specific hive (e.g. a per-hive weight sensor). An apiary-scoped device serves the whole site (e.g. an ambient weather station).'))
      ->setRequired(TRUE)
      ->setDefaultValue('hive')
      ->setSetting('allowed_values', [
        'hive' => 'Hive',
        'apiary' => 'Apiary',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Ordered after `scope` (form weight 3 vs. 2) — matches
    // AiProviderConfigForm's own "controlling field before its
    // conditional dependents" convention (task 0104), since
    // SensorDeviceForm::addScopeConditionalStates() hides this field
    // unless scope = hive.
    $fields['hive'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hive'))
      ->setDescription(t('The specific hive this device monitors. Required when scope is "Hive"; must be left empty when scope is "Apiary".'))
      ->setSetting('target_type', 'hive')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['device_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Device Type'))
      ->setDescription(t('Classification only — does not restrict which metrics this device may report.'))
      ->setSetting('allowed_values', [
        'weight' => 'Weight',
        'temperature_humidity' => 'Temperature/Humidity',
        'acoustic' => 'Acoustic',
        'entrance_counter' => 'Entrance Counter',
        'gps' => 'GPS',
        'multi' => 'Multiple metrics',
        'other' => 'Other',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['transport'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Transport'))
      ->setDescription(t('Informational only, for reference — how this device gets data to HiveLog. No effect on ingestion.'))
      ->setSetting('allowed_values', [
        'lorawan' => 'LoRaWAN',
        'wifi' => 'WiFi',
        'cellular' => 'Cellular',
        'bluetooth' => 'Bluetooth',
        'other' => 'Other',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Deliberately no display options — this stores a password_hash()
    // digest and must never be exposed via the Field UI or a view
    // display, only through generateToken()'s one-time plaintext return.
    $fields['token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Token'))
      ->setDescription(t('Server-generated credential hash. Never shown in plaintext except immediately after generation or regeneration.'))
      ->setSetting('max_length', 255);

    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDescription(t('A disabled device\'s readings are rejected outright, not silently accepted — an explicit deauthorisation, not a soft mute.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => 6,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_seen'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last Seen'))
      ->setDescription(t('Updated on every reading this device successfully submits. Powers a "device offline" health check.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The user who registered this device.'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time this device was registered.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time this device was last updated.'));

    return $fields;
  }

}
