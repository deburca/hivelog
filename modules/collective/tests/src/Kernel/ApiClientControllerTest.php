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
   * The client's owner (has view + update access).
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

    $role = Role::create(['id' => 'client_owner', 'label' => 'Client owner']);
    $role->grantPermission('view own api client');
    $role->grantPermission('edit own api client');
    $role->save();

    // "any" (not "own") — ApiClient's access model is pure ownership
    // (ApiClientAccessControlHandler), with no membership concept like
    // an apiary's beekeepers; a non-owner can only ever view via "any".
    $view_only_role = Role::create(['id' => 'client_viewer', 'label' => 'Client viewer']);
    $view_only_role->grantPermission('view any api client');
    $view_only_role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('client_owner');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('client_owner');
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
