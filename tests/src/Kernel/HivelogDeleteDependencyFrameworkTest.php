<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\hivelog\Delete\HivelogDeleteDependencyCounter;
use Drupal\hivelog\Delete\HivelogDeleteDependencyRegistry;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog_delete_dependency_test\Entity\DeleteTestChild;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the delete-dependency framework itself (task 0134).
 *
 * `HivelogDeleteDependencyRegistry`'s merge/filter/link resolution and
 * `HivelogDeleteDependencyCounter`'s counting, per-request memoization
 * and access integration are tested against a test-only relationship
 * (`hivelog_delete_dependency_test`'s `DeleteTestChild`, BLOCK-treated,
 * with a real `delete own` / `delete any` split so the "deletable" count
 * has something genuine to compute) — decoupled from ADR-0103's real 24
 * rows, so neither can break the other. `HivelogEntityDeleteForm`'s
 * section rendering is then checked once per treatment against real
 * rows (CalendarAction/InventoryPurchase/Queen), since exercising the
 * real base delete form is the actual point of that half.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogDeleteDependencyFrameworkTest extends KernelTestBase {

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
    'hivelog_delete_dependency_test',
  ];

  /**
   * A shared Apiary.
   */
  protected Apiary $apiary;

  /**
   * The test's current user, with `delete own` but not `delete any`.
   */
  protected User $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    // The counter iterates every registered row for whichever parent
    // type it's asked about, regardless of which fixtures a given test
    // actually creates — so every core child type needs its schema
    // installed, not just the ones this test's own fixtures touch.
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
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('hivelog_delete_test_child');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'owner', 'label' => 'Owner']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('owner');
    $this->owner->save();
    \Drupal::currentUser()->setAccount($this->owner);

    $this->apiary = Apiary::create(['name' => 'Delete Dependency Apiary']);
    $this->apiary->save();
  }

  /**
   * The counter service.
   */
  protected function counter(): HivelogDeleteDependencyCounter {
    return \Drupal::service('hivelog.delete_dependency_counter');
  }

  // -------------------------------------------------------------------------
  // Registry
  // -------------------------------------------------------------------------

  /**
   * The registry's rows() merges core rows with the test module's hook row.
   */
  public function testRegistryMergesSubmoduleRows(): void {
    $rows = HivelogDeleteDependencyRegistry::rows();
    $test_rows = array_filter($rows, fn(array $row) => $row['child'] === 'hivelog_delete_test_child');
    $this->assertCount(1, $test_rows);
    $row = reset($test_rows);
    $this->assertEquals('apiary', $row['parent']);
    $this->assertEquals('parent', $row['field']);
    $this->assertEquals(HivelogDeleteDependencyRegistry::BLOCK, $row['treatment']);
  }

  /**
   * RowsForParent() only returns rows for the given parent type.
   */
  public function testRowsForParentFiltersByParentType(): void {
    $apiary_rows = HivelogDeleteDependencyRegistry::rowsForParent('apiary');
    $this->assertNotEmpty(array_filter($apiary_rows, fn($r) => $r['child'] === 'hivelog_delete_test_child'));

    $hive_rows = HivelogDeleteDependencyRegistry::rowsForParent('hive');
    $this->assertEmpty(array_filter($hive_rows, fn($r) => $r['child'] === 'hivelog_delete_test_child'));
  }

  /**
   * ManageUrl(): `'parent-canonical'` resolves to the parent's own page.
   */
  public function testManageUrlResolvesParentCanonical(): void {
    $row = ['manage' => 'parent-canonical'];
    $url = HivelogDeleteDependencyRegistry::manageUrl($row, $this->apiary);
    $this->assertNotNull($url);
    $this->assertEquals('entity.apiary.canonical', $url->getRouteName());
    $this->assertEquals(['apiary' => $this->apiary->id()], $url->getRouteParameters());
  }

  /**
   * ManageUrl(): a bare route name resolves with no parameters.
   */
  public function testManageUrlResolvesRouteName(): void {
    $row = ['manage' => 'entity.inventory_purchase.collection'];
    $url = HivelogDeleteDependencyRegistry::manageUrl($row, $this->apiary);
    $this->assertEquals('entity.inventory_purchase.collection', $url->getRouteName());
  }

  /**
   * ManageUrl(): no `manage` value means no link.
   */
  public function testManageUrlNullWhenNoneDeclared(): void {
    $this->assertNull(HivelogDeleteDependencyRegistry::manageUrl(['manage' => NULL], $this->apiary));
  }

  // -------------------------------------------------------------------------
  // Counter
  // -------------------------------------------------------------------------

  /**
   * A parent with no children counts to an empty array.
   *
   * A Hive, not `$this->apiary` — `Apiary::postSave()` auto-seeds ~30
   * default calendar actions into every new apiary, so a fresh apiary
   * is never actually childless; a fresh Hive genuinely is.
   */
  public function testCounterOmitsZeroCountRows(): void {
    $hive = Hive::create(['name' => 'Empty Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    $this->assertSame([], $this->counter()->countsFor($hive));
  }

  /**
   * The one `hivelog_delete_test_child` row out of `$counts`.
   *
   * Not necessarily the only row: `$this->apiary` always has a
   * `cascade` row too, for its auto-seeded default calendar actions.
   */
  protected function testChildRow(array $counts): array {
    $matches = array_values(array_filter($counts, fn(array $row) => $row['child'] === 'hivelog_delete_test_child'));
    $this->assertCount(1, $matches);
    return $matches[0];
  }

  /**
   * Builds 3 children and a `limited` role with `delete own` access.
   *
   * 2 children are owned by a fresh, non-admin `limited` user, 1 by
   * someone else — deliberately NOT `$this->owner`, which setUp() gave
   * `administer hivelog` (bypassing the ownership check entirely).
   *
   * @return \Drupal\user\Entity\User
   *   The `limited`-role user who owns 2 of the 3 children.
   */
  protected function createSplitDeletableFixture(): User {
    $role = Role::create(['id' => 'limited', 'label' => 'Limited']);
    $role->grantPermission('delete own hivelog_delete_test_child');
    $role->grantPermission('view apiary');
    $role->save();

    $limited_owner = User::create(['name' => 'limited_owner', 'mail' => 'limited_owner@example.com']);
    $limited_owner->addRole('limited');
    $limited_owner->save();
    $other_user = User::create(['name' => 'other', 'mail' => 'other@example.com']);
    $other_user->save();

    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $limited_owner->id()])->save();
    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $limited_owner->id()])->save();
    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $other_user->id()])->save();

    return $limited_owner;
  }

  /**
   * As the owner (`delete own`, not `delete any`): 2 of 3 are theirs.
   */
  public function testCounterSplitsDeletableForOwner(): void {
    $owner = $this->createSplitDeletableFixture();
    \Drupal::currentUser()->setAccount($owner);

    $row = $this->testChildRow($this->counter()->countsFor($this->apiary));
    $this->assertEquals(3, $row['total']);
    $this->assertEquals(2, $row['deletable']);
    $this->assertEquals(1, $row['not_deletable']);
  }

  /**
   * As a `delete own`-only user who owns none of the 3: none deletable.
   */
  public function testCounterSplitsDeletableForNonOwner(): void {
    $this->createSplitDeletableFixture();
    $limited_user = User::create(['name' => 'limited', 'mail' => 'limited@example.com']);
    $limited_user->addRole('limited');
    $limited_user->save();
    \Drupal::currentUser()->setAccount($limited_user);

    $row = $this->testChildRow($this->counter()->countsFor($this->apiary));
    $this->assertEquals(3, $row['total']);
    $this->assertEquals(0, $row['deletable']);
    $this->assertEquals(3, $row['not_deletable']);
  }

  /**
   * CountsFor() memoizes per entity for the life of the request.
   */
  public function testCounterMemoizesPerRequest(): void {
    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $this->owner->id()])->save();
    $counter = $this->counter();

    $first = $this->testChildRow($counter->countsFor($this->apiary));
    $this->assertEquals(1, $first['total']);

    // A second child appears after the first count — the memoized
    // result should NOT reflect it.
    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $this->owner->id()])->save();
    $second = $this->testChildRow($counter->countsFor($this->apiary));
    $this->assertEquals(1, $second['total']);
  }

  // -------------------------------------------------------------------------
  // Access integration point
  // -------------------------------------------------------------------------

  /**
   * BlockingAccessResult() is NULL when no BLOCK row has children.
   */
  public function testBlockingAccessResultNullWhenNoBlockingChildren(): void {
    $this->assertNull($this->counter()->blockingAccessResult($this->apiary));
  }

  /**
   * BlockingAccessResult() forbids with a reason and cache tags.
   *
   * The reason names the count; the cache tags are the blocking child
   * type's own list cache tags.
   */
  public function testBlockingAccessResultForbidsWithReasonAndCacheTags(): void {
    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $this->owner->id()])->save();

    $result = $this->counter()->blockingAccessResult($this->apiary);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
    $this->assertStringContainsString('1 delete test child', $result->getReason());
    $this->assertContains('hivelog_delete_test_child_list', $result->getCacheTags());
  }

  // -------------------------------------------------------------------------
  // Delete-form rendering
  // -------------------------------------------------------------------------

  /**
   * A blocked entity's delete form shows BLOCK and no submit button.
   */
  public function testDeleteFormHidesSubmitAndShowsBlockSection(): void {
    DeleteTestChild::create(['parent' => $this->apiary->id(), 'uid' => $this->owner->id()])->save();

    $build = \Drupal::service('entity.form_builder')->getForm($this->apiary, 'delete');
    $this->assertArrayHasKey('block', $build['hivelog_delete_dependencies']);
    $this->assertArrayNotHasKey('submit', $build['actions']);
    // Cancel must still be there — blocked is not the same as stuck.
    $this->assertArrayHasKey('cancel', $build['actions']);
  }

  /**
   * A dependency-free entity gets neither a section nor a hidden button.
   */
  public function testDeleteFormKeepsSubmitWhenNotBlocked(): void {
    $hive = Hive::create(['name' => 'Empty Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();

    $build = \Drupal::service('entity.form_builder')->getForm($hive, 'delete');
    $this->assertArrayNotHasKey('hivelog_delete_dependencies', $build);
    $this->assertArrayHasKey('submit', $build['actions']);
  }

  /**
   * CASCADE section: "Will also be deleted", Delete stays.
   */
  public function testDeleteFormShowsCascadeSection(): void {
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Feed winter stores',
      'description' => 'Desc.',
      'week_start' => 10,
    ])->save();

    $build = \Drupal::service('entity.form_builder')->getForm($this->apiary, 'delete');
    $this->assertArrayHasKey('cascade', $build['hivelog_delete_dependencies']);
    $this->assertArrayHasKey('submit', $build['actions']);
  }

  /**
   * WARN section: a count and a link, Delete stays.
   */
  public function testDeleteFormShowsWarnSectionWithLink(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Warn Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 1,
      'unit_price' => 1,
    ])->save();

    $build = \Drupal::service('entity.form_builder')->getForm($item, 'delete');
    $this->assertArrayHasKey('warn', $build['hivelog_delete_dependencies']);
    $this->assertArrayHasKey('submit', $build['actions']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['hivelog_delete_dependencies']['warn']);
    $this->assertStringContainsString('<a', $html);
  }

  /**
   * DETACH section: "Will be kept but unlinked", Delete stays.
   */
  public function testDeleteFormShowsDetachSection(): void {
    $hive = Hive::create(['name' => 'Detach Test Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    Queen::create(['name' => 'Detach Test Queen', 'hive' => $hive->id(), 'queen_year' => 2025, 'status' => 'active'])->save();

    $build = \Drupal::service('entity.form_builder')->getForm($hive, 'delete');
    $this->assertArrayHasKey('detach', $build['hivelog_delete_dependencies']);
    $this->assertArrayHasKey('submit', $build['actions']);
  }

}
