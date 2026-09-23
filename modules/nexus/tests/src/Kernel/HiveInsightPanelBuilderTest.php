<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nexus\Entity\HiveInsight;
use Drupal\nexus\HiveInsightPanelBuilder;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "AI Insight" panel builder (task 0092).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInsightPanelBuilderTest extends KernelTestBase {

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
   * An apiary with AI insights enabled, owned by `$owner`.
   */
  protected Apiary $apiary;

  /**
   * A hive under `$apiary`.
   */
  protected Hive $hive;

  /**
   * The apiary owner (has view access to everything under `$apiary`).
   */
  protected User $owner;

  /**
   * A user with no relationship to `$apiary` (no access).
   */
  protected User $outsider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_insight');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own hive insight');
    $role->save();

    $this->owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $this->owner->addRole('beekeeper');
    $this->owner->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com']);
    $this->outsider->addRole('beekeeper');
    $this->outsider->save();

    $this->apiary = Apiary::create([
      'name' => 'Test Apiary',
      'uid' => $this->owner->id(),
      'visibility' => 'private',
      'ai_insights_enabled' => TRUE,
    ]);
    $this->apiary->save();
    $this->hive = Hive::create([
      'name' => 'Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'uid' => $this->owner->id(),
    ]);
    $this->hive->save();

    // HiveInsightPanelBuilder checks access against \Drupal::currentUser(),
    // not any PHP object a test happens to hold — without this, every
    // "authorized user can see it" test below would pass for the wrong
    // reason (nothing visible to anyone by default).
    $this->setCurrentUser($this->owner);
  }

  /**
   * Builds a HiveInsight for `$this->hive` with the given field overrides.
   */
  protected function createInsight(array $values = []): HiveInsight {
    $insight = HiveInsight::create($values + [
      'apiary' => $this->apiary->id(),
      'hive' => $this->hive->id(),
      'scope' => 'hive',
      'verdict' => 'inspect_soon',
      'recommendation' => 'Possible swarm risk — inspect within 2 days',
      'signals' => "- Weight dropped 2.1 kg overnight\n- Queen cells present",
      'confidence' => 'high',
      'generated' => \Drupal::time()->getRequestTime(),
    ]);
    $insight->save();
    return $insight;
  }

  /**
   * The panel builder under test.
   */
  protected function builder(): HiveInsightPanelBuilder {
    return \Drupal::service('nexus.hive_insight_panel_builder');
  }

  /**
   * Tests the panel renders the latest insight's verdict/recommendation/signals.
   */
  public function testPanelRendersLatestInsight(): void {
    $this->createInsight();

    $build = $this->builder()->buildHivePanel($this->hive);
    $this->assertArrayHasKey('nexus_hive_insight', $build);

    $rendered = \Drupal::service('renderer')->renderInIsolation($build['nexus_hive_insight']);
    $this->assertStringContainsString('Inspect soon', (string) $rendered);
    $this->assertStringContainsString('Possible swarm risk', (string) $rendered);
    $this->assertStringContainsString('Weight dropped 2.1 kg overnight', (string) $rendered);
    $this->assertStringContainsString('Confidence: High', (string) $rendered);
  }

  /**
   * Tests the most recently generated insight wins when several exist.
   */
  public function testPanelShowsMostRecentInsightWhenSeveralExist(): void {
    $now = \Drupal::time()->getRequestTime();
    $this->createInsight(['recommendation' => 'Older recommendation', 'generated' => $now - 3600]);
    $this->createInsight(['recommendation' => 'Newer recommendation', 'generated' => $now]);

    $build = $this->builder()->buildHivePanel($this->hive);
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build['nexus_hive_insight']);

    $this->assertStringContainsString('Newer recommendation', $rendered);
    $this->assertStringNotContainsString('Older recommendation', $rendered);
  }

  /**
   * Tests the panel is entirely absent when ai_insights_enabled is FALSE.
   */
  public function testPanelHiddenWhenAiInsightsDisabled(): void {
    $this->createInsight();
    $this->apiary->set('ai_insights_enabled', FALSE);
    $this->apiary->save();

    $build = $this->builder()->buildHivePanel($this->hive);
    $this->assertSame([], $build);
  }

  /**
   * Tests the panel is entirely absent when no insight exists yet.
   */
  public function testPanelHiddenWhenNoInsightExists(): void {
    $build = $this->builder()->buildHivePanel($this->hive);
    $this->assertSame([], $build);
  }

  /**
   * Tests an insight past the staleness threshold is visibly flagged.
   */
  public function testStalenessFlagAppearsPastThreshold(): void {
    $stale_time = \Drupal::time()->getRequestTime() - (49 * 3600);
    $this->createInsight(['generated' => $stale_time]);

    $build = $this->builder()->buildHivePanel($this->hive);
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build['nexus_hive_insight']);

    $this->assertStringContainsString('may be out of date', $rendered);
  }

  /**
   * Tests a recent insight is NOT flagged as stale.
   */
  public function testRecentInsightNotFlaggedStale(): void {
    $this->createInsight(['generated' => \Drupal::time()->getRequestTime() - 3600]);

    $build = $this->builder()->buildHivePanel($this->hive);
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build['nexus_hive_insight']);

    $this->assertStringNotContainsString('may be out of date', $rendered);
  }

  /**
   * Tests apiary-scoped panel renders an apiary-scoped insight.
   */
  public function testApiaryPanelRendersApiaryScopedInsight(): void {
    HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'verdict' => 'all_clear',
      'recommendation' => 'Apiary looks healthy overall',
      'signals' => '- All monitored hives reporting normally',
      'generated' => \Drupal::time()->getRequestTime(),
    ])->save();

    $build = $this->builder()->buildApiaryPanel($this->apiary);
    $this->assertArrayHasKey('nexus_hive_insight', $build);
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build['nexus_hive_insight']);
    $this->assertStringContainsString('All clear', $rendered);
  }

  /**
   * Tests the apiary panel is empty when no apiary-scoped insight exists.
   *
   * Per task 0092: ships now even though nothing produces apiary-scoped
   * rows yet (Phase 2) — a hive-scoped insight on one of this apiary's
   * hives must NOT leak into the apiary's own panel.
   */
  public function testApiaryPanelEmptyWithOnlyHiveScopedInsights(): void {
    $this->createInsight();

    $build = $this->builder()->buildApiaryPanel($this->apiary);
    $this->assertSame([], $build);
  }

  /**
   * Tests a beekeeper without access to the apiary sees no panel at all.
   *
   * `$this->outsider` is never the apiary's owner and isn't listed as an
   * apiary member, and `$this->apiary` stays `private` (setUp()'s
   * default) — so `ApiaryAccessTrait::checkApiaryViewAccess()` denies
   * "own" access despite `$this->outsider` holding the `view own hive
   * insight` permission generally.
   */
  public function testPanelRespectsAccessControl(): void {
    $this->createInsight();
    $this->setCurrentUser($this->outsider);

    $build = $this->builder()->buildHivePanel($this->hive);
    $this->assertSame([], $build);
  }

  /**
   * Tests the Hive stat tile descriptor (task 0110) shows the verdict.
   *
   * And links to the anchor on the full panel already on the same page,
   * not a separate page — HiveInsight has no structured to-do list
   * beyond its one recommendation, so there's nothing a dedicated page
   * would add.
   */
  public function testHiveStatTileShowsVerdictAndLinksToAnchor(): void {
    $this->createInsight(['verdict' => 'act_now', 'recommendation' => 'Add a super']);

    $tile = $this->builder()->buildHiveStatTile($this->hive);
    $this->assertArrayHasKey('nexus_ai_insight', $tile);
    $descriptor = $tile['nexus_ai_insight'];

    $this->assertEquals('Act now', $descriptor['value']);
    $this->assertEquals('critical', $descriptor['sublabel_variant']);
    // Task 0111: links to the dedicated Insights page, not an anchor on
    // the same page — the panel itself moved there.
    $this->assertEquals('entity.hive.insights', $descriptor['url']->getRouteName());
    $this->assertStringContainsString(
      '#' . HiveInsightPanelBuilder::PANEL_ANCHOR_ID,
      $descriptor['url']->toString()
    );
  }

  /**
   * Tests every verdict maps to the stat tile's expected sublabel variant.
   */
  public function testHiveStatTileVariantMapsToVerdict(): void {
    $cases = ['act_now' => 'critical', 'inspect_soon' => 'warning', 'all_clear' => 'default'];
    foreach ($cases as $verdict => $expected_variant) {
      $hive = Hive::create(['name' => 'Hive for ' . $verdict, 'apiary' => $this->apiary->id(), 'status' => 'active']);
      $hive->save();
      HiveInsight::create([
        'apiary' => $this->apiary->id(),
        'hive' => $hive->id(),
        'scope' => 'hive',
        'verdict' => $verdict,
        'recommendation' => 'Test',
        'signals' => '- Test',
        'generated' => \Drupal::time()->getRequestTime(),
      ])->save();

      $tile = $this->builder()->buildHiveStatTile($hive);
      $this->assertEquals($expected_variant, $tile['nexus_ai_insight']['sublabel_variant'], "verdict $verdict");
    }
  }

  /**
   * Tests the Hive stat tile is absent when there's no insight to summarise.
   */
  public function testHiveStatTileAbsentWhenNoInsight(): void {
    $this->assertSame([], $this->builder()->buildHiveStatTile($this->hive));
  }

  /**
   * Tests the Apiary stat tile descriptor shows an apiary-scoped verdict.
   */
  public function testApiaryStatTileShowsVerdict(): void {
    HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'verdict' => 'all_clear',
      'recommendation' => 'Apiary looks healthy overall',
      'signals' => '- All monitored hives reporting normally',
      'generated' => \Drupal::time()->getRequestTime(),
    ])->save();

    $tile = $this->builder()->buildApiaryStatTile($this->apiary);
    $this->assertArrayHasKey('nexus_ai_insight', $tile);
    $this->assertEquals('All clear', $tile['nexus_ai_insight']['value']);
  }

  /**
   * Tests hook_hivelog_hive_stat_tiles() is actually wired up end-to-end.
   *
   * Mirrors testPanelRendersLatestInsight()'s own "the hook fires, not
   * just the service in isolation" reasoning, applied to the new task
   * 0110 hook.
   */
  public function testHiveStatTilesHookIsDispatchedByModuleHandler(): void {
    $this->createInsight(['verdict' => 'act_now']);

    $tiles = \Drupal::moduleHandler()->invokeAll('hivelog_hive_stat_tiles', [$this->hive]);
    $this->assertArrayHasKey('nexus_ai_insight', $tiles);
  }

  /**
   * Tests hook_hivelog_hive_insights_panels() is actually wired up.
   *
   * Task 0111 moved the full AI Insight panel off
   * hook_hivelog_hive_view_panels() (the main Hive canonical page) onto
   * this dedicated-Insights-page hook instead — the apiary-scoped panel
   * still uses the original hook
   * (testApiaryViewPanelsHookIsDispatchedByModuleHandler()).
   */
  public function testHiveInsightsPanelsHookIsDispatchedByModuleHandler(): void {
    $this->createInsight();

    $panels = \Drupal::moduleHandler()->invokeAll('hivelog_hive_insights_panels', [$this->hive]);
    $this->assertArrayHasKey('nexus_hive_insight', $panels);
  }

  /**
   * Tests hook_hivelog_apiary_view_panels() is still wired up for apiary.
   *
   * Unlike the hive-scoped panel above, the apiary-scoped AI Insight
   * panel did NOT move (task 0111 only decluttered the Hive canonical
   * page).
   */
  public function testApiaryViewPanelsHookIsDispatchedByModuleHandler(): void {
    HiveInsight::create([
      'apiary' => $this->apiary->id(),
      'scope' => 'apiary',
      'verdict' => 'all_clear',
      'recommendation' => 'Apiary looks healthy overall',
      'signals' => '- All monitored hives reporting normally',
      'generated' => \Drupal::time()->getRequestTime(),
    ])->save();

    $panels = \Drupal::moduleHandler()->invokeAll('hivelog_apiary_view_panels', [$this->apiary]);
    $this->assertArrayHasKey('nexus_hive_insight', $panels);
  }

}
