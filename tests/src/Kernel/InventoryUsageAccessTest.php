<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryUsage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for InventoryUsage.
 *
 * Mirrors InventoryAccessTest's style. Delete is owner-or-creator,
 * mirroring HiveActionLog — a usage record is a per-transaction log
 * line, not foundational apiary structure.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InventoryUsageAccessTest extends KernelTestBase {

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
   * The apiary owner user.
   */
  protected User $owner;

  /**
   * A beekeeper member of the apiary.
   */
  protected User $beekeeper;

  /**
   * A user with no access to the apiary.
   */
  protected User $outsider;

  /**
   * The test apiary (private).
   */
  protected Apiary $apiary;

  /**
   * A test usage record, created by the beekeeper.
   */
  protected InventoryUsage $usage;

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
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_usage');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own inventory usage');
    $role->grantPermission('edit own inventory usage');
    $role->grantPermission('delete own inventory usage');
    $role->grantPermission('add inventory usage');
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

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$this->beekeeper->id()],
    ]);
    $this->apiary->save();

    $hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $hive->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Test Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'uid' => $this->owner->id(),
    ]);
    $calendar_action->save();

    $hive_action_log = HiveActionLog::create([
      'hive' => $hive->id(),
      'calendar_action' => $calendar_action->id(),
      'status' => 'done',
      'uid' => $this->owner->id(),
    ]);
    $hive_action_log->save();

    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();

    $this->usage = InventoryUsage::create([
      'item' => $item->id(),
      'quantity' => 2,
      'hive_action_log' => $hive_action_log->id(),
      'uid' => $this->beekeeper->id(),
    ]);
    $this->usage->save();
  }

  /**
   * Tests that the owner can view a usage record.
   */
  public function testOwnerCanViewUsage(): void {
    $this->assertTrue($this->usage->access('view', $this->owner));
  }

  /**
   * Tests that a beekeeper can view a usage record.
   */
  public function testBeekeeperCanViewUsage(): void {
    $this->assertTrue($this->usage->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit a usage record.
   */
  public function testBeekeeperCanEditUsage(): void {
    $this->assertTrue($this->usage->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can delete their own usage record.
   */
  public function testBeekeeperCanDeleteOwnUsage(): void {
    // $this->usage was created with uid = beekeeper.
    $this->assertTrue($this->usage->access('delete', $this->beekeeper));
  }

  /**
   * Tests that the apiary owner can delete any usage record.
   */
  public function testOwnerCanDeleteAnyUsage(): void {
    $this->assertTrue($this->usage->access('delete', $this->owner));
  }

  /**
   * Tests that an outsider cannot view a private apiary's usage record.
   */
  public function testOutsiderCannotViewPrivateUsage(): void {
    $this->assertFalse($this->usage->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's usage record.
   */
  public function testOutsiderCanViewPublicUsage(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->usage->access('view', $this->outsider));
  }

}
