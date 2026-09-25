<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Delete\HivelogDeleteDependencyRegistry;
use Drupal\hivelog\Delete\HivelogOrphanFinder;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `HivelogOrphanFinder` (task 0145).
 *
 * A dangling reference is planted directly — saving a child entity
 * whose reference field points at an ID that was never a real entity —
 * rather than by deleting a live parent, since a BLOCK/CASCADE/DETACH
 * row's own enforcement (tasks 0141-0143) would either refuse the
 * delete or already clean the child up. This is also, in effect, the
 * realistic shape of the historical orphans this task exists for: a
 * dangling reference is exactly what `->save()` allows without
 * `->validate()` ever running (see `ContentEntityBase::save()`'s own
 * contract), which is precisely how they arise in practice — an import,
 * a script, or (as on `cms2`) a delete that predates this framework.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogOrphanFinderTest extends KernelTestBase {

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
    $this->installEntitySchema('calendar_action');
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
   * A fresh apiary.
   */
  protected function apiary(): Apiary {
    $apiary = Apiary::create(['name' => 'Orphan Finder Apiary']);
    $apiary->save();
    return $apiary;
  }

  /**
   * The row for a given `adr_row`, or NULL if `findOrphans()` didn't find it.
   */
  protected function rowFor(array $rows, string $adr_row): ?array {
    foreach ($rows as $row) {
      if ($row['adr_row'] === $adr_row) {
        return $row;
      }
    }
    return NULL;
  }

  /**
   * Entirely valid data produces no orphans at all.
   */
  public function testNoOrphansWhenEverythingValid(): void {
    $apiary = $this->apiary();
    $hive = Hive::create(['name' => 'Valid Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Feeding',
      'description' => 'Desc.',
      'week_start' => 10,
    ])->save();
    Queen::create(['name' => 'Valid Queen', 'hive' => $hive->id(), 'status' => 'active', 'origin' => 'Bred'])->save();

    $this->assertSame([], $this->finder()->findOrphans());
  }

  /**
   * A BLOCK row's dangling reference is found, with the right IDs.
   */
  public function testFindsBlockRowOrphan(): void {
    $apiary = $this->apiary();
    $valid_hive = Hive::create(['name' => 'Valid Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $valid_hive->save();
    $orphan_hive = Hive::create(['name' => 'Orphan Hive', 'apiary' => self::BOGUS_ID, 'status' => 'active']);
    $orphan_hive->save();

    $rows = $this->finder()->findOrphans();
    $row = $this->rowFor($rows, '1');
    $this->assertNotNull($row, 'Row #1 (apiary -> hive, BLOCK) must be reported.');
    $this->assertSame(HivelogDeleteDependencyRegistry::BLOCK, $row['treatment']);
    $this->assertSame([$orphan_hive->id()], $row['orphan_ids']);
  }

  /**
   * A CASCADE row's dangling reference is found.
   */
  public function testFindsCascadeRowOrphan(): void {
    $orphan = CalendarAction::create([
      'apiary' => self::BOGUS_ID,
      'title' => 'Orphaned Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $orphan->save();

    $rows = $this->finder()->findOrphans();
    $row = $this->rowFor($rows, '2');
    $this->assertNotNull($row, 'Row #2 (apiary -> calendar_action, CASCADE) must be reported.');
    $this->assertSame(HivelogDeleteDependencyRegistry::CASCADE, $row['treatment']);
    $this->assertSame([$orphan->id()], $row['orphan_ids']);
  }

  /**
   * A DETACH row's dangling reference is found.
   */
  public function testFindsDetachRowOrphan(): void {
    $orphan = Queen::create([
      'name' => 'Orphan Queen',
      'hive' => self::BOGUS_ID,
      'status' => 'active',
      'origin' => 'Bred',
    ]);
    $orphan->save();

    $rows = $this->finder()->findOrphans();
    $row = $this->rowFor($rows, '11');
    $this->assertNotNull($row, 'Row #11 (hive -> queen, DETACH) must be reported.');
    $this->assertSame(HivelogDeleteDependencyRegistry::DETACH, $row['treatment']);
    $this->assertSame([$orphan->id()], $row['orphan_ids']);
  }

  /**
   * A WARN row's dangling reference is found (report-only, still surfaced).
   */
  public function testFindsWarnRowOrphan(): void {
    $apiary = $this->apiary();
    $orphan = InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => self::BOGUS_ID,
      'purchase_date' => '2026-01-01',
      'quantity' => 1,
      'unit_price' => 1,
    ]);
    $orphan->save();

    $rows = $this->finder()->findOrphans();
    $row = $this->rowFor($rows, '22');
    $this->assertNotNull($row, 'Row #22 (inventory_item -> inventory_purchase, WARN) must be reported.');
    $this->assertSame(HivelogDeleteDependencyRegistry::WARN, $row['treatment']);
    $this->assertSame([$orphan->id()], $row['orphan_ids']);
  }

  /**
   * A valid item is never reported alongside a genuinely orphaned one.
   */
  public function testValidReferenceNotFlaggedAlongsideOrphan(): void {
    $apiary = $this->apiary();
    $valid_item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Valid Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $valid_item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $valid_item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 1,
      'unit_price' => 1,
    ])->save();
    $orphan = InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => self::BOGUS_ID,
      'purchase_date' => '2026-01-02',
      'quantity' => 1,
      'unit_price' => 1,
    ]);
    $orphan->save();

    $row = $this->rowFor($this->finder()->findOrphans(), '22');
    $this->assertSame([$orphan->id()], $row['orphan_ids']);
  }

  /**
   * With zero valid parents at all, every non-empty reference is an orphan.
   *
   * Exercises the empty-`NOT IN`-list branch specifically, which is
   * ambiguous across query backends if handled naively.
   */
  public function testZeroValidParentsTreatsEveryReferenceAsOrphan(): void {
    $orphan_a = Hive::create(['name' => 'Orphan A', 'apiary' => self::BOGUS_ID, 'status' => 'active']);
    $orphan_a->save();
    $orphan_b = Hive::create(['name' => 'Orphan B', 'apiary' => self::BOGUS_ID + 1, 'status' => 'active']);
    $orphan_b->save();

    // No Apiary at all exists in this test.
    $row = $this->rowFor($this->finder()->findOrphans(), '1');
    $this->assertNotNull($row);
    $this->assertEqualsCanonicalizing([$orphan_a->id(), $orphan_b->id()], $row['orphan_ids']);
  }

}
