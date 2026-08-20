<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for CalendarActionProductYield.
 *
 * Mirrors CalendarActionItemRequirementAccessTest's style. Delete is
 * owner-only, mirroring CalendarActionItemRequirement — a yield recipe
 * line is part of the calendar action's plan, not a per-transaction log.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionProductYieldAccessTest extends KernelTestBase {

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
   * A test yield on the test apiary's calendar action.
   */
  protected CalendarActionProductYield $yield;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own calendar action product yield');
    $role->grantPermission('edit own calendar action product yield');
    $role->grantPermission('delete own calendar action product yield');
    $role->grantPermission('add calendar action product yield');
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

    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Test Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'uid' => $this->owner->id(),
    ]);
    $product->save();

    $this->yield = CalendarActionProductYield::create([
      'calendar_action' => $calendar_action->id(),
      'product' => $product->id(),
      'quantity' => 5,
      'uid' => $this->beekeeper->id(),
    ]);
    $this->yield->save();
  }

  /**
   * Tests that the owner can view a yield.
   */
  public function testOwnerCanViewYield(): void {
    $this->assertTrue($this->yield->access('view', $this->owner));
  }

  /**
   * Tests that the owner can edit a yield.
   */
  public function testOwnerCanEditYield(): void {
    $this->assertTrue($this->yield->access('update', $this->owner));
  }

  /**
   * Tests that the owner can delete a yield.
   */
  public function testOwnerCanDeleteYield(): void {
    $this->assertTrue($this->yield->access('delete', $this->owner));
  }

  /**
   * Tests that a beekeeper can view a yield.
   */
  public function testBeekeeperCanViewYield(): void {
    $this->assertTrue($this->yield->access('view', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper can edit a yield.
   */
  public function testBeekeeperCanEditYield(): void {
    $this->assertTrue($this->yield->access('update', $this->beekeeper));
  }

  /**
   * Tests that a beekeeper cannot delete their own yield (owner-only).
   */
  public function testBeekeeperCannotDeleteOwnYield(): void {
    // $this->yield was created with uid = beekeeper.
    $this->assertFalse($this->yield->access('delete', $this->beekeeper));
  }

  /**
   * Tests that an outsider cannot view a private apiary's yield.
   */
  public function testOutsiderCannotViewPrivateYield(): void {
    $this->assertFalse($this->yield->access('view', $this->outsider));
  }

  /**
   * Tests that an outsider can view a public apiary's yield.
   */
  public function testOutsiderCanViewPublicYield(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->yield->access('view', $this->outsider));
  }

}
