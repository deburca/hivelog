<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for CalendarActionItemRequirement.
 *
 * Mirrors InventoryAccessTest's style. Delete is owner-only, mirroring
 * CalendarAction — a requirement is part of the calendar action's plan,
 * not a per-transaction log.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionItemRequirementAccessTest extends KernelTestBase {

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
   * A test requirement on the test apiary's calendar action.
   */
  protected CalendarActionItemRequirement $requirement;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own calendar action item requirement');
    $role->grantPermission('edit own calendar action item requirement');
    $role->grantPermission('delete own calendar action item requirement');
    $role->grantPermission('add calendar action item requirement');
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

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Test Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'uid' => $this->owner->id(),
    ]);
    $calendar_action->save();

    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Test Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'uid' => $this->owner->id(),
    ]);
    $item->save();

    $this->requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $calendar_action->id(),
      'item' => $item->id(),
      'quantity' => 2,
      'uid' => $this->beekeeper->id(),
    ]);
    $this->requirement->save();
  }

  /**
   * Tests that the owner can view a requirement.
   */
  public function testOwnerCanViewRequirement(): void {
    $this->assertTrue($this->requirement->access('view', $this->owner));
  }

  /**
   * Tests that the owner can edit a requirement.
   */
  public function testOwnerCanEditRequirement(): void {
    $this->assertTrue($this->requirement->access('update', $this->owner));
  }

  /**
   * Tests that the owner can delete a requirement.
   */
  public function testOwnerCanDeleteRequirement(): void {
    $this->assertTrue($this->requirement->access('delete', $this->owner));
  }

  /**
   * Tests that a beekeeper can view a requirement.
   */
  public function testBeekeeperCanViewRequirement(): void {
    $this->assertTrue($this->requirement->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit a requirement.
   */
  public function testBeekeeperCanEditRequirement(): void {
    $this->assertTrue($this->requirement->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper cannot delete their own requirement (owner-only).
   */
  public function testBeekeeperCannotDeleteOwnRequirement(): void {
    // $this->requirement was created with uid = beekeeper.
    $this->assertFalse($this->requirement->access('delete', $this->beekeeper));
  }

  /**
   * Tests that an outsider cannot view a private apiary's requirement.
   */
  public function testOutsiderCannotViewPrivateRequirement(): void {
    $this->assertFalse($this->requirement->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's requirement.
   */
  public function testOutsiderCanViewPublicRequirement(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->requirement->access('view', $this->outsider));
  }

}
