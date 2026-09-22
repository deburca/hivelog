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

}
