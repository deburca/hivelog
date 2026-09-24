<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests HivelogListBuilder::load() filters rows by per-entity access (0124).
 *
 * Data-provider-driven across the 10 core entity types with a HivelogList
 * Builder-derived collection page. `SensorDeviceListBuilder` (nanoprobe)
 * already has its own coverage in `SensorDeviceManagementUiTest::
 * testCollectionListsAccessibleDevicesAndHidesInaccessible()`; ApiClient
 * and AiProviderConfig (collective/nexus) get the same-shaped test in
 * their own submodule `tests/src/Kernel/` per the task.
 *
 * Every list builder's render() does nothing but iterate load()'s result
 * (see ApiaryListBuilder::render() et al.), so a leak or an over-filter
 * would already show up in load() — these tests check that directly,
 * plus two representative render() checks (one SDC-table builder, one
 * that still uses core's #type => 'table') to confirm the row-level
 * filtering actually reaches the rendered output.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ListBuilderAccessFilterTest extends KernelTestBase {

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
  ];

  /**
   * The apiary owner.
   */
  protected User $owner;

  /**
   * A beekeeper member of the apiary (not the owner).
   */
  protected User $beekeeper;

  /**
   * A user with the same "view own …" permissions but no apiary relation.
   */
  protected User $outsider;

  /**
   * An `administer hivelog` user.
   */
  protected User $admin;

  /**
   * The test apiary, owned by $owner with $beekeeper as a member.
   */
  protected Apiary $apiary;

  /**
   * Fixture entities, keyed by entity type ID.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $fixtures = [];

  /**
   * Maps entity type ID to the access handler's permission phrase.
   *
   * E.g. `hive_inspection` → `hive inspection`, used to build
   * `view own hive inspection` / `view any hive inspection`.
   */
  protected const PERMISSION_PHRASE = [
    'apiary' => 'apiary',
    'hive' => 'hive',
    'hive_inspection' => 'hive inspection',
    'queen' => 'queen',
    'queen_observation' => 'queen observation',
    'hive_action_log' => 'hive action log',
    'apiary_action_log' => 'apiary action log',
    'inventory_item' => 'inventory item',
    'inventory_purchase' => 'inventory purchase',
    'product' => 'product',
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
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    // The first user created in a kernel test becomes uid 1, which bypasses
    // every permission check entirely — burn it on a throwaway account
    // before creating the users this test actually checks.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    foreach (self::PERMISSION_PHRASE as $phrase) {
      $role->grantPermission('view own ' . $phrase);
    }
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->beekeeper = User::create(['name' => 'beekeeper', 'mail' => 'beekeeper@example.com']);
    $this->beekeeper->addRole('beekeeper');
    $this->beekeeper->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $this->admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $this->admin->addRole('hivelog_admin');
    $this->admin->save();

    $this->apiary = Apiary::create([
      'name' => 'Owner Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$this->beekeeper->id()],
    ]);
    $this->apiary->save();
    $this->fixtures['apiary'] = $this->apiary;

    $this->fixtures['hive'] = Hive::create([
      'name' => 'Owner Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['hive']->save();

    $this->fixtures['hive_inspection'] = HiveInspection::create([
      'hive' => $this->fixtures['hive']->id(),
      'inspection_date' => '2026-06-15',
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['hive_inspection']->save();

    $queen = Queen::create([
      'name' => 'Q-owner',
      'hive' => $this->fixtures['hive']->id(),
      'queen_year' => 2025,
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $queen->save();
    $this->fixtures['queen'] = $queen;

    $this->fixtures['queen_observation'] = QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2026-06-20',
      'health' => 'good',
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['queen_observation']->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Owner Calendar Action',
      'description' => 'Desc.',
      'week_start' => 15,
      'uid' => $this->owner->id(),
    ]);
    $calendar_action->save();

    $this->fixtures['hive_action_log'] = HiveActionLog::create([
      'hive' => $this->fixtures['hive']->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['hive_action_log']->save();

    $this->fixtures['apiary_action_log'] = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $calendar_action->id(),
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['apiary_action_log']->save();

    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Owner Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();
    $this->fixtures['inventory_item'] = $item;

    $this->fixtures['inventory_purchase'] = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 10,
      'unit_price' => 2,
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['inventory_purchase']->save();

    $this->fixtures['product'] = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Owner Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'uid' => $this->owner->id(),
    ]);
    $this->fixtures['product']->save();
  }

  /**
   * Entity type IDs with a HivelogListBuilder-derived collection page.
   *
   * @return array<string, array{0: string}>
   *   Data provider rows, one entity type ID per test iteration.
   */
  public static function entityTypeProvider(): array {
    return [
      'apiary' => ['apiary'],
      'hive' => ['hive'],
      'hive_inspection' => ['hive_inspection'],
      'queen' => ['queen'],
      'queen_observation' => ['queen_observation'],
      'hive_action_log' => ['hive_action_log'],
      'apiary_action_log' => ['apiary_action_log'],
      'inventory_item' => ['inventory_item'],
      'inventory_purchase' => ['inventory_purchase'],
      'product' => ['product'],
    ];
  }

  /**
   * Returns the loaded entity IDs from the type's list builder.
   */
  protected function loadedIds(string $entity_type_id): array {
    $entities = \Drupal::entityTypeManager()->getListBuilder($entity_type_id)->load();
    return array_map(static fn(EntityInterface $e) => $e->id(), $entities);
  }

  /**
   * Tests a non-member with only "view own …" does not see the row.
   */
  #[DataProvider('entityTypeProvider')]
  public function testOutsiderDoesNotSeeRowInLoad(string $entity_type_id): void {
    $this->setCurrentUser($this->outsider);
    $this->assertNotContains(
      $this->fixtures[$entity_type_id]->id(),
      $this->loadedIds($entity_type_id),
      "Outsider must not see another user's $entity_type_id row in load()."
    );
  }

  /**
   * Tests the owner sees their own row.
   */
  #[DataProvider('entityTypeProvider')]
  public function testOwnerSeesOwnRowInLoad(string $entity_type_id): void {
    $this->setCurrentUser($this->owner);
    $this->assertContains(
      $this->fixtures[$entity_type_id]->id(),
      $this->loadedIds($entity_type_id),
      "Owner must see their own $entity_type_id row in load()."
    );
  }

  /**
   * Tests a beekeeper member (not the owner) sees the row.
   *
   * Access to every one of these types is resolved via apiary membership,
   * not a flat owner check — see HiveAccessControlHandler et al.
   */
  #[DataProvider('entityTypeProvider')]
  public function testBeekeeperMemberSeesRowInLoad(string $entity_type_id): void {
    $this->setCurrentUser($this->beekeeper);
    $this->assertContains(
      $this->fixtures[$entity_type_id]->id(),
      $this->loadedIds($entity_type_id),
      "Beekeeper member must see the apiary's $entity_type_id row in load()."
    );
  }

  /**
   * Tests a user with the site-wide "view any …" permission sees the row.
   */
  #[DataProvider('entityTypeProvider')]
  public function testAnyPermissionUserSeesRowInLoad(string $entity_type_id): void {
    $phrase = self::PERMISSION_PHRASE[$entity_type_id];
    $role = Role::create(['id' => $entity_type_id . '_any_viewer', 'label' => 'Any viewer']);
    $role->grantPermission('view any ' . $phrase);
    $role->save();
    $viewer = User::create(['name' => $entity_type_id . '-any-viewer', 'mail' => $entity_type_id . '-any@example.com']);
    $viewer->addRole($entity_type_id . '_any_viewer');
    $viewer->save();

    $this->setCurrentUser($viewer);
    $this->assertContains(
      $this->fixtures[$entity_type_id]->id(),
      $this->loadedIds($entity_type_id),
      "A 'view any $phrase' user must see the $entity_type_id row in load()."
    );
  }

  /**
   * Tests `administer hivelog` sees the row.
   */
  #[DataProvider('entityTypeProvider')]
  public function testAdminSeesRowInLoad(string $entity_type_id): void {
    $this->setCurrentUser($this->admin);
    $this->assertContains(
      $this->fixtures[$entity_type_id]->id(),
      $this->loadedIds($entity_type_id),
      "administer hivelog must see the $entity_type_id row in load()."
    );
  }

  /**
   * Tests ApiaryListBuilder::render() (SDC-table family) hides other rows.
   */
  public function testApiaryRenderHidesOtherUsersRow(): void {
    $this->setCurrentUser($this->outsider);
    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();
    $rows = $build['table']['#props']['rows'] ?? [];
    $this->assertCount(0, $rows);

    $this->setCurrentUser($this->owner);
    $build = \Drupal::entityTypeManager()->getListBuilder('apiary')->render();
    $rows = $build['table']['#props']['rows'] ?? [];
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('Owner Apiary', (string) $rows[0]['cells'][1]);
  }

  /**
   * Tests HiveListBuilder::render() (core's #type => 'table') hides rows.
   *
   * HiveListBuilder does not override render(), so this exercises core
   * EntityListBuilder::render()'s `#rows`, keyed by entity id.
   */
  public function testHiveRenderHidesOtherUsersRow(): void {
    $hive_id = $this->fixtures['hive']->id();

    $this->setCurrentUser($this->outsider);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive')->render();
    $this->assertArrayNotHasKey($hive_id, $build['table']['#rows']);

    $this->setCurrentUser($this->owner);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive')->render();
    $this->assertArrayHasKey($hive_id, $build['table']['#rows']);
  }

}
