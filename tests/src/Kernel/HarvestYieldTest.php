<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Harvest Yield entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HarvestYieldTest extends KernelTestBase {

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
  ];

  /**
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * A test calendar action, belonging to `$apiary`.
   */
  protected CalendarAction $calendarAction;

  /**
   * A test hive action log against `$calendarAction`.
   */
  protected HiveActionLog $hiveActionLog;

  /**
   * A test product, belonging to `$apiary`.
   */
  protected Product $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest Summer Honey',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $this->calendarAction->save();

    $this->hiveActionLog = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $this->hiveActionLog->save();

    $this->product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $this->product->save();
  }

  /**
   * Tests creating, updating and deleting a yield record.
   */
  public function testCrud(): void {
    $yield = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 20,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $yield->save();

    $loaded = HarvestYield::load($yield->id());
    $this->assertEquals($this->product->id(), $loaded->get('product')->target_id);
    $this->assertEquals(20, $loaded->get('quantity')->value);
    $this->assertEquals($this->hiveActionLog->id(), $loaded->get('hive_action_log')->target_id);
    $this->assertTrue($loaded->get('apiary_action_log')->isEmpty());
    $this->assertStringContainsString('Honey', (string) $loaded->label());
    $this->assertStringContainsString('20', (string) $loaded->label());
    $this->assertStringContainsString('kg', (string) $loaded->label());

    // Update.
    $yield->set('quantity', 25);
    $yield->save();
    $this->assertEquals(25, HarvestYield::load($yield->id())->get('quantity')->value);

    // Delete.
    $id = $yield->id();
    $yield->delete();
    $this->assertNull(HarvestYield::load($id));
  }

  /**
   * Tests that exactly one of hive_action_log/apiary_action_log is required.
   */
  public function testExactlyOneLogReferenceRequired(): void {
    $neither = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 20,
    ]);
    $this->expectException(\Exception::class);
    $neither->save();
  }

  /**
   * Tests that setting both log references is rejected.
   */
  public function testBothLogReferencesRejected(): void {
    $apiary_action_log = \Drupal::entityTypeManager()->getStorage('apiary_action_log')->create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $apiary_action_log->save();

    $both = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 20,
      'hive_action_log' => $this->hiveActionLog->id(),
      'apiary_action_log' => $apiary_action_log->id(),
    ]);
    $this->expectException(\Exception::class);
    $both->save();
  }

  /**
   * Tests that `unit_price_snapshot` is derived from the product's expected price.
   */
  public function testUnitPriceSnapshotIsDerivedFromProduct(): void {
    $yield = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 20,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $yield->save();

    $this->assertEquals(12.0, (float) HarvestYield::load($yield->id())->get('unit_price_snapshot')->value);
  }

  /**
   * Tests that the price snapshot survives a later edit and price change.
   */
  public function testUnitPriceSnapshotIsImmutableAfterCreation(): void {
    $yield = HarvestYield::create([
      'product' => $this->product->id(),
      'quantity' => 20,
      'hive_action_log' => $this->hiveActionLog->id(),
    ]);
    $yield->save();
    $this->assertEquals(12.0, (float) HarvestYield::load($yield->id())->get('unit_price_snapshot')->value);

    // Raising the product's expected price should not retroactively
    // change the already-recorded snapshot.
    $this->product->set('expected_unit_price', 50);
    $this->product->save();

    $yield->set('quantity', 25);
    $yield->save();
    $this->assertEquals(12.0, (float) HarvestYield::load($yield->id())->get('unit_price_snapshot')->value);
  }

}
