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
   * Tests act_now/inspect_soon hives each appear as their own stat tile.
   *
   * Task 0110: every insight gets a tile linking to its hive, replacing
   * the old chip+text row.
   */
  public function testActNowAndInspectSoonHivesAppearAsTiles(): void {
    $act_now_hive = $this->createHive('Act Now Hive');
    $this->createInsight($act_now_hive, 'act_now', ['recommendation' => 'Add a super']);

    $inspect_hive = $this->createHive('Inspect Soon Hive');
    $this->createInsight($inspect_hive, 'inspect_soon', ['recommendation' => 'Possible swarm risk']);

    $section = $this->build();
    $this->assertArrayHasKey('tiles', $section['nexus_ai_insights']);
    $tiles = $section['nexus_ai_insights']['tiles'];

    $values = array_map(fn(array $t) => $t['#props']['label'] . '|' . $t['#props']['value'], array_filter(
      $tiles,
      fn($k) => str_starts_with((string) $k, 'tile_'),
      ARRAY_FILTER_USE_KEY
    ));
    $this->assertContains('Act Now Hive|Act now', $values);
    $this->assertContains('Inspect Soon Hive|Inspect soon', $values);

    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);
    $this->assertStringContainsString('Add a super', $rendered);
    $this->assertStringContainsString('Possible swarm risk', $rendered);
  }

  /**
   * Tests each tile links to its own hive's canonical page.
   */
  public function testTileLinksToItsHive(): void {
    $hive = $this->createHive('Act Now Hive');
    $this->createInsight($hive, 'act_now');

    $section = $this->build();
    $tile = $section['nexus_ai_insights']['tiles']['tile_0'];
    $this->assertEquals($hive->toUrl()->toString(), $tile['#props']['url']);
  }

  /**
   * Tests act_now sorts before inspect_soon, which sorts before all_clear.
   */
  public function testActNowSortsBeforeInspectSoonBeforeAllClear(): void {
    $clear_hive = $this->createHive('Clear Hive');
    $this->createInsight($clear_hive, 'all_clear');

    $inspect_hive = $this->createHive('Inspect Soon Hive');
    $this->createInsight($inspect_hive, 'inspect_soon');

    $act_now_hive = $this->createHive('Act Now Hive');
    $this->createInsight($act_now_hive, 'act_now');

    $section = $this->build();
    $labels = array_map(
      fn(array $t) => $t['#props']['label'],
      array_filter($section['nexus_ai_insights']['tiles'], fn($k) => str_starts_with((string) $k, 'tile_'), ARRAY_FILTER_USE_KEY)
    );
    $this->assertSame(['Act Now Hive', 'Inspect Soon Hive', 'Clear Hive'], array_values($labels));
  }

  /**
   * Tests every fresh all_clear hive gets its own tile.
   */
  public function testFreshAllClearHivesEachGetTile(): void {
    $hive_one = $this->createHive('Clear Hive One');
    $this->createInsight($hive_one, 'all_clear');
    $hive_two = $this->createHive('Clear Hive Two');
    $this->createInsight($hive_two, 'all_clear');

    $section = $this->build();
    $labels = array_map(
      fn(array $t) => $t['#props']['label'],
      array_filter($section['nexus_ai_insights']['tiles'], fn($k) => str_starts_with((string) $k, 'tile_'), ARRAY_FILTER_USE_KEY)
    );
    $this->assertCount(2, $labels);
    $this->assertContains('Clear Hive One', $labels);
    $this->assertContains('Clear Hive Two', $labels);
  }

  /**
   * Tests a stale all_clear insight gets no tile at all.
   *
   * An out-of-date "nothing's wrong" must never be shown as current
   * reassurance.
   */
  public function testStaleAllClearInsightGetsNoTile(): void {
    $hive = $this->createHive('Stale Clear Hive');
    $this->createInsight($hive, 'all_clear', ['generated' => \Drupal::time()->getRequestTime() - (49 * 3600)]);

    $section = $this->build();
    $this->assertArrayNotHasKey('tiles', $section['nexus_ai_insights']);
    $this->assertArrayHasKey('empty', $section['nexus_ai_insights']);
  }

  /**
   * Tests a stale act_now insight still gets a tile.
   *
   * Staleness only withholds the positive (`all_clear`) verdict, never
   * an actionable one.
   */
  public function testStaleActNowInsightStillGetsTile(): void {
    $hive = $this->createHive('Stale Act Now Hive');
    $this->createInsight($hive, 'act_now', ['generated' => \Drupal::time()->getRequestTime() - (49 * 3600)]);

    $section = $this->build();
    $this->assertArrayHasKey('tiles', $section['nexus_ai_insights']);
  }

  /**
   * Tests a hive with no insight at all shows an explicit empty state.
   *
   * The hive itself is excluded, and the section shows an explicit
   * "nothing analysed yet" message rather than nothing/an ambiguous
   * count — real user-reported confusion with the old "0 hives all
   * clear today" wording, which looked identical whether nothing had
   * run yet or every hive was genuinely fine.
   */
  public function testHiveWithNoInsightExcludedAndShowsExplicitEmptyState(): void {
    $this->createHive('Unmonitored Hive');

    $section = $this->build();
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($section['nexus_ai_insights']);

    $this->assertStringNotContainsString('Unmonitored Hive', $rendered);
    $this->assertArrayNotHasKey('tiles', $section['nexus_ai_insights']);
    $this->assertStringContainsString('No AI insights yet', $rendered);
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
