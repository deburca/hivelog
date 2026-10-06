<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\nanoprobe\Entity\SensorReading;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the hive stat tiles an app token gets from nanoprobe (task 0216).
 *
 * Until then the field-app role had no sensor permission, so a beekeeper's
 * token saw no sensor tiles at all. The role here is the real one the module
 * installs, so a pass shows the app's own permission set is enough.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiSensorTilesTest extends HivelogApiKernelTestBase {

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
    'serialization',
    'jsonapi',
    'basic_auth',
    'consumers',
    'simple_oauth',
    'hivelog',
    'nanoprobe',
    'hivelog_api',
    'hivelog_api_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // The role is synced inside the parent's setUp(), when the sensor
    // permissions already exist because nanoprobe is enabled.
    parent::setUp();
    foreach (['sensor_device', 'sensor_reading', 'sensor_reading_daily'] as $type) {
      $this->installEntitySchema($type);
    }
  }

  /**
   * Adds a hive-scoped device with one reading to a hive.
   */
  protected function deviceWithReading($apiary, $hive, string $name, string $type, string $metric, float $value): SensorDevice {
    $device = SensorDevice::create([
      'name' => $name,
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'device_type' => $type,
      'scope' => 'hive',
      'enabled' => TRUE,
    ]);
    $device->save();
    SensorReading::create([
      'sensor_device' => $device->id(),
      'metric' => $metric,
      'value' => $value,
      'recorded' => \Drupal::time()->getRequestTime() - 3600,
    ])->save();
    return $device;
  }

  /**
   * Tests the owner's token gets the sensor tiles and the net weight.
   */
  public function testTheOwnerGetsSensorTilesAndNetWeight(): void {
    $apiary = $this->fixtures['my_apiary'];
    $hive = $this->fixtures['my_hive'];
    $weight = $this->deviceWithReading($apiary, $hive, 'Scale', 'weight', 'weight_kg', 30.0);
    $temperature = $this->deviceWithReading($apiary, $hive, 'Probe', 'temperature_humidity', 'temp_internal_c', 34.5);

    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Brood box',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 10,
      'weight_kg' => 5.0,
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 3,
      'unit_price' => 20,
    ])->save();
    HiveComponent::create(['hive' => $hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();

    $response = $this->api('GET', '/hivelog/api/v1/computed/hive/' . $hive->uuid() . '/stat-tiles', NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);

    $tiles = array_column($response['body']['data'], NULL, 'key');
    $this->assertArrayHasKey('nanoprobe_sensor_' . $weight->id(), $tiles);
    $this->assertArrayHasKey('nanoprobe_sensor_' . $temperature->id(), $tiles);
    $this->assertSame('30 kg', $tiles['nanoprobe_sensor_' . $weight->id()]['value']);
    $this->assertArrayHasKey('nanoprobe_net_weight', $tiles);
    $this->assertSame('20 kg', $tiles['nanoprobe_net_weight']['value'], '30 kg on the scale less 2 x 5 kg of hive');
    $this->assertEquals(10, $response['body']['meta']['empty_weight_kg']);
  }

  /**
   * Tests the tiles of another beekeeper's hive are refused, not leaked.
   */
  public function testAnotherBeekeepersSensorTilesAreNotServed(): void {
    $this->deviceWithReading($this->fixtures['their_apiary'], $this->fixtures['their_hive'], 'Their scale', 'weight', 'weight_kg', 44.0);
    $uuid = $this->fixtures['their_hive']->uuid();

    $this->assertSame(403, $this->api('GET', "/hivelog/api/v1/computed/hive/$uuid/stat-tiles", NULL, $this->me)['status']);
    $mine = $this->api('GET', '/hivelog/api/v1/computed/hive/' . $this->fixtures['my_hive']->uuid() . '/stat-tiles', NULL, $this->me);
    $this->assertSame(200, $mine['status']);
    $this->assertStringNotContainsString('Their scale', $mine['raw']);
  }

  /**
   * Tests the sensors stay off the API's allow-list: tiles only, no records.
   */
  public function testSensorsAreNotServedAsResources(): void {
    $device = $this->deviceWithReading($this->fixtures['my_apiary'], $this->fixtures['my_hive'], 'Scale', 'weight', 'weight_kg', 30.0);
    foreach (['sensor_device', 'sensor_reading'] as $type) {
      $this->assertSame(404, $this->api('GET', "/hivelog/api/v1/$type/$type", NULL, $this->me)['status'], $type);
    }
    $this->assertSame(404, $this->api('GET', '/hivelog/api/v1/sensor_device/sensor_device/' . $device->uuid(), NULL, $this->me)['status']);
  }

}
