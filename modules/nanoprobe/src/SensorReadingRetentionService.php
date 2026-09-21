<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\nanoprobe\Entity\SensorDevice;

/**
 * Computes daily rollups and purges old raw readings (task 0082).
 *
 * Per docs/project-management/decisions/0074-sensor-data-ingestion-architecture.md
 * §5: raw `SensorReading` rows must not grow unbounded, but a long-term
 * trend must still be answerable after older raw rows are purged. Run
 * from `nanoprobe_cron()` — rollup always runs before purge in the same
 * call, so every date about to be purged has just been (re)computed.
 *
 * Deliberately simple: every run recomputes the rollup for every
 * device/metric/date that still has raw data (except today, which is
 * still accumulating), rather than tracking a "last rolled up"
 * watermark to skip already-stable historical days. Correct and cheap
 * at the data volumes this project is actually at (per
 * [[0074-sensor-data-ingestion-architecture]] §8, this task isn't
 * required until "general rollout"); revisit for efficiency if it ever
 * shows up as slow in practice, not before.
 */
class SensorReadingRetentionService {

  /**
   * How long raw SensorReading rows are kept before being purged.
   *
   * 2 years — the figure [[0074-sensor-data-ingestion-architecture]] §5
   * itself proposed; that ADR fixed the *policy* (don't keep raw data
   * forever, but keep the rollup), not this exact number.
   */
  public const RAW_RETENTION_DAYS = 730;

  /**
   * How many entities to delete per batch during purge, to bound memory.
   */
  protected const PURGE_BATCH_SIZE = 50;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Runs the full daily maintenance cycle: rollup, then purge.
   */
  public function runDailyMaintenance(): void {
    $this->computeRollups();
    $this->purgeOldRawReadings();
  }

  /**
   * Computes/updates the rollup for every device/metric/date with raw data.
   *
   * Other than today's date, which is still accumulating.
   */
  public function computeRollups(): void {
    $device_storage = $this->entityTypeManager->getStorage('sensor_device');
    $device_ids = $device_storage->getQuery()->accessCheck(FALSE)->execute();
    if (empty($device_ids)) {
      return;
    }

    $today = $this->dateFormatter->format($this->time->getRequestTime(), 'custom', 'Y-m-d', 'UTC');
    $reading_storage = $this->entityTypeManager->getStorage('sensor_reading');

    /** @var \Drupal\nanoprobe\Entity\SensorDevice $device */
    foreach ($device_storage->loadMultiple($device_ids) as $device) {
      $ids = $reading_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('sensor_device', $device->id())
        ->execute();
      if (empty($ids)) {
        continue;
      }

      $groups = [];
      foreach ($reading_storage->loadMultiple($ids) as $reading) {
        $date = $this->dateFormatter->format((int) $reading->get('recorded')->value, 'custom', 'Y-m-d', 'UTC');
        if ($date >= $today) {
          // Today isn't finished yet — rolling it up now would freeze an
          // incomplete day's min/max/avg; it'll be picked up once it's
          // "yesterday" on a future run.
          continue;
        }
        $metric = $reading->get('metric')->value;
        $value = (float) $reading->get('value')->value;
        $key = $metric . '|' . $date;
        if (!isset($groups[$key])) {
          $groups[$key] = [
            'metric' => $metric,
            'date' => $date,
            'min' => $value,
            'max' => $value,
            'sum' => $value,
            'count' => 1,
          ];
        }
        else {
          $groups[$key]['min'] = min($groups[$key]['min'], $value);
          $groups[$key]['max'] = max($groups[$key]['max'], $value);
          $groups[$key]['sum'] += $value;
          $groups[$key]['count']++;
        }
      }

      foreach ($groups as $group) {
        $this->upsertRollup(
          $device,
          $group['metric'],
          $group['date'],
          $group['min'],
          $group['max'],
          $group['sum'] / $group['count'],
          $group['count'],
        );
      }
    }
  }

  /**
   * Creates or updates the one rollup row for a device/metric/date.
   */
  protected function upsertRollup(SensorDevice $device, string $metric, string $date, float $min, float $max, float $avg, int $count): void {
    $storage = $this->entityTypeManager->getStorage('sensor_reading_daily');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('sensor_device', $device->id())
      ->condition('metric', $metric)
      ->condition('date', $date)
      ->execute();

    $rollup = $ids
      ? $storage->load(reset($ids))
      : $storage->create([
        'sensor_device' => $device->id(),
        'metric' => $metric,
        'date' => $date,
      ]);

    $rollup->set('min_value', $min);
    $rollup->set('max_value', $max);
    $rollup->set('avg_value', $avg);
    $rollup->set('sample_count', $count);
    $rollup->save();
  }

  /**
   * Deletes raw SensorReading rows older than RAW_RETENTION_DAYS.
   *
   * Batched to bound memory, mirroring hivelog core's own established
   * uninstall-time batch-delete pattern (e.g. hivelog_uninstall()).
   */
  public function purgeOldRawReadings(): void {
    $cutoff = $this->time->getRequestTime() - (self::RAW_RETENTION_DAYS * 86400);
    $storage = $this->entityTypeManager->getStorage('sensor_reading');

    while (TRUE) {
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('recorded', $cutoff, '<')
        ->range(0, self::PURGE_BATCH_SIZE)
        ->execute();
      if (empty($ids)) {
        break;
      }
      $storage->delete($storage->loadMultiple($ids));
    }
  }

}
