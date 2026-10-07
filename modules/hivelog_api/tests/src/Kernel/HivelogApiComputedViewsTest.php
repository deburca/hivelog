<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog_api\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the computed-view endpoints: alerts, stat tiles and the insight.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogApiComputedViewsTest extends HivelogApiKernelTestBase {

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
    'hivelog',
    'hivelog_api',
    'hivelog_api_test',
  ];

  /**
   * Adds a hive-scoped action due this week to an apiary.
   */
  protected function dueAction($apiary, string $title): CalendarAction {
    $week = (int) date('W');
    $action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => $title,
      'description' => 'Do the thing.',
      'scope' => 'hive',
      'week_start' => $week,
      'week_end' => $week,
    ]);
    $action->save();
    return $action;
  }

  /**
   * Tests the alerts are the dashboard's, serialised, and only the user's own.
   */
  public function testAlertsAreTheSharedCollectorsAndOwnerScoped(): void {
    $mine = $this->dueAction($this->fixtures['my_apiary'], 'Mine due now');
    $theirs = $this->dueAction($this->fixtures['their_apiary'], 'Theirs due now');

    $response = $this->api('GET', '/hivelog/api/v1/computed/alerts', NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);

    $rows = array_column($response['body']['data'], NULL, 'title');
    $this->assertArrayHasKey('Mine due now', $rows);
    $this->assertArrayNotHasKey('Theirs due now', $rows, 'Another beekeeper\'s alert is not served');

    $row = $rows['Mine due now'];
    $this->assertSame('warning', $row['severity']);
    $this->assertSame('calendar_action', $row['kind']);
    $this->assertSame($this->fixtures['my_apiary']->uuid(), $row['apiary']['id']);
    $this->assertSame($this->fixtures['my_hive']->uuid(), $row['hive']['id']);
    $this->assertSame(['id' => $mine->uuid(), 'scope' => 'hive'], $row['calendar_action']);
    $this->assertSame(
      ['id' => $theirs->uuid(), 'scope' => 'hive'],
      array_column($this->api('GET', '/hivelog/api/v1/computed/alerts', NULL, $this->them)['body']['data'], 'calendar_action', 'title')['Theirs due now']
    );
    $this->assertSame(count($response['body']['data']), $response['body']['meta']['count']);

    // The very same rows the dashboard is built from.
    \Drupal::currentUser()->setAccount($this->me);
    $apiaries = \Drupal::service('hivelog.calendar_checklist_builder')->viewableApiaries($this->me);
    $collected = \Drupal::service('hivelog.alert_collector')->collect(
      $apiaries,
      (int) date('Y'),
      (int) date('W'),
      new CacheableMetadata()
    )['alerts'];
    $this->assertEquals(
      array_map(fn($a) => (string) $a['title'] . '|' . (string) $a['chip'], $collected),
      array_map(fn($a) => $a['title'] . '|' . $a['chip'], $response['body']['data'])
    );
  }

  /**
   * Tests an unauthenticated request gets no alerts.
   */
  public function testAlertsNeedSignIn(): void {
    $this->assertSame(401, $this->api('GET', '/hivelog/api/v1/computed/alerts')['status']);
  }

  /**
   * Tests the alerts response varies per user and does not outlive the day.
   */
  public function testAlertsAreCacheablePerUser(): void {
    \Drupal::currentUser()->setAccount($this->me);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition('\Drupal\hivelog_api\Controller\ComputedViewsController');
    $metadata = $controller->alerts()->getCacheableMetadata();

    $this->assertContains('user', $metadata->getCacheContexts());
    $this->assertContains('apiary_list', $metadata->getCacheTags());
    $this->assertGreaterThan(0, $metadata->getCacheMaxAge());
    $this->assertLessThanOrEqual(86400, $metadata->getCacheMaxAge());
  }

  /**
   * Tests stat tiles come from the shared builder, in weight order.
   */
  public function testHiveStatTilesAreServedInWeightOrder(): void {
    $uuid = $this->fixtures['my_hive']->uuid();
    $response = $this->api('GET', "/hivelog/api/v1/computed/hive/$uuid/stat-tiles", NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);

    $this->assertSame(['first_tile', 'test_tile'], array_column($response['body']['data'], 'key'));
    $tile = $response['body']['data'][1];
    $this->assertSame('42', $tile['value']);
    $this->assertSame('Answer', $tile['label']);
    $this->assertSame('and counting', $tile['sublabel']);
    $this->assertSame('warning', $tile['sublabel_variant']);
    $this->assertStringStartsWith('http://localhost/', $tile['web_url']);
    $this->assertNull($response['body']['meta']['empty_weight_kg'], 'No components, no tare weight');
  }

  /**
   * Tests the hive's empty weight is served.
   */
  public function testHiveEmptyWeightIsServed(): void {
    $apiary = $this->fixtures['my_apiary'];
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Brood box',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 10,
      'weight_kg' => 4.25,
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 5,
      'unit_price' => 20,
    ])->save();
    HiveComponent::create([
      'hive' => $this->fixtures['my_hive']->id(),
      'item' => $item->id(),
      'quantity' => 2,
    ])->save();

    $uuid = $this->fixtures['my_hive']->uuid();
    $response = $this->api('GET', "/hivelog/api/v1/computed/hive/$uuid/stat-tiles", NULL, $this->me);
    $this->assertEquals(8.5, $response['body']['meta']['empty_weight_kg']);
  }

  /**
   * Tests the insight is served from whatever module provides it.
   */
  public function testHiveInsightIsServed(): void {
    $uuid = $this->fixtures['my_hive']->uuid();
    $response = $this->api('GET', "/hivelog/api/v1/computed/hive/$uuid/insight", NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);
    $this->assertSame('inspect_soon', $response['body']['data']['verdict']);
    $this->assertSame(['Weight is flat'], $response['body']['data']['signals']);
  }

  /**
   * Tests an apiary's hive verdicts come in one answer, one entry per hive that has one (task 0221).
   */
  public function testApiaryHiveInsightsAreServedInOneAnswer(): void {
    $second = Hive::create([
      'name' => 'second hive',
      'apiary' => $this->fixtures['my_apiary']->id(),
      'status' => 'active',
      'uid' => $this->me->id(),
    ]);
    $second->save();

    $uuid = $this->fixtures['my_apiary']->uuid();
    $response = $this->api('GET', "/hivelog/api/v1/computed/apiary/$uuid/hive-insights", NULL, $this->me);
    $this->assertSame(200, $response['status'], $response['raw']);

    $rows = array_column($response['body']['data'], NULL, 'hive');
    $this->assertEqualsCanonicalizing(
      [$this->fixtures['my_hive']->uuid(), $second->uuid()],
      array_keys($rows),
    );
    $row = $rows[$this->fixtures['my_hive']->uuid()];
    $this->assertSame('inspect_soon', $row['verdict']);
    $this->assertSame('Inspect soon', $row['verdict_label']);
    $this->assertFalse($row['stale']);
    $this->assertSame('2026-10-01T08:00:00+00:00', $row['generated']);
    $this->assertSame(2, $response['body']['meta']['count']);
    // A badge needs the verdict, not the advice: that stays on the per-hive endpoint.
    $this->assertArrayNotHasKey('recommendation', $row);
  }

  /**
   * Tests another beekeeper's apiary is refused, its hives never listed, and an unknown one is missing.
   */
  public function testApiaryHiveInsightsRefuseForeignAndUnknownApiaries(): void {
    $theirs = $this->fixtures['their_apiary']->uuid();
    $path = "/hivelog/api/v1/computed/apiary/$theirs/hive-insights";
    $this->assertSame(403, $this->api('GET', $path, NULL, $this->me)['status']);
    $mine = $this->api('GET', $path, NULL, $this->them);
    $this->assertSame(200, $mine['status']);
    $this->assertSame([$this->fixtures['their_hive']->uuid()], array_column($mine['body']['data'], 'hive'));
    $this->assertSame(404, $this->api('GET', '/hivelog/api/v1/computed/apiary/' . \Drupal::service('uuid')->generate() . '/hive-insights', NULL, $this->me)['status']);
    $this->assertSame(401, $this->api('GET', $path)['status']);
  }

  /**
   * Tests another beekeeper's hive is refused, and an unknown one is missing.
   */
  public function testHiveEndpointsRefuseForeignAndUnknownHives(): void {
    $theirs = $this->fixtures['their_hive']->uuid();
    foreach (['stat-tiles', 'insight'] as $endpoint) {
      $this->assertSame(403, $this->api('GET', "/hivelog/api/v1/computed/hive/$theirs/$endpoint", NULL, $this->me)['status'], $endpoint);
      $this->assertSame(200, $this->api('GET', "/hivelog/api/v1/computed/hive/$theirs/$endpoint", NULL, $this->them)['status'], $endpoint);
      $this->assertSame(404, $this->api('GET', '/hivelog/api/v1/computed/hive/' . \Drupal::service('uuid')->generate() . "/$endpoint", NULL, $this->me)['status'], $endpoint);
      $this->assertSame(401, $this->api('GET', "/hivelog/api/v1/computed/hive/$theirs/$endpoint")['status'], $endpoint);
    }
  }

  /**
   * Tests the endpoints are read-only.
   */
  public function testComputedEndpointsAreReadOnly(): void {
    $uuid = $this->fixtures['my_hive']->uuid();
    $paths = [
      '/hivelog/api/v1/computed/alerts',
      "/hivelog/api/v1/computed/hive/$uuid/stat-tiles",
      "/hivelog/api/v1/computed/hive/$uuid/insight",
      '/hivelog/api/v1/computed/apiary/' . $this->fixtures['my_apiary']->uuid() . '/hive-insights',
    ];
    foreach ($paths as $path) {
      $this->assertSame(405, $this->api('POST', $path, [], $this->me)['status'], $path);
    }
  }

}
