<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped access control for all hivelog entity types.
 *
 * Verifies that access to apiaries, hives, inspections, queens and
 * queen observations is gated by apiary membership (owner + beekeepers)
 * and the apiary's visibility setting (public / private).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryScopedAccessTest extends KernelTestBase {

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
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $owner;

  /**
   * A beekeeper member of the apiary.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $beekeeper;

  /**
   * A user with no access to the apiary.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $outsider;

  /**
   * The test apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * A hive inside the test apiary.
   *
   * @var \Drupal\hivelog\Entity\Hive
   */
  protected Hive $hive;

  /**
   * An inspection on the test hive.
   *
   * @var \Drupal\hivelog\Entity\HiveInspection
   */
  protected HiveInspection $inspection;

  /**
   * A queen on the test hive.
   *
   * @var \Drupal\hivelog\Entity\Queen
   */
  protected Queen $queen;

  /**
   * An observation on the test queen.
   *
   * @var \Drupal\hivelog\Entity\QueenObservation
   */
  protected QueenObservation $observation;

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
    $this->installSchema('file', ['file_usage']);

    // Create a role with all "own" permissions (apiary-member scoped).
    $role = Role::create([
      'id' => 'beekeeper',
      'label' => 'Beekeeper',
    ]);
    $role->grantPermission('view own apiary');
    $role->grantPermission('edit own apiary');
    $role->grantPermission('delete own apiary');
    $role->grantPermission('view own hive');
    $role->grantPermission('edit own hive');
    $role->grantPermission('delete own hive');
    $role->grantPermission('view own hive inspection');
    $role->grantPermission('edit own hive inspection');
    $role->grantPermission('delete own hive inspection');
    $role->grantPermission('view own queen');
    $role->grantPermission('edit own queen');
    $role->grantPermission('delete own queen');
    $role->grantPermission('view own queen observation');
    $role->grantPermission('edit own queen observation');
    $role->grantPermission('delete own queen observation');
    $role->grantPermission('add hive');
    $role->grantPermission('add hive inspection');
    $role->grantPermission('add queen');
    $role->grantPermission('add queen observation');
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

    // Create a private apiary owned by $owner with $beekeeper as a member.
    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
      'beekeepers' => [$this->beekeeper->id()],
    ]);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->hive->save();

    $this->inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2025-06-15',
      'uid' => $this->beekeeper->id(),
    ]);
    $this->inspection->save();

    $this->queen = Queen::create([
      'name' => 'Q-test',
      'hive' => $this->hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->queen->save();

    $this->observation = QueenObservation::create([
      'queen' => $this->queen->id(),
      'observation_date' => '2025-06-20',
      'health' => 'good',
      'uid' => $this->beekeeper->id(),
    ]);
    $this->observation->save();
  }

  // -----------------------------------------------------------------------
  // Apiary::isApiaryMember()
  // -----------------------------------------------------------------------

  /** Tests that the owner is an apiary member. */
  public function testIsApiaryMemberOwner(): void {
    $this->assertTrue($this->apiary->isApiaryMember($this->owner));
  }

  /** Tests that a beekeeper is an apiary member. */
  public function testIsApiaryMemberBeekeeper(): void {
    $this->assertTrue($this->apiary->isApiaryMember($this->beekeeper));
  }

  /** Tests that an outsider is not an apiary member. */
  public function testIsApiaryMemberOutsider(): void {
    $this->assertFalse($this->apiary->isApiaryMember($this->outsider));
  }

  /** Tests that the apiary is not public by default. */
  public function testIsPublicDefault(): void {
    $this->assertFalse($this->apiary->isPublic());
  }

  // -----------------------------------------------------------------------
  // Private apiary: owner access
  // -----------------------------------------------------------------------

  /** Tests that the owner can view a private apiary. */
  public function testOwnerCanViewPrivateApiary(): void {
    $this->assertTrue($this->apiary->access('view', $this->owner));
  }

  /** Tests that the owner can edit a private apiary. */
  public function testOwnerCanEditPrivateApiary(): void {
    $this->assertTrue($this->apiary->access('update', $this->owner));
  }

  /** Tests that the owner can delete a private apiary. */
  public function testOwnerCanDeletePrivateApiary(): void {
    $this->assertTrue($this->apiary->access('delete', $this->owner));
  }

  /** Tests that the owner can view a hive. */
  public function testOwnerCanViewHive(): void {
    $this->assertTrue($this->hive->access('view', $this->owner));
  }

  /** Tests that the owner can edit a hive. */
  public function testOwnerCanEditHive(): void {
    $this->assertTrue($this->hive->access('update', $this->owner));
  }

  /** Tests that the owner can delete a hive. */
  public function testOwnerCanDeleteHive(): void {
    $this->assertTrue($this->hive->access('delete', $this->owner));
  }

  /** Tests that the owner can view an inspection. */
  public function testOwnerCanViewInspection(): void {
    $this->assertTrue($this->inspection->access('view', $this->owner));
  }

  /** Tests that the owner can edit an inspection. */
  public function testOwnerCanEditInspection(): void {
    $this->assertTrue($this->inspection->access('update', $this->owner));
  }

  /** Tests that the owner can delete an inspection. */
  public function testOwnerCanDeleteInspection(): void {
    $this->assertTrue($this->inspection->access('delete', $this->owner));
  }

  // -----------------------------------------------------------------------
  // Private apiary: beekeeper access
  // -----------------------------------------------------------------------

  /** Tests that a beekeeper can view a private apiary. */
  public function testBeekeeperCanViewPrivateApiary(): void {
    $this->assertTrue($this->apiary->access('view', $this->beekeeper));
  }

  /** Tests that a beekeeper cannot edit an apiary. */
  public function testBeekeeperCannotEditApiary(): void {
    $this->assertFalse($this->apiary->access('update', $this->beekeeper));
  }

  /** Tests that a beekeeper cannot delete an apiary. */
  public function testBeekeeperCannotDeleteApiary(): void {
    $this->assertFalse($this->apiary->access('delete', $this->beekeeper));
  }

  /** Tests that a beekeeper can view a hive. */
  public function testBeekeeperCanViewHive(): void {
    $this->assertTrue($this->hive->access('view', $this->beekeeper));
  }

  /** Tests that a beekeeper can edit a hive. */
  public function testBeekeeperCanEditHive(): void {
    $this->assertTrue($this->hive->access('update', $this->beekeeper));
  }

  /** Tests that a beekeeper cannot delete a hive. */
  public function testBeekeeperCannotDeleteHive(): void {
    // Only apiary owner can delete hives.
    $this->assertFalse($this->hive->access('delete', $this->beekeeper));
  }

  /** Tests that a beekeeper can view an inspection. */
  public function testBeekeeperCanViewInspection(): void {
    $this->assertTrue($this->inspection->access('view', $this->beekeeper));
  }

  /** Tests that a beekeeper can edit an inspection. */
  public function testBeekeeperCanEditInspection(): void {
    $this->assertTrue($this->inspection->access('update', $this->beekeeper));
  }

  /** Tests that a beekeeper can delete their own inspection. */
  public function testBeekeeperCanDeleteOwnInspection(): void {
    // Beekeeper created this inspection, so they can delete it.
    $this->assertTrue($this->inspection->access('delete', $this->beekeeper));
  }

  /** Tests that a beekeeper cannot delete another user's inspection. */
  public function testBeekeeperCannotDeleteOthersInspection(): void {
    // Create an inspection owned by the apiary owner.
    $owner_inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2025-07-01',
      'uid' => $this->owner->id(),
    ]);
    $owner_inspection->save();
    // Beekeeper is a member but did not create this one.
    $this->assertFalse($owner_inspection->access('delete', $this->beekeeper));
  }

  // -----------------------------------------------------------------------
  // Private apiary: outsider access (denied)
  // -----------------------------------------------------------------------

  /** Tests that an outsider cannot view a private apiary. */
  public function testOutsiderCannotViewPrivateApiary(): void {
    $this->assertFalse($this->apiary->access('view', $this->outsider));
  }

  /** Tests that an outsider cannot view a private hive. */
  public function testOutsiderCannotViewPrivateHive(): void {
    $this->assertFalse($this->hive->access('view', $this->outsider));
  }

  /** Tests that an outsider cannot edit a private hive. */
  public function testOutsiderCannotEditPrivateHive(): void {
    $this->assertFalse($this->hive->access('update', $this->outsider));
  }

  /** Tests that an outsider cannot view a private inspection. */
  public function testOutsiderCannotViewPrivateInspection(): void {
    $this->assertFalse($this->inspection->access('view', $this->outsider));
  }

  // -----------------------------------------------------------------------
  // Public apiary: outsider can view but not edit
  // -----------------------------------------------------------------------

  /** Tests that an outsider can view a public apiary. */
  public function testOutsiderCanViewPublicApiary(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->apiary->access('view', $this->outsider));
  }

  /** Tests that an outsider can view a public hive. */
  public function testOutsiderCanViewPublicHive(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->hive->access('view', $this->outsider));
  }

  /** Tests that an outsider cannot edit a public hive. */
  public function testOutsiderCannotEditPublicHive(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertFalse($this->hive->access('update', $this->outsider));
  }

  /** Tests that an outsider can view a public inspection. */
  public function testOutsiderCanViewPublicInspection(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertTrue($this->inspection->access('view', $this->outsider));
  }

  /** Tests that an outsider cannot edit a public inspection. */
  public function testOutsiderCannotEditPublicInspection(): void {
    $this->apiary->set('visibility', 'public');
    $this->apiary->save();
    $this->assertFalse($this->inspection->access('update', $this->outsider));
  }

  // -----------------------------------------------------------------------
  // Queen and observation access follows apiary scope
  // -----------------------------------------------------------------------

  /** Tests that a beekeeper can view a queen. */
  public function testBeekeeperCanViewQueen(): void {
    $this->assertTrue($this->queen->access('view', $this->beekeeper));
  }

  /** Tests that a beekeeper can edit a queen. */
  public function testBeekeeperCanEditQueen(): void {
    $this->assertTrue($this->queen->access('update', $this->beekeeper));
  }

  /** Tests that an outsider cannot view a private queen. */
  public function testOutsiderCannotViewPrivateQueen(): void {
    $this->assertFalse($this->queen->access('view', $this->outsider));
  }

  /** Tests that a beekeeper can view an observation. */
  public function testBeekeeperCanViewObservation(): void {
    $this->assertTrue($this->observation->access('view', $this->beekeeper));
  }

  /** Tests that a beekeeper can edit an observation. */
  public function testBeekeeperCanEditObservation(): void {
    $this->assertTrue($this->observation->access('update', $this->beekeeper));
  }

  /** Tests that a beekeeper can delete their own observation. */
  public function testBeekeeperCanDeleteOwnObservation(): void {
    $this->assertTrue($this->observation->access('delete', $this->beekeeper));
  }

  /** Tests that an outsider cannot view a private observation. */
  public function testOutsiderCannotViewPrivateObservation(): void {
    $this->assertFalse($this->observation->access('view', $this->outsider));
  }

  // -----------------------------------------------------------------------
  // Site-wide "any" permissions bypass apiary membership
  // -----------------------------------------------------------------------

  /** Tests that a site-wide any permission bypasses apiary membership. */
  public function testAnyPermissionBypassesMembership(): void {
    $any_role = Role::create(['id' => 'site_viewer', 'label' => 'Site viewer']);
    $any_role->grantPermission('view any hive');
    $any_role->save();

    $viewer = User::create(['name' => 'site-viewer', 'mail' => 'viewer@example.com']);
    $viewer->addRole('site_viewer');
    $viewer->save();

    $this->assertTrue($this->hive->access('view', $viewer));
  }

  // -----------------------------------------------------------------------
  // Cross-user scenario: userB editing userA's entities.
  //
  // UserA (owner) creates an apiary, hive, and inspection. UserB has the
  // same role/permissions but is NOT an apiary member. Verify userB
  // cannot view or edit anything. Then add userB as a beekeeper and
  // verify they gain view + edit access to child entities but NOT to the
  // apiary itself.
  // -----------------------------------------------------------------------

  /** Tests a cross-user edit scenario where membership changes grant access. */
  public function testCrossUserEditScenario(): void {
    // Setup: userA creates everything, userB is a stranger.
    $userA = User::create(['name' => 'userA', 'mail' => 'a@example.com']);
    $userA->addRole('beekeeper');
    $userA->save();

    $userB = User::create(['name' => 'userB', 'mail' => 'b@example.com']);
    $userB->addRole('beekeeper');
    $userB->save();

    $apiaryA = Apiary::create([
      'name' => 'UserA Apiary',
      'uid' => $userA->id(),
      'visibility' => 'private',
    ]);
    $apiaryA->save();

    $hiveA = Hive::create([
      'name' => 'UserA Hive',
      'apiary' => $apiaryA->id(),
      'status' => 'active',
      'uid' => $userA->id(),
    ]);
    $hiveA->save();

    $inspectionA = HiveInspection::create([
      'hive' => $hiveA->id(),
      'inspection_date' => '2025-08-01',
      'uid' => $userA->id(),
    ]);
    $inspectionA->save();

    $queenA = Queen::create([
      'name' => 'Q-userA',
      'hive' => $hiveA->id(),
      'queen_year' => 2025,
      'status' => 'active',
      'uid' => $userA->id(),
    ]);
    $queenA->save();

    $observationA = QueenObservation::create([
      'queen' => $queenA->id(),
      'observation_date' => '2025-08-05',
      'health' => 'good',
      'uid' => $userA->id(),
    ]);
    $observationA->save();

    // --- Phase 1: userB is NOT a member → all access denied. ---
    $this->assertFalse($apiaryA->access('view', $userB), 'Non-member userB cannot view private apiary.');
    $this->assertFalse($apiaryA->access('update', $userB), 'Non-member userB cannot edit apiary.');
    $this->assertFalse($hiveA->access('view', $userB), 'Non-member userB cannot view hive.');
    $this->assertFalse($hiveA->access('update', $userB), 'Non-member userB cannot edit hive.');
    $this->assertFalse($inspectionA->access('view', $userB), 'Non-member userB cannot view inspection.');
    $this->assertFalse($inspectionA->access('update', $userB), 'Non-member userB cannot edit inspection.');
    $this->assertFalse($queenA->access('view', $userB), 'Non-member userB cannot view queen.');
    $this->assertFalse($queenA->access('update', $userB), 'Non-member userB cannot edit queen.');
    $this->assertFalse($observationA->access('view', $userB), 'Non-member userB cannot view observation.');
    $this->assertFalse($observationA->access('update', $userB), 'Non-member userB cannot edit observation.');

    // --- Phase 2: add userB as a beekeeper on the apiary. ---
    $apiaryA->set('beekeepers', [$userB->id()]);
    $apiaryA->save();

    // Reset entity storage and access static caches so Phase 1 results
    // are not reused and entity references resolve the updated apiary.
    $etm = \Drupal::entityTypeManager();
    foreach (['apiary', 'hive', 'hive_inspection', 'queen', 'queen_observation'] as $type) {
      $etm->getStorage($type)->resetCache();
      $etm->getAccessControlHandler($type)->resetCache();
    }

    // Reload entities to ensure fresh access checks.
    $apiaryA = Apiary::load($apiaryA->id());
    $hiveA = Hive::load($hiveA->id());
    $inspectionA = HiveInspection::load($inspectionA->id());
    $queenA = Queen::load($queenA->id());
    $observationA = QueenObservation::load($observationA->id());

    // userB can now VIEW all entities in the apiary.
    $this->assertTrue($apiaryA->access('view', $userB), 'Beekeeper userB can view apiary.');
    $this->assertTrue($hiveA->access('view', $userB), 'Beekeeper userB can view hive.');
    $this->assertTrue($inspectionA->access('view', $userB), 'Beekeeper userB can view inspection.');
    $this->assertTrue($queenA->access('view', $userB), 'Beekeeper userB can view queen.');
    $this->assertTrue($observationA->access('view', $userB), 'Beekeeper userB can view observation.');

    // userB can EDIT hives, inspections, queens, and observations.
    $this->assertTrue($hiveA->access('update', $userB), 'Beekeeper userB can edit hive created by userA.');
    $this->assertTrue($inspectionA->access('update', $userB), 'Beekeeper userB can edit inspection created by userA.');
    $this->assertTrue($queenA->access('update', $userB), 'Beekeeper userB can edit queen created by userA.');
    $this->assertTrue($observationA->access('update', $userB), 'Beekeeper userB can edit observation created by userA.');

    // userB CANNOT edit or delete the apiary itself (owner-only).
    $this->assertFalse($apiaryA->access('update', $userB), 'Beekeeper userB cannot edit apiary (owner-only).');
    $this->assertFalse($apiaryA->access('delete', $userB), 'Beekeeper userB cannot delete apiary (owner-only).');

    // userB CANNOT delete hives or queens (owner-only).
    $this->assertFalse($hiveA->access('delete', $userB), 'Beekeeper userB cannot delete hive (owner-only).');
    $this->assertFalse($queenA->access('delete', $userB), 'Beekeeper userB cannot delete queen (owner-only).');

    // userB CANNOT delete inspections/observations created by userA.
    $this->assertFalse($inspectionA->access('delete', $userB), 'Beekeeper userB cannot delete inspection created by userA.');
    $this->assertFalse($observationA->access('delete', $userB), 'Beekeeper userB cannot delete observation created by userA.');

    // --- Phase 3: userB creates their own inspection → can delete it. ---
    $inspectionB = HiveInspection::create([
      'hive' => $hiveA->id(),
      'inspection_date' => '2025-08-10',
      'uid' => $userB->id(),
    ]);
    $inspectionB->save();
    $this->assertTrue($inspectionB->access('delete', $userB), 'Beekeeper userB can delete their own inspection.');

    // userA (owner) can also delete userB's inspection.
    $this->assertTrue($inspectionB->access('delete', $userA), 'Apiary owner userA can delete inspection created by userB.');
  }

}
