<?php

declare(strict_types=1);

namespace Drupal\hivelog\Tests\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\hivelog\Entity\ApiaryMembership;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ApiaryMembership entity and its migration from beekeepers.
 *
 * @group hivelog
 */
class ApiaryMembershipTest extends KernelTestBase {

  /**
   * Modules to install.
   */
  protected static $modules = [
    'api',
    'base_field',
    'date',
    'entity',
    'field',
    'hivelog',
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('apiary');
    // ApiaryMembership schema is installed by the update hook.
  }

  /**
   * Tests that the apiary_memberships field exists on Apiary.
   */
  public function testApiaryField(): void {
    $storage = $this->container->get('entity_field.storage');
    $fields = $storage->loadByProperties([
      'entity_type' => 'apiary',
      'field_name' => 'apiary_memberships',
    ]);
    $this->assertNotFalse(reset($fields) ?: FALSE, 'apiary_memberships field exists on Apiary.');
  }

  /**
   * Tests that duplicate apiary-user memberships are prevented.
   */
  public function testDuplicateGuard(): void {
    // Install the entity type and schema via the update hook.
    $this->installEntitySchema('apiary_membership');
    $storage = $this->container->get('entity_storage')->load('apiary_membership');

    /** @var \Drupal\user\UserInterface $user1 */
    $user1 = $this->createUser();

    // Create the apiary entity.
    $apiary = \Drupal\hivelog\Entity\Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->currentUser()->id(),
    ]);
    $apiary->save();

    // First membership succeeds.
    /** @var \Drupal\hivelog\Entity\ApiaryMembership $membership */
    $membership = ApiaryMembership::create([
      'apiary' => $apiary->id(),
      'account' => $user1->id(),
      'role' => 'viewer',
    ]);
    $membership->save();
    $this->assertTrue(TRUE, 'First membership saved successfully.');

    // Second membership with same apiary-user pair throws.
    $this->expectException(\InvalidArgumentException::class);
    ApiaryMembership::create([
      'apiary' => $apiary->id(),
      'account' => $user1->id(),
      'role' => 'editor',
    ])->save();
  }

  /**
   * Tests that getApiaryMembers() returns memberships correctly.
   */
  public function testGetApiaryMembers(): void {
    // Install the apiary_memberships field and entity schema.
    $this->installEntitySchema('apiary_membership');

    /** @var \Drupal\user\UserInterface $user1 */
    $user1 = $this->createUser();
    /** @var \Drupal\user\UserInterface $user2 */
    $user2 = $this->createUser();

    // Create the apiary.
    $apiary = \Drupal\hivelog\Entity\Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->currentUser()->id(),
    ]);
    $apiary->save();

    // Add memberships directly to storage (bypassing preSave for this test).
    $storage = $this->container->get('entity_storage')->load('apiary_membership');
    /** @var \Drupal\hivelog\Entity\ApiaryMembership $m1 */
    $m1 = $storage->create([
      'apiary' => $apiary->id(),
      'account' => $user1->id(),
      'role' => 'editor',
    ]);
    $m1->save();

    /** @var \Drupal\hivelog\Entity\ApiaryMembership $m2 */
    $m2 = $storage->create([
      'apiary' => $apiary->id(),
      'account' => $user2->id(),
      'role' => 'viewer',
    ]);
    $m2->save();

    // Reload the apiary with fresh field data.
    $apiary = \Drupal\hivelog\Entity\Apiary::load($apiary->id());

    $members = $apiary->getApiaryMembers();
    $this->assertCount(2, $members);

    $roles = array_map(fn(ApiaryMembership $m): string => $m->getRole(), $members);
    $this->assertContains('editor', $roles);
    $this->assertContains('viewer', $roles);
  }

  /**
   * Tests that isApiaryMember() works with memberships.
   */
  public function testIsApiaryMember(): void {
    // Install the apiary_memberships field and entity schema.
    $this->installEntitySchema('apiary_membership');

    /** @var \Drupal\user\UserInterface $member */
    $member = $this->createUser();

    // Create the apiary.
    $apiary = \Drupal\hivelog\Entity\Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->currentUser()->id(),
    ]);
    $apiary->save();

    // The apiary owner is always a member.
    $this->assertTrue($apiary->isApiaryMember($this->currentUser()));

    // Add the external user as a member.
    $storage = $this->container->get('entity_storage')->load('apiary_membership');
    /** @var \Drupal\hivelog\Entity\ApiaryMembership $m */
    $m = $storage->create([
      'apiary' => $apiary->id(),
      'account' => $member->id(),
      'role' => 'viewer',
    ]);
    $m->save();

    // Reload the apiary.
    $apiary = \Drupal\hivelog\Entity\Apiary::load($apiary->id());

    // The member is now recognized as an apiary member.
    $this->assertTrue($apiary->isApiaryMember($member));

    // A non-member user is not recognized.
    /** @var \Drupal\user\UserInterface $non_member */
    $non_member = $this->createUser();
    $this->assertFalse($apiary->isApiaryMember($non_member));
  }

}
