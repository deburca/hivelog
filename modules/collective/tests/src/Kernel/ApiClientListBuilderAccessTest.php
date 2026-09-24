<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\collective\Entity\ApiClient;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ApiClientListBuilder::load() filters rows by per-entity access.
 *
 * Part of task 0124: `ApiClientAccessControlHandler`'s "own" permission is
 * a flat owner check (no apiary dimension), but before
 * `HivelogListBuilder::load()` filtered rows, a `view own api client`
 * user still saw every other owner's clients on `/hivelog/api-clients`.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiClientListBuilderAccessTest extends KernelTestBase {

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
    'collective',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('api_client');
    $this->installSchema('file', ['file_usage']);

    // Burn uid 1 (bypasses all permission checks) on a throwaway account.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();
  }

  /**
   * Tests a "view own" user does not see another owner's client in load().
   */
  public function testOwnViewerDoesNotSeeOthersClientInLoad(): void {
    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->save();
    $client = ApiClient::create(['label' => "Owner's Client", 'uid' => $owner->id()]);
    $client->save();

    $role = Role::create(['id' => 'own_client_viewer', 'label' => 'Own client viewer']);
    $role->grantPermission('view own api client');
    $role->save();
    $viewer = User::create(['name' => 'viewer', 'mail' => 'viewer@example.com']);
    $viewer->addRole('own_client_viewer');
    $viewer->save();
    $this->setCurrentUser($viewer);

    $loaded = \Drupal::entityTypeManager()->getListBuilder('api_client')->load();
    $this->assertArrayNotHasKey($client->id(), $loaded);
  }

  /**
   * Tests the owner sees their own client, and "view any" sees every client.
   */
  public function testOwnerAndAnyViewerSeeClientInLoad(): void {
    $owner = User::create(['name' => 'owner2', 'mail' => 'owner2@example.com']);
    $owner->save();
    $client = ApiClient::create(['label' => "Owner's Client", 'uid' => $owner->id()]);
    $client->save();

    $own_role = Role::create(['id' => 'own_client_owner', 'label' => 'Own client owner']);
    $own_role->grantPermission('view own api client');
    $own_role->save();
    $owner->addRole('own_client_owner');
    $owner->save();
    $this->setCurrentUser($owner);
    $loaded = \Drupal::entityTypeManager()->getListBuilder('api_client')->load();
    $this->assertArrayHasKey($client->id(), $loaded);

    $any_role = Role::create(['id' => 'any_client_viewer', 'label' => 'Any client viewer']);
    $any_role->grantPermission('view any api client');
    $any_role->save();
    $any_viewer = User::create(['name' => 'any-viewer', 'mail' => 'any-viewer@example.com']);
    $any_viewer->addRole('any_client_viewer');
    $any_viewer->save();
    $this->setCurrentUser($any_viewer);
    $loaded = \Drupal::entityTypeManager()->getListBuilder('api_client')->load();
    $this->assertArrayHasKey($client->id(), $loaded);
  }

  /**
   * Tests render() carries the same filtering through to the row output.
   */
  public function testRenderHidesOtherOwnersClient(): void {
    $owner = User::create(['name' => 'owner3', 'mail' => 'owner3@example.com']);
    $owner->save();
    ApiClient::create(['label' => 'Hidden Client', 'uid' => $owner->id()])->save();

    $role = Role::create(['id' => 'own_client_viewer2', 'label' => 'Own client viewer 2']);
    $role->grantPermission('view own api client');
    $role->save();
    $viewer = User::create(['name' => 'viewer2', 'mail' => 'viewer2@example.com']);
    $viewer->addRole('own_client_viewer2');
    $viewer->save();
    $this->setCurrentUser($viewer);

    $build = \Drupal::entityTypeManager()->getListBuilder('api_client')->render();
    $this->assertCount(0, $build['table']['#props']['rows']);
  }

}
