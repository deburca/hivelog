<?php

declare(strict_types=1);

namespace Drupal\assimilate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\nanoprobe\Entity\SensorDevice;

/**
 * Provisions assimilate's own demo apiary, hive, devices and inspection.
 *
 * Every created entity's id is recorded in State, keyed so `provision()`
 * is idempotent — calling it again (a second `drush cr` re-running
 * `hook_install()` logic manually, a kernel test calling it twice) reuses
 * whatever already exists instead of duplicating it. This is also how
 * `ProductionGuardrail` knows which apiary is assimilate's own and should
 * be excluded from its "is there a real one?" check.
 *
 * Demo `SensorDevice`s are hive-scoped (weight, temperature_humidity) —
 * matching [[sensor-data-collection]]'s own pilot design, not an
 * apiary-scoped weather station, since that's the actual pilot hardware
 * shape task 0107 is meant to preview.
 */
class DemoDataProvisioner {

  /**
   * The demo apiary's State key — also read by `ProductionGuardrail`.
   */
  public const DEMO_APIARY_ID_STATE_KEY = ProductionGuardrail::DEMO_APIARY_ID_STATE_KEY;

  /**
   * The demo hive's State key.
   */
  public const DEMO_HIVE_ID_STATE_KEY = 'assimilate.demo_hive_id';

  /**
   * The demo sensor devices' State key — an array keyed by device_type.
   */
  public const DEMO_DEVICE_IDS_STATE_KEY = 'assimilate.demo_device_ids';

  /**
   * The demo inspection's State key.
   */
  public const DEMO_INSPECTION_ID_STATE_KEY = 'assimilate.demo_inspection_id';

  /**
   * The demo device types to provision, matching the pilot's own shape.
   */
  protected const DEMO_DEVICE_TYPES = [
    'weight' => 'Assimilate Weight Sensor',
    'temperature_humidity' => 'Assimilate Temperature/Humidity Sensor',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
  ) {}

  /**
   * Provisions everything, reusing whatever already exists.
   *
   * @return \Drupal\nanoprobe\Entity\SensorDevice[]
   *   The demo sensor devices, keyed by `device_type`.
   */
  public function provision(): array {
    $apiary = $this->ensureDemoApiary();
    $hive = $this->ensureDemoHive($apiary);
    $this->ensureDemoInspection($hive);

    $devices = [];
    foreach (self::DEMO_DEVICE_TYPES as $device_type => $label) {
      $devices[$device_type] = $this->ensureDemoDevice($apiary, $hive, $device_type, $label);
    }
    return $devices;
  }

  /**
   * Loads the demo sensor devices already provisioned, without creating any.
   *
   * @return \Drupal\nanoprobe\Entity\SensorDevice[]
   *   The demo devices that actually still exist, keyed by `device_type`.
   */
  public function getDemoDevices(): array {
    $ids = $this->state->get(self::DEMO_DEVICE_IDS_STATE_KEY, []);
    if (empty($ids)) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('sensor_device');
    $devices = [];
    foreach ($ids as $device_type => $id) {
      $device = $storage->load($id);
      if ($device) {
        $devices[$device_type] = $device;
      }
    }
    return $devices;
  }

  /**
   * Deletes every demo entity this class created, and clears State.
   *
   * Called from `assimilate_uninstall()`. Order matters only in that
   * child-ish entities go first — Drupal doesn't enforce referential
   * integrity between these entity types, but leaving orphans around
   * after uninstall would be sloppy regardless.
   */
  public function cleanUp(): void {
    $device_ids = array_values($this->state->get(self::DEMO_DEVICE_IDS_STATE_KEY, []));
    if ($device_ids) {
      $device_storage = $this->entityTypeManager->getStorage('sensor_device');
      $reading_storage = $this->entityTypeManager->getStorage('sensor_reading');
      $reading_ids = $reading_storage->getQuery()->accessCheck(FALSE)
        ->condition('sensor_device', $device_ids, 'IN')
        ->execute();
      if ($reading_ids) {
        $reading_storage->delete($reading_storage->loadMultiple($reading_ids));
      }
      $devices = $device_storage->loadMultiple($device_ids);
      if ($devices) {
        $device_storage->delete($devices);
      }
    }

    $this->deleteByStateKey('hive_inspection', self::DEMO_INSPECTION_ID_STATE_KEY);
    $this->deleteByStateKey('hive', self::DEMO_HIVE_ID_STATE_KEY);
    $this->deleteByStateKey('apiary', self::DEMO_APIARY_ID_STATE_KEY);

    $this->state->deleteMultiple([
      self::DEMO_APIARY_ID_STATE_KEY,
      self::DEMO_HIVE_ID_STATE_KEY,
      self::DEMO_DEVICE_IDS_STATE_KEY,
      self::DEMO_INSPECTION_ID_STATE_KEY,
    ]);
  }

  /**
   * Deletes a single tracked entity by its State-stored id, if it exists.
   */
  protected function deleteByStateKey(string $entity_type_id, string $state_key): void {
    $id = $this->state->get($state_key);
    if (!$id) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity = $storage->load($id);
    if ($entity) {
      $storage->delete([$entity]);
    }
  }

  /**
   * Ensures the demo apiary exists, creating it only if it doesn't.
   */
  protected function ensureDemoApiary(): Apiary {
    $existing_id = $this->state->get(self::DEMO_APIARY_ID_STATE_KEY);
    if ($existing_id) {
      $apiary = Apiary::load($existing_id);
      if ($apiary) {
        return $apiary;
      }
    }

    $apiary = Apiary::create([
      'name' => 'Assimilate Demo Apiary',
      'visibility' => 'private',
      // So nexus_cron() actually has something to reason over once a
      // real AiProviderConfig exists — NexusInsightGenerator::
      // loadOptedInHives() selects hives by their apiary's own flag.
      // ProductionGuardrail excludes this exact apiary (by id, tracked
      // here) from its "is there a real one?" check, so this doesn't
      // trip assimilate's own guardrail.
      'ai_insights_enabled' => TRUE,
    ]);
    $apiary->save();
    $this->state->set(self::DEMO_APIARY_ID_STATE_KEY, $apiary->id());
    return $apiary;
  }

  /**
   * Ensures the demo hive exists, creating it only if it doesn't.
   */
  protected function ensureDemoHive(Apiary $apiary): Hive {
    $existing_id = $this->state->get(self::DEMO_HIVE_ID_STATE_KEY);
    if ($existing_id) {
      $hive = Hive::load($existing_id);
      if ($hive) {
        return $hive;
      }
    }

    $hive = Hive::create([
      'name' => 'Assimilate Demo Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();
    $this->state->set(self::DEMO_HIVE_ID_STATE_KEY, $hive->id());
    return $hive;
  }

  /**
   * Ensures one demo inspection exists on the demo hive.
   *
   * `HiveContextBuilder` (per task 0085's "sensor-less" design) never
   * reads `SensorReading` data — its AI context comes from inspections/
   * queen/calendar data instead. Without at least one real inspection,
   * `nexus_cron()` would have nothing substantive to reason over even
   * with mock sensor readings flowing in, so this gives it one plausible
   * one. Created once, not regenerated every cron run — unlike sensor
   * readings, a single realistic inspection is enough to exercise
   * `HiveContextBuilder`, and a real beekeeper's inspection cadence
   * (weeks, not every cron run) is what this is meant to preview.
   */
  protected function ensureDemoInspection(Hive $hive): void {
    $existing_id = $this->state->get(self::DEMO_INSPECTION_ID_STATE_KEY);
    if ($existing_id && HiveInspection::load($existing_id)) {
      return;
    }

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => date('Y-m-d'),
      'queen_seen' => TRUE,
      'queen_cells' => FALSE,
      'eggs_seen' => TRUE,
      'brood_pattern' => 'good',
      'honey_stores' => 'adequate',
      'pollen_stores' => 'adequate',
      'temperament' => 'calm',
      'population' => 'strong',
      'varroa_check' => TRUE,
      'varroa_count' => 3,
      'disease_signs' => 'none',
      'weight' => 22.5,
      'fed' => FALSE,
      'supers' => 1,
      'notes' => 'Assimilate demo data — fabricated for preview purposes, not a real inspection.',
    ]);
    $inspection->save();
    $this->state->set(self::DEMO_INSPECTION_ID_STATE_KEY, $inspection->id());
  }

  /**
   * Ensures one demo sensor device of the given type exists.
   */
  protected function ensureDemoDevice(Apiary $apiary, Hive $hive, string $device_type, string $label): SensorDevice {
    $ids = $this->state->get(self::DEMO_DEVICE_IDS_STATE_KEY, []);
    if (isset($ids[$device_type])) {
      $device = SensorDevice::load($ids[$device_type]);
      if ($device) {
        return $device;
      }
    }

    $device = SensorDevice::create([
      'label' => $label,
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => $device_type,
      'enabled' => TRUE,
    ]);
    $device->save();

    $ids[$device_type] = $device->id();
    $this->state->set(self::DEMO_DEVICE_IDS_STATE_KEY, $ids);

    return $device;
  }

}
