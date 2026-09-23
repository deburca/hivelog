<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nanoprobe\Controller\SensorDeviceController;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the full-history sensor readings page (task 0110).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceReadingsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'file',
    'image',
    'geofield',
    'hivelog',
    'nanoprobe',
  ];

  /**
   * A test apiary, owned by `$owner`.
   */
  protected Apiary $apiary;

  /**
   * A test sensor device.
   *
   * Created 5 days ago so backdated readings fall inside the page's
   * default "full history" range.
   */
  protected SensorDevice $device;

  /**
   * The apiary owner (has view access to `$device`).
   */
  protected User $owner;

  /**
   * A user with no relationship to the apiary (no access to `$device`).
   */
  protected User $outsider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('sensor_device');
    $this->installEntitySchema('sensor_reading');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    \Drupal::service('router.builder')->rebuild();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own sensor device');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
    ]);
    $this->apiary->save();

    $hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $hive->save();

    $this->device = SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $this->apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'uid' => $this->owner->id(),
      'created' => \Drupal::time()->getRequestTime() - (5 * 86400),
    ]);
    $this->device->save();

    $this->setCurrentUser($this->owner);
  }

  /**
   * Creates a reading for `$this->device` at $days_ago days before now.
   */
  protected function createReading(string $metric, float $value, int $days_ago): void {
    \Drupal::entityTypeManager()->getStorage('sensor_reading')->create([
      'sensor_device' => $this->device->id(),
      'metric' => $metric,
      'value' => $value,
      'recorded' => \Drupal::time()->getRequestTime() - ($days_ago * 86400),
    ])->save();
  }

  /**
   * Tests the page builds a chart for every metric with 2+ days of data.
   */
  public function testReadingsPageBuildsChartsForAllMetrics(): void {
    $this->createReading('weight_kg', 20.0, 2);
    $this->createReading('weight_kg', 21.0, 0);
    $this->createReading('battery_voltage', 3.9, 2);
    $this->createReading('battery_voltage', 3.8, 0);

    $controller = new SensorDeviceController();
    $build = $controller->readings($this->device);

    $this->assertArrayHasKey('charts', $build);
    $this->assertArrayHasKey('metric_weight_kg', $build['charts']);
    $this->assertArrayHasKey('metric_battery_voltage', $build['charts']);
  }

  /**
   * Tests the `metric` query filter limits the page to just that metric.
   */
  public function testMetricFilterLimitsToOneMetric(): void {
    $this->createReading('weight_kg', 20.0, 2);
    $this->createReading('weight_kg', 21.0, 0);
    $this->createReading('battery_voltage', 3.9, 2);
    $this->createReading('battery_voltage', 3.8, 0);

    \Drupal::request()->query->set('metric', 'weight_kg');

    $controller = new SensorDeviceController();
    $build = $controller->readings($this->device);

    $this->assertArrayHasKey('metric_weight_kg', $build['charts']);
    $this->assertArrayNotHasKey('metric_battery_voltage', $build['charts']);
  }

  /**
   * Tests an unrecognised `metric` query value is ignored, not fatal.
   *
   * Falls back to every metric, exactly as if no filter were given —
   * matches `extractValidDate()`'s own "malformed input falls back to
   * the default" reasoning for date_from/date_to.
   */
  public function testUnrecognisedMetricFilterFallsBackToAll(): void {
    $this->createReading('weight_kg', 20.0, 2);
    $this->createReading('weight_kg', 21.0, 0);

    \Drupal::request()->query->set('metric', 'not_a_real_metric');

    $controller = new SensorDeviceController();
    $build = $controller->readings($this->device);

    $this->assertArrayHasKey('metric_weight_kg', $build['charts']);
  }

  /**
   * Tests a `date_from`/`date_to` range that excludes all data shows the empty state.
   */
  public function testDateRangeFilterExcludingAllDataShowsEmptyState(): void {
    $this->createReading('weight_kg', 20.0, 2);
    $this->createReading('weight_kg', 21.0, 0);

    $far_future = date('Y-m-d', \Drupal::time()->getRequestTime() + (365 * 86400));
    \Drupal::request()->query->set('date_from', $far_future);

    $controller = new SensorDeviceController();
    $build = $controller->readings($this->device);

    $this->assertArrayNotHasKey('charts', $build);
    $this->assertArrayHasKey('empty', $build);
  }

  /**
   * Tests a malformed `date_from` value doesn't cause a fatal error.
   *
   * Falls back to the device's own `created` time (full history) exactly
   * as if no `date_from` were given.
   */
  public function testMalformedDateFilterFallsBackToDefault(): void {
    $this->createReading('weight_kg', 20.0, 2);
    $this->createReading('weight_kg', 21.0, 0);

    \Drupal::request()->query->set('date_from', 'not-a-date');

    $controller = new SensorDeviceController();
    $build = $controller->readings($this->device);

    $this->assertArrayHasKey('metric_weight_kg', $build['charts']);
  }

  /**
   * Tests the filter form is included on the page.
   */
  public function testFilterFormIsIncluded(): void {
    $controller = new SensorDeviceController();
    $build = $controller->readings($this->device);

    $this->assertArrayHasKey('filter', $build);
    $this->assertArrayHasKey('metric', $build['filter']['filters']);
    $this->assertArrayHasKey('date_from', $build['filter']['filters']);
    $this->assertArrayHasKey('date_to', $build['filter']['filters']);
  }

  /**
   * Tests an outsider cannot view the page at all.
   */
  public function testReadingsDeniedForOutsider(): void {
    $this->setCurrentUser($this->outsider);

    $controller = new SensorDeviceController();
    $this->expectException(AccessDeniedHttpException::class);
    $controller->readings($this->device);
  }

  /**
   * Tests the readings route is registered and resolvable.
   */
  public function testReadingsRouteIsRegistered(): void {
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('entity.sensor_device.readings');

    $this->assertEquals('/hivelog/sensor-device/{sensor_device}/readings', $route->getPath());
  }

  /**
   * Tests the title callback includes the device's own label.
   */
  public function testReadingsTitleIncludesDeviceLabel(): void {
    $controller = new SensorDeviceController();
    $this->assertStringContainsString('VV-01 Scale', $controller->readingsTitle($this->device));
  }

}
