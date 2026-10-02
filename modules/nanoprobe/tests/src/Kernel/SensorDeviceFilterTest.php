<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filters on the Sensor Devices list (task 0157).
 *
 * `/hivelog/sensor-devices` (previously unfiltered) now carries
 * `SensorDeviceFilterForm`, full-page-only, mirroring task 0132/0155's
 * own established shape. Not to be confused with `SensorReadingFilterForm`
 * (task 0110), which filters one device's own reading history.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorDeviceFilterTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('sensor_device');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Pushes a request onto the stack, routed as `$route_name`.
   *
   * Matches the request through the real router first, so
   * `current_route_match` (and therefore `Url::fromRoute('<current>')`,
   * which Reset relies on) resolves to this route — a bare
   * `Request::create()` alone leaves no route attributes set.
   */
  protected function pushRoutedRequest(string $route_name, string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests the list filters by scope and Reset clears the query string.
   */
  public function testListScopeFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id()]);
    $hive->save();

    SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
      'transport' => 'wifi',
    ])->save();
    SensorDevice::create([
      'label' => 'VV-01 Scale',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'transport' => 'lorawan',
    ])->save();

    $this->pushRoutedRequest('entity.sensor_device.collection', '/hivelog/sensor-devices', ['scope' => 'hive']);
    $build = \Drupal::entityTypeManager()->getListBuilder('sensor_device')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('VV-01 Scale', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/sensor-devices', $reset_url);
    $this->assertStringNotContainsString('scope', $reset_url);
  }

  /**
   * Tests the list filters by device type, transport and enabled.
   */
  public function testListDeviceTypeTransportAndEnabledFilter(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();

    SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
      'transport' => 'wifi',
      'enabled' => TRUE,
    ])->save();
    SensorDevice::create([
      'label' => 'Retired Scale',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'weight',
      'transport' => 'lorawan',
      'enabled' => FALSE,
    ])->save();

    $this->pushRoutedRequest('entity.sensor_device.collection', '/hivelog/sensor-devices', [
      'device_type' => 'weight',
      'transport' => 'lorawan',
    ]);
    $build = \Drupal::entityTypeManager()->getListBuilder('sensor_device')->render();
    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Retired Scale', (string) $build['table']['#props']['rows'][0]['cells'][0]);

    $this->pushRoutedRequest('entity.sensor_device.collection', '/hivelog/sensor-devices', ['enabled' => '0']);
    $build = \Drupal::entityTypeManager()->getListBuilder('sensor_device')->render();
    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Retired Scale', (string) $build['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Tests the list's empty state distinguishes filtered from unfiltered.
   */
  public function testListEmptyStateDistinguishesFilteredFromUnfiltered(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    SensorDevice::create([
      'label' => 'Weather Station',
      'apiary' => $apiary->id(),
      'scope' => 'apiary',
      'device_type' => 'temperature_humidity',
    ])->save();

    $this->pushRoutedRequest('entity.sensor_device.collection', '/hivelog/sensor-devices', ['device_type' => 'gps']);
    $build = \Drupal::entityTypeManager()->getListBuilder('sensor_device')->render();

    $this->assertCount(0, $build['table']['#props']['rows']);
    $this->assertStringContainsString('match the current filters', $build['table']['#props']['empty_message']);
  }

  /**
   * Extracts the rendered Reset button's URL from a list builder's build.
   */
  protected function resetUrl(array $build): string {
    $reset = $build['filter']['filter_actions']['reset']['#props']['url'] ?? NULL;
    $this->assertNotNull($reset, 'Reset button was not rendered.');
    return $reset;
  }

  /**
   * Asserts the list pages over its filtered set and keeps the filter.
   *
   * Task 0170. Five rows are expected to exist, three matching `$query`;
   * page size is forced to two.
   */
  protected function assertPagesOverFilteredSet(string $entity_type, string $route_name, string $path, array $query): void {
    $list_builder = \Drupal::entityTypeManager()->getListBuilder($entity_type);
    (new \ReflectionProperty($list_builder, 'limit'))->setValue($list_builder, 2);

    $this->pushRoutedRequest($route_name, $path, $query);
    $build = $list_builder->render();
    $this->assertCount(2, $build['table']['#props']['rows'], 'Page one holds a full page of matching rows.');
    $this->assertSame('pager', $build['pager']['#type']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['pager']);
    $key = array_key_first($query);
    $this->assertStringContainsString($key . '=' . $query[$key], $html, 'Pager links must carry the active filter.');

    $this->pushRoutedRequest($route_name, $path, $query + ['page' => '1']);
    $build = $list_builder->render();
    $this->assertCount(1, $build['table']['#props']['rows'], 'Page two holds only the remaining matching row.');
  }

  /**
   * Tests the Sensor Devices list pages over the filtered set (task 0170).
   */
  public function testListPagesOverFilteredSet(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id()]);
    $hive->save();
    foreach ([1, 2, 3] as $i) {
      SensorDevice::create([
        'label' => "Scale $i",
        'apiary' => $apiary->id(),
        'hive' => $hive->id(),
        'scope' => 'hive',
        'device_type' => 'weight',
        'transport' => 'lorawan',
      ])->save();
    }
    foreach ([1, 2] as $i) {
      SensorDevice::create([
        'label' => "Station $i",
        'apiary' => $apiary->id(),
        'scope' => 'apiary',
        'device_type' => 'temperature_humidity',
        'transport' => 'wifi',
      ])->save();
    }
    $this->assertPagesOverFilteredSet('sensor_device', 'entity.sensor_device.collection', '/hivelog/sensor-devices', ['scope' => 'hive']);
  }

}
