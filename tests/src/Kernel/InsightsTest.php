<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\hivelog\Controller\InsightsController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Insights" landing page.
 *
 * Task 0146, ADR-0104; renamed from "Setup" by task 0152, ADR-0105.
 * `hivelog_app_nav_test` (a test-only module implementing
 * `hook_hivelog_app_nav_items()` with a `parent: 'insights'` item
 * pointing at the real `entity.apiary.collection` route) is enabled
 * per-test via `enableModules()`, not the class's static `$modules` —
 * several tests deliberately want it absent, to cover "no submodule
 * contributes an `insights` item at all" per ADR-0104's own reasoning
 * for why `InsightsPageAccessCheck` derives access from the registry
 * rather than a hard-coded permission.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class InsightsTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    \Drupal::service('router.builder')->rebuild();

    // The first user created in a kernel test becomes uid 1, an
    // implicit superuser that bypasses every permission check — burn
    // it before any test creates a user it expects to be denied.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();
  }

  /**
   * Resolves the Insights controller from the container.
   */
  private function controller(): InsightsController {
    return \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InsightsController::class);
  }

  /**
   * Creates a user with exactly the given permissions.
   */
  private function createUserWithPermissions(array $permissions): User {
    $role = Role::create(['id' => $this->randomMachineName(8), 'label' => 'Test role']);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();

    $user = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
    ]);
    $user->addRole($role->id());
    $user->save();
    return $user;
  }

  /**
   * No module declares a `parent: 'insights'` item — denied even for admin.
   */
  public function testAccessDeniedWhenNoInsightsItemExistsAtAll(): void {
    $admin = $this->createUserWithPermissions(['administer hivelog']);

    $this->assertFalse(
      \Drupal::service('access_manager')->checkNamedRoute('hivelog.insights', [], $admin),
      'administer hivelog must still be denied when the registry has no insights-parented item at all.'
    );
  }

  /**
   * Allowed once the account can reach at least one insights-parented item.
   */
  public function testAccessAllowedWhenAccountCanReachTheChild(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    $allowed = $this->createUserWithPermissions(['view own apiary']);

    $this->assertTrue(
      \Drupal::service('access_manager')->checkNamedRoute('hivelog.insights', [], $allowed),
    );
  }

  /**
   * Denied when the registered item exists but this account can't see it.
   */
  public function testAccessDeniedWhenAccountCannotReachAnyChild(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    // An unrelated permission — not apiary view — so the fake item's
    // own target route stays inaccessible to this account.
    $denied = $this->createUserWithPermissions(['view own hive']);

    $this->assertFalse(
      \Drupal::service('access_manager')->checkNamedRoute('hivelog.insights', [], $denied),
    );
  }

  /**
   * Anonymous is denied even when an insights-parented item is registered.
   */
  public function testAnonymousDenied(): void {
    $this->enableModules(['hivelog_app_nav_test']);

    $this->assertFalse(
      \Drupal::service('access_manager')->checkNamedRoute('hivelog.insights', [], new AnonymousUserSession()),
    );
  }

  /**
   * The page lists exactly the accessible insights-parented items.
   */
  public function testViewListsExactlyTheAccessibleChildren(): void {
    $this->enableModules(['hivelog_app_nav_test']);
    $user = $this->createUserWithPermissions(['view own apiary']);
    \Drupal::currentUser()->setAccount($user);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->controller()->view());

    $this->assertStringContainsString('Test Widgets', $html);
  }

  /**
   * The defensive empty-state branch renders when nothing is accessible.
   *
   * Real requests never reach this — `InsightsPageAccessCheck` denies
   * the route first — but the controller is still tested directly here.
   */
  public function testViewShowsEmptyStateWhenNothingAccessible(): void {
    $user = $this->createUserWithPermissions(['administer hivelog']);
    \Drupal::currentUser()->setAccount($user);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->controller()->view());

    $this->assertStringContainsString('nothing here yet', $html);
  }

  /**
   * The page title is the literal "Insights".
   */
  public function testTitleIsInsights(): void {
    $this->assertEquals('Insights', (string) $this->controller()->title());
  }

}
