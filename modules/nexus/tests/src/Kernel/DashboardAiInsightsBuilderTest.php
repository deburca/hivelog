<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nexus\Entity\HiveInsight;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dashboard "AI Insights" section (task 0093).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class DashboardAiInsightsBuilderTest extends KernelTestBase {

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
   * An apiary with AI insights enabled.
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
    $this->installEntitySchema('hive_insight');
    $this->installEntitySchema('ai_provider_config');
    $this->installSchema('file', ['file_usage']);

    // The first user created in a kernel test is uid 1 — bypasses
    // per-entity access filtering, matching every other dashboard-hook
    // test in this project (see SensorAlertCollectorTest, task 0081).
    $user = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Test Apiary', 'uid' => $user->id(), 'ai_insights_enabled' => TRUE]);
    $this->apiary->save();
  }

  /**
   * Creates a hive under `$this->apiary`.
   */
  protected function createHive(string $name): Hive {
    $hive = Hive::create(['name' => $name, 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    return $hive;
  }

  /**
   * Creates a hive-scoped HiveInsight for `$hive`.
   */
  protected function createInsight(Hive $hive, string $verdict, array $overrides = []): HiveInsight {
    $insight = HiveInsight::create($overrides + [
      'apiary' => $this->apiary->id(),
      'hive' => $hive->id(),
      'scope' => 'hive',
      'verdict' => $verdict,
      'recommendation' => 'Test recommendation',
      'signals' => '- Test signal',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();
    return $insight;
  }

  /**
   * Builds the section for `$this->apiary` via the real service.
   */
  protected function build(): array {
    $builder = \Drupal::service('nexus.dashboard_ai_insights_builder');
    return $builder->build([$this->apiary->id() => $this->apiary], new CacheableMetadata());
  }

  /**
   * Tests the section is absent with no ai_insights_enabled apiaries.
   */
  public function testSectionHiddenWithNoOptedInApiaries(): void {
    $this->apiary->set('ai_insights_enabled', FALSE);
    $this->apiary->save();

    $this->assertSame([], $this->build());
  }

  /**
   * Tests act_now/inspect_soon hives appear as action rows.
   */
  public function testActNowAndInspectSoonHivesAppearAsRows(): void {
    $act_now_hive = $this->createHive('Act Now Hive');
    $this->createInsight($act_now_hive, 'act_now', ['recommendation' => 'Add a super']);

    $inspect_hive = $this->createHive('Inspect Soon Hive');
    $this->createInsight($inspect_hive, 'inspect_soon', ['recommendation' => 'Possible swarm risk']);

    $section = $this->build();
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);

    $this->assertStringContainsString('Add a super', $rendered);
    $this->assertStringContainsString('Possible swarm risk', $rendered);
  }

  /**
   * Tests act_now sorts before inspect_soon.
   */
  public function testActNowSortsBeforeInspectSoon(): void {
    $inspect_hive = $this->createHive('Inspect Soon Hive');
    $this->createInsight($inspect_hive, 'inspect_soon', ['recommendation' => 'Inspect me']);

    $act_now_hive = $this->createHive('Act Now Hive');
    $this->createInsight($act_now_hive, 'act_now', ['recommendation' => 'Act on me']);

    $section = $this->build();
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);

    $this->assertTrue(strpos($rendered, 'Act on me') < strpos($rendered, 'Inspect me'));
  }

  /**
   * Tests the "N hives all clear today" summary counts fresh all_clear hives.
   */
  public function testAllClearSummaryCountsFreshAllClearHives(): void {
    $hive_one = $this->createHive('Clear Hive One');
    $this->createInsight($hive_one, 'all_clear');
    $hive_two = $this->createHive('Clear Hive Two');
    $this->createInsight($hive_two, 'all_clear');

    $section = $this->build();
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);

    $this->assertStringContainsString('2 hives all clear today.', $rendered);
  }

  /**
   * Tests a stale all_clear insight is NOT counted as "clear today".
   */
  public function testStaleAllClearInsightNotCounted(): void {
    $hive = $this->createHive('Stale Clear Hive');
    $this->createInsight($hive, 'all_clear', ['generated' => \Drupal::time()->getRequestTime() - (49 * 3600)]);

    $section = $this->build();
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);

    $this->assertStringContainsString('0 hives all clear today.', $rendered);
  }

  /**
   * Tests a hive with no insight at all is excluded from both counts.
   */
  public function testHiveWithNoInsightExcludedFromBoth(): void {
    $this->createHive('Unmonitored Hive');

    $section = $this->build();
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);

    $this->assertStringNotContainsString('Unmonitored Hive', $rendered);
    $this->assertStringContainsString('0 hives all clear today.', $rendered);
  }

  /**
   * Tests the hook is actually dispatched by the module handler.
   */
  public function testHookIsDispatchedByModuleHandler(): void {
    $hive = $this->createHive('Act Now Hive');
    $this->createInsight($hive, 'act_now');

    $sections = \Drupal::moduleHandler()->invokeAll(
      'hivelog_dashboard_sections',
      [[$this->apiary->id() => $this->apiary], new CacheableMetadata()]
    );
    $this->assertArrayHasKey('nexus_ai_insights', $sections);
  }

  /**
   * Tests the section appears in DashboardController's real rendered page.
   */
  public function testSectionAppearsInRealDashboardPage(): void {
    $hive = $this->createHive('Act Now Hive');
    $this->createInsight($hive, 'act_now', ['recommendation' => 'Add a super']);

    /** @var \Drupal\hivelog\Controller\DashboardController $controller */
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(DashboardController::class);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($controller->view());

    $this->assertStringContainsString('AI Insights', $html);
    $this->assertStringContainsString('Add a super', $html);
  }

}
