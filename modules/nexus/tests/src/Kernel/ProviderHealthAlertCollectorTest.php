<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\nexus\Entity\AiProviderConfig;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AiProviderConfig staleness "Needs attention" rule (task 0093).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ProviderHealthAlertCollectorTest extends KernelTestBase {

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
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('product');
    $this->installEntitySchema('ai_provider_config');
    $this->installSchema('file', ['file_usage']);

    // The first user created in a kernel test is uid 1 (the superuser),
    // matching hivelog core's own DashboardTest::makeCurrentUser()
    // convention and SensorAlertCollectorTest's own setUp() — bypasses
    // per-entity access filtering so tests focus on the alert *rule*,
    // not access parity.
    $user = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $user->id()]);
    $this->apiary->save();
  }

  /**
   * Collects alerts for `$this->apiary` via the real service.
   */
  protected function collect(): array {
    $collector = \Drupal::service('nexus.provider_health_alert_collector');
    return $collector->collectAlerts([$this->apiary->id() => $this->apiary], new CacheableMetadata());
  }

  /**
   * Tests a config whose last_run is past the threshold fires.
   */
  public function testStaleConfigFires(): void {
    AiProviderConfig::create([
      'label' => 'Stale Config',
      'mode' => 'ai_module',
      'last_run' => \Drupal::time()->getRequestTime() - (37 * 3600),
    ])->save();

    $alerts = $this->collect();
    $this->assertCount(1, $alerts);
    $this->assertEquals('warning', $alerts[0]['severity']);
    $this->assertEquals('Stale Config', $alerts[0]['title']);
  }

  /**
   * Tests a config that ran recently does not fire.
   */
  public function testRecentConfigDoesNotFire(): void {
    AiProviderConfig::create([
      'label' => 'Recent Config',
      'mode' => 'ai_module',
      'last_run' => \Drupal::time()->getRequestTime() - 3600,
    ])->save();

    $this->assertSame([], $this->collect());
  }

  /**
   * Tests a config that has never run doesn't false-alarm.
   *
   * Mirrors `SensorAlertCollectorTest::testDeviceThatNeverReportedDoesNotFireOffline()`
   * — a freshly-provisioned config hasn't had its first nexus_cron()
   * pass yet.
   */
  public function testNeverRunConfigDoesNotFire(): void {
    AiProviderConfig::create([
      'label' => 'Never Run Config',
      'mode' => 'ai_module',
    ])->save();

    $this->assertSame([], $this->collect());
  }

  /**
   * Tests a disabled config is excluded even if stale.
   */
  public function testDisabledConfigExcluded(): void {
    AiProviderConfig::create([
      'label' => 'Disabled Stale Config',
      'mode' => 'ai_module',
      'enabled' => FALSE,
      'last_run' => \Drupal::time()->getRequestTime() - (37 * 3600),
    ])->save();

    $this->assertSame([], $this->collect());
  }

  /**
   * Tests the hook is actually dispatched by the module handler.
   */
  public function testHookIsDispatchedByModuleHandler(): void {
    AiProviderConfig::create([
      'label' => 'Stale Config',
      'mode' => 'ai_module',
      'last_run' => \Drupal::time()->getRequestTime() - (37 * 3600),
    ])->save();

    $alerts = \Drupal::moduleHandler()->invokeAll(
      'hivelog_needs_attention_alerts',
      [[$this->apiary->id() => $this->apiary], new CacheableMetadata()]
    );
    $this->assertNotEmpty($alerts);
  }

  /**
   * Tests the alert appears in DashboardController's real merged queue.
   */
  public function testProviderAlertAppearsInMergedDashboardQueue(): void {
    AiProviderConfig::create([
      'label' => 'Stale Config',
      'mode' => 'ai_module',
      'last_run' => \Drupal::time()->getRequestTime() - (37 * 3600),
    ])->save();

    /** @var \Drupal\hivelog\Controller\DashboardController $controller */
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(DashboardController::class);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($controller->view());

    $this->assertStringContainsString('AI provider stale', $html);
  }

}
