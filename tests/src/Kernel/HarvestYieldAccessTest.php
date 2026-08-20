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
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for HarvestYield.
 *
 * Mirrors InventoryUsageAccessTest's style. Delete is owner-or-creator,
 * mirroring InventoryUsage/HiveActionLog — a yield record is a
 * per-transaction log line, not foundational apiary structure.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HarvestYieldAccessTest extends KernelTestBase {

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
   * A test yield record, created by the beekeeper.
   */
  protected HarvestYield $yield;

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

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own harvest yield');
    $role->grantPermission('edit own harvest yield');
    $role->grantPermission('delete own harvest yield');
    $role->grantPermission('add harvest yield');
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

    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Test Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'uid' => $this->owner->id(),
    ]);
    $product->save();

    $this->yield = HarvestYield::create([
      'product' => $product->id(),
      'quantity' => 20,
      'hive_action_log' => $hive_action_log->id(),
      'uid' => $this->beekeeper->id(),
    ]);
    $this->yield->save();
  }

  /**
   * Tests that the owner can view a yield record.
   */
  public function testOwnerCanViewYield(): void {
    $this->assertTrue($this->yield->access('view', $this->owner));
  }

  /**
   * Tests that a beekeeper can view a yield record.
   */
  public function testBeekeeperCanViewYield(): void {
    $this->assertTrue($this->yield->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit a yield record.
   */
  public function testBeekeeperCanEditYield(): void {
    $this->assertTrue($this->yield->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can delete their own yield record.
   */
  public function testBeekeeperCanDeleteOwnYield(): void {
    // $this->yield was created with uid = beekeeper.
    $this->assertTrue($this->yield->access('delete', $this->beekeeper));
  }

  /**
   * Tests that the apiary owner can delete any yield record.
   */
  public function testOwnerCanDeleteAnyYield(): void {
    $this->assertTrue($this->yield->access('delete', $this->owner));
  }

  /**
   * Tests that an outsider cannot view a private apiary's yield record.
   */
  public function testOutsiderCannotViewPrivateYield(): void {
    $this->assertFalse($this->yield->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's yield record.
   */
  public function testOutsiderCanViewPublicYield(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->yield->access('view', $this->outsider));
  }

}
