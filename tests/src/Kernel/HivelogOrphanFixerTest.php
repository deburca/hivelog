<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Delete\HivelogOrphanFinder;
use Drupal\hivelog\Delete\HivelogOrphanFixer;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `HivelogOrphanFixer` (task 0145).
 *
 * See `HivelogOrphanFinderTest`'s own class docblock for why a dangling
 * reference is planted directly (a bogus target ID) rather than via a
 * live parent delete.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogOrphanFixerTest extends KernelTestBase {

  /**
   * A target ID that is never a real entity in any of these tests.
   */
  protected const BOGUS_ID = 999999;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * The finder service.
   */
  protected function finder(): HivelogOrphanFinder {
    return \Drupal::service('hivelog.orphan_finder');
  }

  /**
   * The fixer service.
   */
  protected function fixer(): HivelogOrphanFixer {
    return \Drupal::service('hivelog.orphan_fixer');
  }

  /**
   * Deletes a BLOCK row's orphans.
   */
  public function testFixDeletesBlockRowOrphan(): void {
    $orphan = Hive::create([
      'name' => 'Orphan Hive',
      'apiary' => self::BOGUS_ID,
      'status' => 'active',
    ]);
    $orphan->save();
    $id = $orphan->id();

    $result = $this->fixer()->fix();

    $this->assertNull(Hive::load($id));
    $this->assertSame([], $this->finder()->findOrphans());
    $this->assertCount(1, $result['fixed']);
    $this->assertSame('1', $result['fixed'][0]['adr_row']);
    $this->assertSame(1, $result['fixed'][0]['count']);
    $this->assertSame([], $result['warned']);
  }

  /**
   * Deletes a CASCADE row's orphans, taking their own CASCADE children too.
   *
   * Exactly as a live delete would.
   */
  public function testFixDeletesCascadeRowOrphanAndItsOwnCascadeChildren(): void {
    $orphan_action = CalendarAction::create([
      'apiary' => self::BOGUS_ID,
      'title' => 'Orphaned Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $orphan_action->save();
    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $orphan_action->id(),
      // A dangling `item` too — this row is a BLOCK, not under test
      // here; the point is only that the requirement itself, valid or
      // not, is CASCADE-owned by the calendar action and must go with it.
      'item' => self::BOGUS_ID,
      'quantity' => 1,
    ]);
    $requirement->save();

    $this->fixer()->fix();

    $this->assertNull(CalendarAction::load($orphan_action->id()));
    $this->assertNull(CalendarActionItemRequirement::load($requirement->id()));
  }

  /**
   * Clears a DETACH row's dangling reference and applies its side effect.
   *
   * A detached queen goes `inactive`.
   */
  public function testFixDetachesAndAppliesSideEffect(): void {
    $orphan = Queen::create([
      'name' => 'Orphan Queen',
      'hive' => self::BOGUS_ID,
      'status' => 'active',
      'origin' => 'Bred',
    ]);
    $orphan->save();

    $result = $this->fixer()->fix();

    $reloaded = Queen::load($orphan->id());
    $this->assertNotNull($reloaded, 'DETACH keeps the child, unlike BLOCK/CASCADE.');
    $this->assertTrue($reloaded->get('hive')->isEmpty());
    $this->assertEquals('inactive', $reloaded->get('status')->value);
    $this->assertSame('11', $result['fixed'][0]['adr_row']);
  }

  /**
   * Never touches a WARN row's dangling reference.
   */
  public function testFixLeavesWarnRowOrphanUntouched(): void {
    $apiary = Apiary::create(['name' => 'Orphan Fixer Apiary']);
    $apiary->save();
    $orphan = InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => self::BOGUS_ID,
      'purchase_date' => '2026-01-01',
      'quantity' => 1,
      'unit_price' => 1,
    ]);
    $orphan->save();

    $result = $this->fixer()->fix();

    $reloaded = InventoryPurchase::load($orphan->id());
    $this->assertNotNull($reloaded, 'WARN rows are never deleted.');
    $this->assertEquals(self::BOGUS_ID, $reloaded->get('item')->target_id, "WARN rows' dangling reference is never cleared either.");
    $this->assertSame([], $result['fixed']);
    $this->assertCount(1, $result['warned']);
    $this->assertSame('22', $result['warned'][0]['adr_row']);
    // Still findable afterwards — nothing about fixing other rows
    // makes a WARN orphan disappear from the report.
    $this->assertNotEmpty($this->finder()->findOrphans());
  }

  /**
   * A dry run (`$dry_run` true) changes nothing.
   */
  public function testDryRunChangesNothing(): void {
    $orphan = Hive::create([
      'name' => 'Orphan Hive',
      'apiary' => self::BOGUS_ID,
      'status' => 'active',
    ]);
    $orphan->save();

    $result = $this->fixer()->fix(TRUE);

    $this->assertNotNull(Hive::load($orphan->id()), 'Dry run must not actually delete anything.');
    $this->assertCount(1, $result['fixed']);
    $this->assertSame(1, $result['passes'], 'A dry run never loops for a second pass — nothing changed for it to re-check.');
    $this->assertNotEmpty($this->finder()->findOrphans(), 'The orphan is still there after a dry run.');
  }

  /**
   * Fixing one row's orphan can create a new orphan elsewhere.
   *
   * `fix()` catches it in a later pass instead of leaving it behind.
   * An orphaned Hive (row #1, BLOCK) has its own HiveInspection (row
   * #9, also BLOCK — never auto-cascaded by a live delete either).
   * Deleting the orphaned hive therefore leaves the inspection newly
   * dangling; `fix()` must find and delete that too, in a second pass,
   * within the same call.
   */
  public function testFixRecursesAcrossPassesForNewlyCreatedOrphans(): void {
    $orphan_hive = Hive::create(['name' => 'Orphan Hive', 'apiary' => self::BOGUS_ID, 'status' => 'active']);
    $orphan_hive->save();
    $inspection = HiveInspection::create([
      'hive' => $orphan_hive->id(),
      'inspection_date' => '2026-01-01',
    ]);
    $inspection->save();

    $result = $this->fixer()->fix();

    $this->assertNull(Hive::load($orphan_hive->id()));
    $this->assertNull(HiveInspection::load($inspection->id()), 'The now-orphaned inspection must be cleaned up too, not left behind.');
    $this->assertSame([], $this->finder()->findOrphans());
    $this->assertGreaterThanOrEqual(2, $result['passes'], 'This scenario requires more than one find/fix pass.');

    $adr_rows = array_column($result['fixed'], 'adr_row');
    $this->assertContains('1', $adr_rows);
    $this->assertContains('9', $adr_rows);
  }

  /**
   * Valid data is left alone by `fix()`.
   */
  public function testFixLeavesValidDataAlone(): void {
    $apiary = Apiary::create(['name' => 'Valid Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Valid Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Valid Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    $result = $this->fixer()->fix();

    $this->assertNotNull(Apiary::load($apiary->id()));
    $this->assertNotNull(Hive::load($hive->id()));
    $this->assertNotNull(InventoryItem::load($item->id()));
    $this->assertSame([], $result['fixed']);
    $this->assertSame([], $result['warned']);
    $this->assertSame(1, $result['passes']);
  }

}
