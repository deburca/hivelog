<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\hivelog\Delete\HivelogDeleteDependencyCounter;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 13 BLOCK relationships from ADR-0103 (task 0141).
 *
 * One test method per row (#7, apiary → sensor_device, is nanoprobe's own
 * row and is covered by that submodule's own kernel tests instead — see
 * modules/nanoprobe/tests/src/Kernel/). Each test builds its parent
 * entity fresh (never shared via setUp() with another BLOCK row's own
 * fixtures) so exactly one relationship is under test at a time: several
 * rows share a parent type with other BLOCK rows (Apiary has five,
 * CalendarAction has two), and a second, unrelated blocking child on the
 * same parent would make the "allowed once the one child is deleted"
 * half of the assertion false.
 *
 * `$this->owner` is deliberately a non-admin, apiary-owner-scoped user
 * (a real ownership check, not `administer hivelog`'s blanket bypass) —
 * ADR-0103's "administer hivelog can always resolve it" wording is about
 * an admin's ability to delete *someone else's* blocking children, not
 * an exemption from BLOCK on the parent itself (see
 * HivelogDeleteBlockingAccessTrait and testAdminIsNotExemptFromBlock()).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogDeleteBlockRelationshipsTest extends KernelTestBase {

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
   * A non-admin user who owns every apiary this test creates.
   */
  protected User $owner;

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
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    // Deliberately not installed: inventory_usage, harvest_yield and
    // every submodule child type (sensor_device, hive_insight, …) —
    // none of this test's own rows need them, and
    // HivelogDeleteDependencyCounter::countsFor() skips a registered
    // row whose child type isn't installed (checked via
    // EntityLastInstalledSchemaRepositoryInterface) rather than erroring,
    // precisely so a test only has to install what it actually uses.
    $this->installSchema('file', ['file_usage']);

    // A single "owner" role covering every "delete own X" permission a
    // BLOCK row's parent or child type in this test needs — mirrors
    // ApiaryScopedAccessTest's own role.
    $role = Role::create(['id' => 'owner', 'label' => 'Owner']);
    foreach ([
      'apiary', 'hive', 'hive inspection', 'queen', 'queen observation',
      'calendar action', 'hive action log', 'apiary action log',
      'inventory item', 'inventory purchase', 'product',
      'calendar action item requirement', 'calendar action product yield',
    ] as $type_label) {
      $role->grantPermission("delete own $type_label");
    }
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('owner');
    $this->owner->save();
    \Drupal::currentUser()->setAccount($this->owner);
  }

  /**
   * A fresh apiary owned by `$this->owner`.
   */
  protected function createApiary(): Apiary {
    $apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $this->owner->id()]);
    $apiary->save();
    return $apiary;
  }

  /**
   * A fresh hive in `$apiary`, owned by `$this->owner`.
   */
  protected function createHive(Apiary $apiary): Hive {
    $hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $hive->save();
    return $hive;
  }

  /**
   * A fresh calendar action on `$apiary`, owned by `$this->owner`.
   */
  protected function createCalendarAction(Apiary $apiary): CalendarAction {
    $calendar_action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Test Calendar Action',
      'description' => 'Desc.',
      'week_start' => 15,
      'uid' => $this->owner->id(),
    ]);
    $calendar_action->save();
    return $calendar_action;
  }

  /**
   * Asserts `$parent`'s delete access is forbidden, naming `$needle`.
   */
  protected function assertDeleteBlocked(object $parent, string $needle): void {
    $result = $parent->access('delete', $this->owner, TRUE);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
    $this->assertStringContainsString($needle, (string) $result->getReason());
  }

  /**
   * Forces the next access check to see the child just deleted.
   *
   * Two separate memoizations would otherwise show a stale "still
   * blocked" result within this same test method: the entity access
   * control handler's own per-request `$accessCache`, and (task 0134,
   * by design — see HivelogDeleteDependencyCounter's own docblock and
   * HivelogDeleteDependencyFrameworkTest::testCounterMemoizesPerRequest())
   * the delete-dependency counter's per-entity `countsFor()` memoization.
   * In real use this never matters — deleting the blocking child and
   * re-visiting the parent's delete page are two separate requests, each
   * with fresh service instances — so this helper exists only to make a
   * single kernel test method behave like those two requests.
   */
  protected function resetDeleteCaches(): void {
    $etm = \Drupal::entityTypeManager();
    \Drupal::getContainer()->set(
      'hivelog.delete_dependency_counter',
      new HivelogDeleteDependencyCounter($etm, \Drupal::service('entity.last_installed_schema.repository')),
    );
    $etm->clearCachedDefinitions();
  }

  // -------------------------------------------------------------------------
  // Apiary's BLOCK rows: #1, #3, #4, #5, #6.
  // -------------------------------------------------------------------------

  /**
   * Row #1: Apiary blocked by a Hive.
   */
  public function testApiaryBlockedByHive(): void {
    $apiary = $this->createApiary();
    $hive = $this->createHive($apiary);

    $this->assertDeleteBlocked($apiary, '1 hive');
    $hive->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($apiary->access('delete', $this->owner));
  }

  /**
   * Row #3: Apiary blocked by an ApiaryActionLog.
   */
  public function testApiaryBlockedByApiaryActionLog(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $log = ApiaryActionLog::create([
      'apiary' => $apiary->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $log->save();

    $this->assertDeleteBlocked($apiary, '1 apiary action log');
    $log->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($apiary->access('delete', $this->owner));
  }

  /**
   * Row #4: Apiary blocked by an InventoryItem.
   */
  public function testApiaryBlockedByInventoryItem(): void {
    $apiary = $this->createApiary();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();

    $this->assertDeleteBlocked($apiary, '1 inventory item');
    $item->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($apiary->access('delete', $this->owner));
  }

  /**
   * Row #5: Apiary blocked by an InventoryPurchase.
   */
  public function testApiaryBlockedByInventoryPurchase(): void {
    $apiary = $this->createApiary();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();
    $purchase = InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 1,
      'unit_price' => 1,
      'uid' => $this->owner->id(),
    ]);
    $purchase->save();

    $this->assertDeleteBlocked($apiary, '1 inventory purchase');
    $purchase->delete();
    // The item itself is also a BLOCK row's child (#4) — it only exists
    // here because InventoryPurchase requires one, so it has to go too
    // before the apiary is genuinely unblocked.
    $item->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($apiary->access('delete', $this->owner));
  }

  /**
   * Row #6: Apiary blocked by a Product.
   */
  public function testApiaryBlockedByProduct(): void {
    $apiary = $this->createApiary();
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
      'uid' => $this->owner->id(),
    ]);
    $product->save();

    $this->assertDeleteBlocked($apiary, '1 product');
    $product->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($apiary->access('delete', $this->owner));
  }

  // -------------------------------------------------------------------------
  // Hive's BLOCK rows: #9, #10.
  // -------------------------------------------------------------------------

  /**
   * Row #9: Hive blocked by a HiveInspection.
   */
  public function testHiveBlockedByHiveInspection(): void {
    $apiary = $this->createApiary();
    $hive = $this->createHive($apiary);
    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2026-01-01',
      'uid' => $this->owner->id(),
    ]);
    $inspection->save();

    $this->assertDeleteBlocked($hive, '1 hive inspection');
    $inspection->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($hive->access('delete', $this->owner));
  }

  /**
   * Row #10: Hive blocked by a HiveActionLog.
   */
  public function testHiveBlockedByHiveActionLog(): void {
    $apiary = $this->createApiary();
    $hive = $this->createHive($apiary);
    $calendar_action = $this->createCalendarAction($apiary);
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $log->save();

    $this->assertDeleteBlocked($hive, '1 hive action log');
    $log->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($hive->access('delete', $this->owner));
  }

  // -------------------------------------------------------------------------
  // Queen's BLOCK row: #14.
  // -------------------------------------------------------------------------

  /**
   * Row #14: Queen blocked by a QueenObservation.
   */
  public function testQueenBlockedByQueenObservation(): void {
    $apiary = $this->createApiary();
    $hive = $this->createHive($apiary);
    $queen = Queen::create([
      'name' => 'Test Queen',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $queen->save();
    $observation = QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2026-01-01',
      'health' => 'good',
      'uid' => $this->owner->id(),
    ]);
    $observation->save();

    $this->assertDeleteBlocked($queen, '1 queen observation');
    $observation->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($queen->access('delete', $this->owner));
  }

  // -------------------------------------------------------------------------
  // CalendarAction's BLOCK rows: #17, #18.
  // -------------------------------------------------------------------------

  /**
   * Row #17: CalendarAction blocked by a HiveActionLog.
   */
  public function testCalendarActionBlockedByHiveActionLog(): void {
    $apiary = $this->createApiary();
    $hive = $this->createHive($apiary);
    $calendar_action = $this->createCalendarAction($apiary);
    $log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $log->save();

    $this->assertDeleteBlocked($calendar_action, '1 hive action log');
    $log->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($calendar_action->access('delete', $this->owner));
  }

  /**
   * Row #18: CalendarAction blocked by an ApiaryActionLog.
   */
  public function testCalendarActionBlockedByApiaryActionLog(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $log = ApiaryActionLog::create([
      'apiary' => $apiary->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $log->save();

    $this->assertDeleteBlocked($calendar_action, '1 apiary action log');
    $log->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($calendar_action->access('delete', $this->owner));
  }

  // -------------------------------------------------------------------------
  // InventoryItem's and Product's BLOCK rows: #24, #25.
  // -------------------------------------------------------------------------

  /**
   * Row #24: InventoryItem blocked by a CalendarActionItemRequirement.
   */
  public function testInventoryItemBlockedByCalendarActionItemRequirement(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();
    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 2,
    ]);
    $requirement->save();

    $this->assertDeleteBlocked($item, '1 calendar action item requirement');
    $requirement->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($item->access('delete', $this->owner));
  }

  /**
   * Row #25: Product blocked by a CalendarActionProductYield.
   */
  public function testProductBlockedByCalendarActionProductYield(): void {
    $apiary = $this->createApiary();
    $calendar_action = $this->createCalendarAction($apiary);
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
      'uid' => $this->owner->id(),
    ]);
    $product->save();
    $yield = CalendarActionProductYield::create([
      'calendar_action' => $calendar_action->id(),
      'product' => $product->id(),
      'quantity' => 20,
    ]);
    $yield->save();

    $this->assertDeleteBlocked($product, '1 calendar action product yield');
    $yield->delete();
    $this->resetDeleteCaches();
    $this->assertTrue($product->access('delete', $this->owner));
  }

  // -------------------------------------------------------------------------
  // Route access vs. the `delete` operation, and the admin non-exemption.
  // -------------------------------------------------------------------------

  /**
   * The `delete_route` op stays allowed while `delete` is blocked.
   *
   * `entity.apiary.delete_form`'s route uses `apiary.delete_route`
   * precisely so a blocked delete still reaches the form (which renders
   * the "Can't delete yet" state) instead of a bare 403 — see
   * HivelogDeleteBlockingAccessTrait's docblock and
   * docs/project-management/tasks/0141-delete-block-relationships.md.
   * Meanwhile the plain `delete` op — used by every Delete button/tab
   * that calls `access('delete')` directly — is forbidden, so those
   * disappear.
   */
  public function testDeleteRouteAccessStaysAllowedWhileDeleteOpIsBlocked(): void {
    $apiary = $this->createApiary();
    $this->createHive($apiary);

    $this->assertFalse($apiary->access('delete', $this->owner));
    $this->assertTrue($apiary->access('delete_route', $this->owner));
  }

  /**
   * The `administer hivelog` permission does not exempt a BLOCK row.
   *
   * ADR-0103's "an administer hivelog user can always resolve it" is
   * about an admin's ability to delete *someone else's* blocking
   * children (their blanket `delete any X`), not an exemption from
   * BLOCK on the parent — an admin still has to delete the hive first.
   */
  public function testAdminIsNotExemptFromBlock(): void {
    $admin_role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('admin');
    $admin->save();

    $apiary = $this->createApiary();
    $this->createHive($apiary);

    $result = $apiary->access('delete', $admin, TRUE);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

}
