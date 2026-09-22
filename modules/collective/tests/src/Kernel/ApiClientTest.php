<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\collective\Entity\InsightAgent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Insight Agent entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InsightAgentTest extends KernelTestBase {

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
    'collective',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('insight_agent');
  }

  /**
   * Tests creating, updating and deleting an agent.
   */
  public function testCrud(): void {
    $agent = InsightAgent::create(['label' => 'Production Insight Agent']);
    $agent->save();

    $loaded = InsightAgent::load($agent->id());
    $this->assertEquals('Production Insight Agent', $loaded->label());
    $this->assertTrue((bool) $loaded->get('enabled')->value);
    $this->assertTrue($loaded->get('last_run')->isEmpty());

    // Update.
    $agent->set('label', 'Production Insight Agent (renamed)');
    $agent->save();
    $this->assertEquals('Production Insight Agent (renamed)', InsightAgent::load($agent->id())->label());

    // Delete.
    $id = $agent->id();
    $agent->delete();
    $this->assertNull(InsightAgent::load($id));
  }

  /**
   * Tests `enabled` defaults to TRUE.
   */
  public function testFieldDefaults(): void {
    $agent = InsightAgent::create(['label' => 'Default Agent']);
    $this->assertTrue((bool) $agent->get('enabled')->value);
  }

  /**
   * Tests that a token is auto-generated on insert.
   */
  public function testTokenGeneratedOnInsert(): void {
    $agent = InsightAgent::create(['label' => 'Token Test']);
    $this->assertNull($agent->getPlainTextToken());
    $agent->save();

    $plaintext = $agent->getPlainTextToken();
    $this->assertIsString($plaintext);
    $this->assertNotEmpty($plaintext);
    $this->assertNotEmpty($agent->get('token')->value);
    $this->assertNotEquals($plaintext, $agent->get('token')->value);
    $this->assertTrue($agent->verifyToken($plaintext));
  }

  /**
   * Tests that the plaintext token is not recoverable after a plain load.
   */
  public function testPlainTextTokenNotExposedAfterLoad(): void {
    $agent = InsightAgent::create(['label' => 'Token Load Test']);
    $agent->save();
    $plaintext = $agent->getPlainTextToken();

    $loaded = InsightAgent::load($agent->id());
    $this->assertNull($loaded->getPlainTextToken());
    $this->assertTrue($loaded->verifyToken($plaintext));
  }

  /**
   * Tests that regenerating a token invalidates the previous one.
   */
  public function testTokenRegeneration(): void {
    $agent = InsightAgent::create(['label' => 'Regeneration Test']);
    $agent->save();
    $original = $agent->getPlainTextToken();

    $new_plaintext = $agent->generateToken();
    $agent->save();

    $this->assertNotEquals($original, $new_plaintext);
    $this->assertFalse($agent->verifyToken($original));
    $this->assertTrue($agent->verifyToken($new_plaintext));
  }

  /**
   * Tests ownership-based access — no apiary/hive dimension at all.
   */
  public function testOwnershipBasedAccess(): void {
    // The first user created in a kernel test is uid 1, which bypasses
    // permission checks entirely (Drupal core behaviour) — a throwaway
    // user here ensures $owner's "can" assertions below actually
    // exercise the ownership-check logic, not the uid-1 bypass.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'agent_manager', 'label' => 'Agent Manager']);
    $role->grantPermission('view own insight agent');
    $role->grantPermission('edit own insight agent');
    $role->grantPermission('delete own insight agent');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('agent_manager');
    $owner->save();

    $other = User::create(['name' => 'other', 'mail' => 'other@example.com']);
    $other->addRole('agent_manager');
    $other->save();

    $agent = InsightAgent::create(['label' => 'Owned Agent', 'uid' => $owner->id()]);
    $agent->save();

    $this->assertTrue($agent->access('view', $owner));
    $this->assertTrue($agent->access('update', $owner));
    $this->assertTrue($agent->access('delete', $owner));

    // A different user with only "own" permissions cannot touch an
    // agent they don't own — no apiary-membership escape hatch exists
    // for this entity type, unlike every apiary-scoped one.
    $this->assertFalse($agent->access('view', $other));
    $this->assertFalse($agent->access('update', $other));
    $this->assertFalse($agent->access('delete', $other));
  }

  /**
   * Tests "any" permissions grant access regardless of ownership.
   */
  public function testAnyPermissionGrantsAccessRegardlessOfOwnership(): void {
    $role = Role::create(['id' => 'agent_admin', 'label' => 'Agent Admin']);
    $role->grantPermission('view any insight agent');
    $role->save();

    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->save();

    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('agent_admin');
    $admin->save();

    $agent = InsightAgent::create(['label' => 'Owned Agent', 'uid' => $owner->id()]);
    $agent->save();

    $this->assertTrue($agent->access('view', $admin));
  }

  /**
   * Tests only `administer hivelog` may create an agent.
   *
   * No "add" permission exists at all, unlike every other hivelog
   * entity type.
   */
  public function testOnlyAdministerHivelogGrantsCreateAccess(): void {
    // Consume uid 1 (Drupal's permission-check-bypassing superuser) so
    // $user below genuinely exercises the permission check.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'agent_manager', 'label' => 'Agent Manager']);
    $role->grantPermission('view own insight agent');
    $role->grantPermission('edit own insight agent');
    $role->grantPermission('delete own insight agent');
    $role->save();

    $user = User::create(['name' => 'user', 'mail' => 'user@example.com']);
    $user->addRole('agent_manager');
    $user->save();

    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('insight_agent');
    $this->assertFalse($access_handler->createAccess(NULL, $user));

    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('hivelog_admin');
    $admin->save();

    $this->assertTrue($access_handler->createAccess(NULL, $admin));
  }

}
