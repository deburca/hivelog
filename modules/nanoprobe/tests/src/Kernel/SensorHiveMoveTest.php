<?php

declare(strict_types=1);

namespace Drupal\Tests\nanoprobe\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\nanoprobe\Entity\SensorDevice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the sensors part of the hive move dialog (task 0231).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class SensorHiveMoveTest extends KernelTestBase {

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
   * The apiary the hive starts in.
   */
  protected Apiary $source;

  /**
   * The apiary it moves to.
   */
  protected Apiary $target;

  /**
   * The hive.
   */
  protected Hive $hive;

  /**
   * A device scoped to the hive.
   */
  protected SensorDevice $device;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_component', 'inventory_item',
      'inventory_purchase', 'sensor_device'
] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    $this->source = Apiary::create(['name' => 'Source']);
    $this->source->save();
    $this->target = Apiary::create(['name' => 'Target']);
    $this->target->save();
    $this->hive = Hive::create(['name' => 'H-1', 'apiary' => $this->source->id(), 'status' => 'active']);
    $this->hive->save();
    $this->device = SensorDevice::create([
      'label' => 'Scale',
      'apiary' => $this->source->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'device_type' => 'weight',
      'transport' => 'lorawan',
    ]);
    $this->device->save();
  }

  /**
   * Tests the dialog lists a hive-scoped sensor, and nothing for a bare hive.
   */
  public function testSensorsArePartOfTheDialog(): void {
    $mover = \Drupal::service('hivelog.hive_mover');
    $parts = $mover->dependents($this->hive);

    $this->assertSame(['sensors'], array_keys($parts));
    $this->assertSame(['Scale'], $parts['sensors']['items']);
    $this->assertSame(['move', 'detach'], array_keys($parts['sensors']['choices']));

    $bare = Hive::create(['name' => 'H-2', 'apiary' => $this->source->id(), 'status' => 'active']);
    $bare->save();
    $this->assertSame([], $mover->dependents($bare));
  }

  /**
   * Tests moving a sensor with the hive puts it in the target apiary.
   */
  public function testMovingTheSensorWithTheHive(): void {
    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->target, ['sensors' => 'move']);

    $device = SensorDevice::load($this->device->id());
    $this->assertSame('hive', $device->get('scope')->value);
    $this->assertSame((string) $this->hive->id(), (string) $device->get('hive')->target_id);
    $this->assertSame((string) $this->target->id(), (string) $device->get('apiary')->target_id);
  }

  /**
   * Tests detaching the sensor leaves it apiary-wide in the source apiary.
   */
  public function testDetachingTheSensor(): void {
    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->target, ['sensors' => 'detach']);

    $device = SensorDevice::load($this->device->id());
    $this->assertSame('apiary', $device->get('scope')->value);
    $this->assertEmpty($device->get('hive')->target_id);
    $this->assertSame((string) $this->source->id(), (string) $device->get('apiary')->target_id);
    $this->assertSame((string) $this->target->id(), (string) Hive::load($this->hive->id())->get('apiary')->target_id);
  }

  /**
   * Tests a sensor needs a choice before the hive moves, and none is changed.
   */
  public function testSensorWithoutChoiceStopsTheMove(): void {
    try {
      \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->target, []);
      $this->fail('A hive with a sensor must not move without a choice.');
    }
    catch (\InvalidArgumentException) {
      // Expected.
    }
    $this->assertSame((string) $this->source->id(), (string) Hive::load($this->hive->id())->get('apiary')->target_id);
    $this->assertSame('hive', SensorDevice::load($this->device->id())->get('scope')->value);
  }

}
