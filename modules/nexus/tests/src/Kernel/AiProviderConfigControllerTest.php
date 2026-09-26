<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\nexus\Controller\AiProviderConfigController;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI Provider Config controller's page-owned actions (0118).
 *
 * `AiProviderConfigManagementUiTest` already covers the rest of
 * `AiProviderConfigController::view()` (the field summary, the AI
 * module status block) — this file is scoped to `buildActions()`
 * specifically, mirroring `ApiClientControllerTest`'s own pair of
 * tests for the identically-shaped `ApiClientAccessControlHandler`.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class AiProviderConfigControllerTest extends KernelTestBase {

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
    'key',
    'hivelog',
    'collective',
    'nexus',
  ];

  /**
   * A test AI provider config.
   */
  protected AiProviderConfig $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('ai_provider_config');

    $this->config = AiProviderConfig::create([
      'label' => 'Test Config',
      'mode' => 'direct_api',
      'provider' => 'anthropic',
      'key' => 'anthropic_api_key',
    ]);
    $this->config->save();
  }

  /**
   * Tests the summary table carries the generic detail-table class.
   *
   * Task 0131: the generic class is kept alongside the per-entity class.
   */
  public function testSummaryTableHasGenericDetailClass(): void {
    // Uid 1 (the first user created in a kernel test) bypasses every
    // permission check, matching AiProviderConfigController::view()'s own
    // access('view') gate.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $controller = new AiProviderConfigController();
    $build = $controller->view($this->config);

    $this->assertContains('hivelog-detail-table', $build['summary']['#attributes']['class']);
    $this->assertContains('hivelog-ai-provider-config-table', $build['summary']['#attributes']['class']);
  }

  /**
   * Tests the page-owned Edit/Delete button group appears with access.
   *
   * This page previously had no page-owned actions and no local task
   * tabs; it was only editable from the collection row.
   */
  public function testActionsAppearWithUpdateAndDeleteAccess(): void {
    $role = Role::create(['id' => 'config_full_access', 'label' => 'Config full access']);
    $role->grantPermission('view any ai provider config');
    $role->grantPermission('edit any ai provider config');
    $role->grantPermission('delete any ai provider config');
    $role->save();
    $full_access = User::create(['name' => 'full_access', 'mail' => 'full_access@example.com']);
    $full_access->addRole('config_full_access');
    $full_access->save();

    $this->setCurrentUser($full_access);
    $controller = new AiProviderConfigController();
    $build = $controller->view($this->config);

    $this->assertEquals('hivelog:button-group', $build['actions']['#component']);
    $labels = array_column($build['actions']['#props']['buttons'], 'label');
    $this->assertContains('Edit', $labels);
    $this->assertContains('Delete', $labels);
  }

  /**
   * Tests the page-owned Edit/Delete button group is absent without access.
   */
  public function testActionsAbsentWithoutUpdateOrDeleteAccess(): void {
    // The first user created in a kernel test becomes uid 1, which
    // bypasses every permission check entirely (Drupal core
    // behaviour) — a throwaway user here ensures $viewer's "cannot"
    // assertion below actually exercises the access check.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'config_view_only', 'label' => 'Config view only']);
    $role->grantPermission('view any ai provider config');
    $role->save();
    $viewer = User::create(['name' => 'viewer', 'mail' => 'viewer@example.com']);
    $viewer->addRole('config_view_only');
    $viewer->save();

    $this->setCurrentUser($viewer);
    $controller = new AiProviderConfigController();
    $build = $controller->view($this->config);

    $this->assertSame([], $build['actions']);
  }

}
