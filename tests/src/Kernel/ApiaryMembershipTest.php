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
    $this->owner = $this->createUser();
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
    $membership = $this->addMember($this->createUser()->id(), 'viewer');
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
    $this->addMember($user->id(), 'editor');
  }

  /**
   * Tests that getApiaryMembers() returns the apiary's memberships only.
   */
  public function testGetApiaryMembers(): void {
    $this->addMember($this->createUser()->id(), 'editor');
    $this->addMember($this->createUser()->id(), 'viewer');

    $other = Apiary::create(['name' => 'Other', 'uid' => $this->owner->id()]);
    $other->save();
    ApiaryMembership::create([
      'apiary' => $other->id(),
      'account' => $this->createUser()->id(),
      'role' => 'viewer',
    ])->save();

    $members = $this->apiary->getApiaryMembers();
    $this->assertCount(2, $members);
    $roles = array_map(fn(ApiaryMembership $m): string => $m->getRole(), $members);
    $this->assertContains('editor', $roles);
    $this->assertContains('viewer', $roles);
  }

  /**
   * Tests that isApiaryMember() recognises the owner and members only.
   */
  public function testIsApiaryMember(): void {
    $member = $this->createUser();
    $outsider = $this->createUser();
    $this->addMember($member->id(), 'viewer');

    $this->assertTrue($this->apiary->isApiaryMember($this->owner));
    $this->assertTrue($this->apiary->isApiaryMember($member));
    $this->assertFalse($this->apiary->isApiaryMember($outsider));
  }

  /**
   * Tests that membership URLs carry the apiary the routes need.
   */
  public function testUrlsIncludeApiary(): void {
    $membership = $this->addMember($this->createUser()->id(), 'viewer');
    $parameters = $membership->toUrl('delete-form')->getRouteParameters();
    $this->assertEquals($this->apiary->id(), $parameters['apiary']);
    $this->assertEquals($membership->id(), $parameters['apiary_membership']);
  }

}
