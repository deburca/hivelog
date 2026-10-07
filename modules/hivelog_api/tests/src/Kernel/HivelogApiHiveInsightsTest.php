<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\nexus\Entity\HiveInsight;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the hive insights an app token gets from nexus (task 0221).
 *
 * Until then the field-app role could not view insights, so the per-hive insight endpoint
 * always answered "nothing" and the app never showed one. The role here is the real one the
 * module installs, with nexus enabled, so a pass shows the app's own permission set is enough.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiHiveInsightsTest extends HivelogApiKernelTestBase {

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
    'serialization',
    'jsonapi',
    'basic_auth',
    'consumers',
    'simple_oauth',
    'key',
    'hivelog',
    'collective',
    'nexus',
    'hivelog_api',
    'hivelog_api_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('hive_insight');
  }

  /**
   * Writes a hive's insight, generated this many seconds ago.
   */
  protected function insight($apiary, $hive, string $verdict, int $age = 60): HiveInsight {
    $insight = HiveInsight::create([
      'scope' => 'hive',
      'apiary' => $apiary->id(),
      'hive' => $hive->id(),
      'verdict' => $verdict,
      'recommendation' => 'Do the thing.',
      'signals' => "- A signal\n- Another",
      'confidence' => 'medium',
      'generated' => time() - $age,
    ]);
    $insight->save();
    return $insight;
  }

  /**
   * Tests the owner's token gets the hive's insight, and the apiary's verdicts in one answer.
   */
  public function testTheOwnerGetsTheInsightAndTheApiaryVerdicts(): void {
    $apiary = $this->fixtures['my_apiary'];
    $hive = $this->fixtures['my_hive'];
    $apiary->set('ai_insights_enabled', TRUE)->save();
    $this->insight($apiary, $hive, 'act_now');

    $one = $this->api('GET', '/hivelog/api/v1/computed/hive/' . $hive->uuid() . '/insight', NULL, $this->me);
    $this->assertSame(200, $one['status'], $one['raw']);
    $this->assertSame('act_now', $one['body']['data']['verdict']);
    $this->assertFalse($one['body']['data']['stale']);

    $all = $this->api('GET', '/hivelog/api/v1/computed/apiary/' . $apiary->uuid() . '/hive-insights', NULL, $this->me);
    $this->assertSame(200, $all['status'], $all['raw']);
    $this->assertSame([$hive->uuid()], array_column($all['body']['data'], 'hive'));
    $this->assertSame('act_now', $all['body']['data'][0]['verdict']);
  }

  /**
   * Tests an old insight is reported stale, so the app does not tint the badge as if it were current.
   */
  public function testAnOldInsightIsStale(): void {
    $apiary = $this->fixtures['my_apiary'];
    $apiary->set('ai_insights_enabled', TRUE)->save();
    $this->insight($apiary, $this->fixtures['my_hive'], 'all_clear', 3 * 24 * 3600);

    $all = $this->api('GET', '/hivelog/api/v1/computed/apiary/' . $apiary->uuid() . '/hive-insights', NULL, $this->me);
    $this->assertTrue($all['body']['data'][0]['stale']);
  }

  /**
   * Tests an apiary that has not opted in to AI insights shows none, whatever is stored.
   */
  public function testNoInsightsWithoutOptIn(): void {
    $apiary = $this->fixtures['my_apiary'];
    $this->insight($apiary, $this->fixtures['my_hive'], 'all_clear');

    $all = $this->api('GET', '/hivelog/api/v1/computed/apiary/' . $apiary->uuid() . '/hive-insights', NULL, $this->me);
    $this->assertSame([], $all['body']['data']);
  }

  /**
   * Tests another beekeeper's insights are never served.
   */
  public function testAnotherBeekeepersInsightsAreNotServed(): void {
    $theirs = $this->fixtures['their_apiary'];
    $theirs->set('ai_insights_enabled', TRUE)->save();
    $this->insight($theirs, $this->fixtures['their_hive'], 'act_now');

    $this->assertSame(403, $this->api('GET', '/hivelog/api/v1/computed/apiary/' . $theirs->uuid() . '/hive-insights', NULL, $this->me)['status']);
    $this->assertSame(403, $this->api('GET', '/hivelog/api/v1/computed/hive/' . $this->fixtures['their_hive']->uuid() . '/insight', NULL, $this->me)['status']);
    $mine = $this->api('GET', '/hivelog/api/v1/computed/apiary/' . $this->fixtures['my_apiary']->uuid() . '/hive-insights', NULL, $this->me);
    $this->assertSame([], $mine['body']['data']);
  }

}
