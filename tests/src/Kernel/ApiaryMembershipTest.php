<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryMembership;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ApiaryMembership entity and Apiary's member lookups.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryMembershipTest extends KernelTestBase {

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
   * The apiary under test, owned by $this->owner.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * The apiary's owner.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('apiary_membership');

    // Skip uid 1 so the owner and members are ordinary accounts.
    $this->createUser();
    $this->owner = $this->createUser(['edit own apiary']);
    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
    ]);
    $this->apiary->save();
  }

  /**
   * Creates a membership through the entity API.
   */
  protected function addMember(int|string $account_id, string $role): ApiaryMembership {
    $membership = ApiaryMembership::create([
      'apiary' => $this->apiary->id(),
      'account' => $account_id,
      'role' => $role,
    ]);
    $membership->save();
    return $membership;
  }

  /**
   * Tests that Apiary no longer carries a stored memberships field.
   */
  public function testNoStoredMembershipsField(): void {
    $definitions = $this->container->get('entity_field.manager')
      ->getBaseFieldDefinitions('apiary');
    $this->assertArrayNotHasKey('apiary_memberships', $definitions);
  }

  /**
   * Tests that a new membership records the current user as its creator.
   */
  public function testCreatorIsSet(): void {
    $this->setCurrentUser($this->owner);
    $membership = $this->addMember($this->createUser()->id(), 'novice');
    $this->assertEquals($this->owner->id(), $membership->get('uid')->target_id);
  }

  /**
   * Tests that duplicate apiary-user memberships are prevented.
   */
  public function testDuplicateGuard(): void {
    $user = $this->createUser();
    $this->addMember($user->id(), 'viewer');

    // The storage wraps the entity's backstop exception.
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('already exists');
    $this->addMember($user->id(), 'keeper');
  }

  /**
   * Tests that getApiaryMembers() returns the apiary's memberships only.
   */
  public function testGetApiaryMembers(): void {
    $this->addMember($this->createUser()->id(), 'keeper');
    $this->addMember($this->createUser()->id(), 'novice');

    $other = Apiary::create(['name' => 'Other', 'uid' => $this->owner->id()]);
    $other->save();
    ApiaryMembership::create([
      'apiary' => $other->id(),
      'account' => $this->createUser()->id(),
      'role' => 'novice',
    ])->save();

    // The creator's `owner` membership is there too.
    $members = $this->apiary->getApiaryMembers();
    $this->assertCount(3, $members);
    $roles = array_map(fn(ApiaryMembership $m): string => $m->getRole(), $members);
    sort($roles);
    $this->assertSame(['keeper', 'novice', 'owner'], $roles);
  }

  /**
   * Tests that isApiaryMember() recognises the owner and members only.
   */
  public function testIsApiaryMember(): void {
    $member = $this->createUser();
    $outsider = $this->createUser();
    $this->addMember($member->id(), 'novice');

    $this->assertTrue($this->apiary->isApiaryMember($this->owner));
    $this->assertTrue($this->apiary->isApiaryMember($member));
    $this->assertFalse($this->apiary->isApiaryMember($outsider));
  }

  /**
   * Tests that membership URLs carry the apiary the routes need.
   */
  public function testUrlsIncludeApiary(): void {
    $membership = $this->addMember($this->createUser()->id(), 'novice');
    $parameters = $membership->toUrl('delete-form')->getRouteParameters();
    $this->assertEquals($this->apiary->id(), $parameters['apiary']);
    $this->assertEquals($membership->id(), $parameters['apiary_membership']);
  }

  /**
   * Tests that the creator of an apiary is given an owner membership.
   */
  public function testCreatorBecomesOwner(): void {
    $this->assertSame('owner', $this->apiary->getRoleOf($this->owner));
    $this->assertTrue($this->apiary->isOwner($this->owner));
  }

  /**
   * Tests the role lookup and the ordering novice < keeper < owner.
   */
  public function testRoleOrdering(): void {
    $keeper = $this->createUser();
    $novice = $this->createUser();
    $outsider = $this->createUser();
    $this->addMember($keeper->id(), 'keeper');
    $this->addMember($novice->id(), 'novice');

    $this->assertSame('keeper', $this->apiary->getRoleOf($keeper));
    $this->assertNull($this->apiary->getRoleOf($outsider));

    $this->assertTrue($this->apiary->hasRoleAtLeast($novice, 'novice'));
    $this->assertFalse($this->apiary->hasRoleAtLeast($novice, 'keeper'));
    $this->assertTrue($this->apiary->hasRoleAtLeast($keeper, 'keeper'));
    $this->assertFalse($this->apiary->hasRoleAtLeast($keeper, 'owner'));
    $this->assertTrue($this->apiary->hasRoleAtLeast($this->owner, 'keeper'));
    $this->assertFalse($this->apiary->hasRoleAtLeast($outsider, 'novice'));
  }

  /**
   * Tests that a role change is seen at once, not served from the cache.
   */
  public function testRoleChangeResetsTheCache(): void {
    $user = $this->createUser();
    $membership = $this->addMember($user->id(), 'novice');
    $this->assertSame('novice', $this->apiary->getRoleOf($user));

    $membership->set('role', 'keeper')->save();
    $this->assertSame('keeper', $this->apiary->getRoleOf($user));

    $membership->delete();
    $this->assertNull($this->apiary->getRoleOf($user));
  }

  /**
   * Tests that access does not come from the apiary's uid.
   */
  public function testUidGrantsNothing(): void {
    $owner_membership = array_values(array_filter(
      $this->apiary->getApiaryMembers(),
      fn(ApiaryMembership $m): bool => (int) $m->get('account')->target_id === (int) $this->owner->id()
    ))[0];
    // A second owner, so the first can be removed.
    $this->addMember($this->createUser()->id(), 'owner');
    $owner_membership->delete();

    $this->assertSame((int) $this->owner->id(), (int) $this->apiary->getOwnerId(), 'uid is still "created by".');
    $this->assertNull($this->apiary->getRoleOf($this->owner));
    $this->assertFalse($this->apiary->isApiaryMember($this->owner));
  }

  /**
   * Tests that the last owner cannot be demoted or removed.
   */
  public function testLastOwnerIsProtected(): void {
    $owner_membership = array_values($this->apiary->getApiaryMembers())[0];
    $this->assertTrue($owner_membership->isLastOwner());
    $this->assertFalse($owner_membership->access('delete', $this->owner), 'The last owner cannot be removed.');

    $owner_membership->set('role', 'keeper');
    $this->expectException(EntityStorageException::class);
    $owner_membership->save();
  }

  /**
   * Tests that owners may remove other owners while one remains.
   */
  public function testOwnerMayRemoveAnotherOwner(): void {
    $second = $this->addMember($this->createUser()->id(), 'owner');

    $this->assertFalse($second->isLastOwner());
    $this->assertTrue($second->access('delete', $this->owner));
  }

  /**
   * Tests the update hook that renames roles and creates owner memberships.
   */
  public function testUpdateHookRenamesRolesAndCreatesOwners(): void {
    \Drupal::moduleHandler()->loadInclude('hivelog', 'install');
    $viewer = $this->createUser();
    $editor = $this->createUser();
    $this->addMember($viewer->id(), 'novice');
    $this->addMember($editor->id(), 'keeper');
    $database = \Drupal::database();
    $database->update('hivelog_apiary_membership')->fields(['role' => 'viewer'])
      ->condition('account', $viewer->id())->execute();
    $database->update('hivelog_apiary_membership')->fields(['role' => 'editor'])
      ->condition('account', $editor->id())->execute();
    // An apiary whose creator has no membership (made before roles).
    $legacy = Apiary::create(['name' => 'Legacy', 'uid' => $this->owner->id()]);
    $legacy->save();
    $database->delete('hivelog_apiary_membership')->condition('apiary', $legacy->id())->execute();
    \Drupal::entityTypeManager()->getStorage('apiary_membership')->resetCache();
    Apiary::resetRoleCache();

    $sandbox = [];
    // @phpstan-ignore-next-line
    hivelog_update_10035($sandbox);
    // @phpstan-ignore-next-line
    hivelog_update_10035($sandbox);

    Apiary::resetRoleCache();
    $this->assertSame('novice', $this->apiary->getRoleOf($viewer));
    $this->assertSame('keeper', $this->apiary->getRoleOf($editor));
    $this->assertSame('owner', $legacy->getRoleOf($this->owner));
    $this->assertCount(1, $legacy->getApiaryMembers(), 'Running the hook twice adds nothing.');
  }

  /**
   * Tests the apiary-level operations: write (beekeeper up) and update (owner).
   *
   * `update` is the apiary's own settings; `write` is "may add or change
   * records inside the apiary", used by the scoped add routes and the
   * parent-access rule.
   */
  public function testApiaryWriteAndUpdateOperationsFollowTheRole(): void {
    $permissions = ['view own apiary', 'edit own apiary', 'delete own apiary'];
    $keeper = $this->createUser($permissions);
    $novice = $this->createUser($permissions);
    $outsider = $this->createUser($permissions);
    $this->addMember($keeper->id(), 'keeper');
    $this->addMember($novice->id(), 'novice');
    $owner = $this->createUser($permissions);
    $this->addMember($owner->id(), 'owner');

    $this->assertTrue($this->apiary->access('write', $owner));
    $this->assertTrue($this->apiary->access('write', $keeper));
    $this->assertFalse($this->apiary->access('write', $novice));
    $this->assertFalse($this->apiary->access('write', $outsider));

    $this->assertTrue($this->apiary->access('update', $owner));
    $this->assertFalse($this->apiary->access('update', $keeper), 'The apiary settings are owner-only.');
    $this->assertFalse($this->apiary->access('update', $novice));

    $this->assertTrue($this->apiary->access('view', $novice));
    $this->assertFalse($this->apiary->access('delete', $keeper), 'Only an owner deletes the apiary.');
  }

  /**
   * Tests that only an owner manages members.
   */
  public function testOnlyOwnersManageMembers(): void {
    $permissions = ['view own apiary', 'edit own apiary'];
    $keeper = $this->createUser($permissions);
    $owner = $this->createUser($permissions);
    $target = $this->addMember($this->createUser()->id(), 'novice');
    $this->addMember($keeper->id(), 'keeper');
    $this->addMember($owner->id(), 'owner');

    $this->assertTrue($target->access('update', $owner));
    $this->assertTrue($target->access('delete', $owner));
    $this->assertFalse($target->access('update', $keeper));
    $this->assertFalse($target->access('delete', $keeper));
    $this->assertFalse($target->access('view', $keeper));
  }

}
