<?php

declare(strict_types=1);

namespace Drupal\Tests\collective\Kernel;

use Drupal\collective\Controller\ApiClientController;
use Drupal\collective\Entity\ApiClient;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the API Client canonical page controller.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiClientControllerTest extends KernelTestBase {

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
   * A test API client, owned by `$owner`.
   */
  protected ApiClient $client;

  /**
   * The client's owner (has view-own + edit-any access).
   *
   * Update access has no "own" path since task 0135 removed `edit own
   * api client` as dead config, so this fixture grants `edit any api
   * client` to exercise the regenerate button's update-access branch —
   * it happens to also be this client's owner, but that ownership plays
   * no role in the update check itself anymore.
   */
  protected User $owner;

  /**
   * Another user with the same role but not the owner (no access).
   */
  protected User $outsider;

  /**
   * A user with view-only access (no edit permission at all).
   */
  protected User $viewOnlyMember;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('api_client');

    // Update has no "own" path since task 0135 removed `edit own api
    // client` as dead config, so the owner fixture is granted `edit any
    // api client` directly to exercise the regenerate button's
    // update-access branch — happens to also own the fixture client,
    // but that ownership plays no role in the update check anymore.
    $role = Role::create(['id' => 'client_owner', 'label' => 'Client owner']);
    $role->grantPermission('view own api client');
    $role->grantPermission('edit any api client');
    $role->save();

    // "any" (not "own") — ApiClient's access model is pure ownership
    // (ApiClientAccessControlHandler), with no membership concept like
    // an apiary's beekeepers; a non-owner can only ever view via "any".
    $view_only_role = Role::create(['id' => 'client_viewer', 'label' => 'Client viewer']);
    $view_only_role->grantPermission('view any api client');
    $view_only_role->save();

    // Plain "view own" only — no edit — so denial in
    // testViewDeniedForOutsider is purely about not owning the fixture
    // client, not incidentally about lacking edit access too.
    $outsider_role = Role::create(['id' => 'client_outsider', 'label' => 'Client outsider']);
    $outsider_role->grantPermission('view own api client');
    $outsider_role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('client_owner');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('client_outsider');
    $this->outsider->save();

    $this->viewOnlyMember = User::create(['name' => 'view_only', 'mail' => 'view_only@example.com']);
    $this->viewOnlyMember->addRole('client_viewer');
    $this->viewOnlyMember->save();

    $this->client = ApiClient::create([
      'label' => 'Dashboard Tablet',
      'uid' => $this->owner->id(),
    ]);
    $this->client->save();
  }

  /**
   * Tests the page renders and includes a regenerate button for an owner.
   *
   * The button uses a `danger` variant since regenerating the token
   * invalidates the previous one.
   */
  public function testViewRendersForAuthorizedUser(): void {
    $this->setCurrentUser($this->owner);

    $controller = new ApiClientController();
    $build = $controller->view($this->client);

    $this->assertArrayHasKey('summary', $build);
    // Task 0131: the generic detail-table class is kept alongside the
    // per-entity class.
    $this->assertContains('hivelog-detail-table', $build['summary']['#attributes']['class']);
    $this->assertContains('hivelog-api-client-table', $build['summary']['#attributes']['class']);
    $this->assertArrayHasKey('endpoints', $build);
    $this->assertArrayHasKey('regenerate', $build);
    $this->assertEquals('hivelog:button', $build['regenerate']['button']['#component']);
    $this->assertEquals('danger', $build['regenerate']['button']['#props']['variant']);
    $this->assertStringContainsString(
      '/api-client/' . $this->client->id() . '/regenerate-token',
      $build['regenerate']['button']['#props']['url']
    );
  }

  /**
   * Tests the regenerate action is hidden for a viewer without update access.
   */
  public function testRegenerateActionHiddenWithoutUpdateAccess(): void {
    $this->setCurrentUser($this->viewOnlyMember);
    $controller = new ApiClientController();
    $build = $controller->view($this->client);

    $this->assertArrayHasKey('summary', $build);
    $this->assertArrayNotHasKey('regenerate', $build);
  }

  /**
   * Tests the page-owned Edit/Delete button group appears with access.
   *
   * Task 0118 — this page previously had no page-owned actions and no
   * local task tabs; it was only editable from the collection row.
   * Needs its own role, since `$this->owner` only holds `edit any api
   * client` (enough for the existing regenerate-button test) — this
   * one wants both `edit any` and `delete any`.
   */
  public function testActionsAppearWithUpdateAndDeleteAccess(): void {
    $role = Role::create(['id' => 'client_full_access', 'label' => 'Client full access']);
    $role->grantPermission('view any api client');
    $role->grantPermission('edit any api client');
    $role->grantPermission('delete any api client');
    $role->save();
    $full_access = User::create(['name' => 'full_access', 'mail' => 'full_access@example.com']);
    $full_access->addRole('client_full_access');
    $full_access->save();

    $this->setCurrentUser($full_access);
    $controller = new ApiClientController();
    $build = $controller->view($this->client);

    $this->assertEquals('hivelog:button-group', $build['actions']['#component']);
    $labels = array_column($build['actions']['#props']['buttons'], 'label');
    $this->assertContains('Edit', $labels);
    $this->assertContains('Delete', $labels);
  }

  /**
   * Tests the page-owned Edit/Delete button group is absent without access.
   */
  public function testActionsAbsentWithoutUpdateOrDeleteAccess(): void {
    $this->setCurrentUser($this->viewOnlyMember);
    $controller = new ApiClientController();
    $build = $controller->view($this->client);

    $this->assertSame([], $build['actions']);
  }

  /**
   * Tests a non-owner without "any" access cannot view the page at all.
   */
  public function testViewDeniedForOutsider(): void {
    $this->setCurrentUser($this->outsider);

    $controller = new ApiClientController();
    $this->expectException(AccessDeniedHttpException::class);
    $controller->view($this->client);
  }

  /**
   * Tests the canonical route is registered and resolvable.
   */
  public function testCanonicalRouteIsRegistered(): void {
    \Drupal::service('router.builder')->rebuild();

    /** @var \Symfony\Component\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('entity.api_client.canonical');

    $this->assertEquals('/hivelog/api-client/{api_client}', $route->getPath());
  }

}
