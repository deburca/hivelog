<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the apiary page's core activity tiles (task 0263).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryActivityTilesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'datetime', 'options', 'file', 'image', 'geofield', 'hivelog',
  ];

  /**
   * The apiary.
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'queen',
      'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'inventory_item', 'inventory_purchase', 'inventory_usage', 'product', 'harvest_yield',
      'hive_action_log', 'apiary_action_log',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));
    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests the four tiles and that they count this apiary only.
   */
  public function testTilesCountThisApiaryOnly(): void {
    $other = Apiary::create(['name' => 'Other']);
    $other->save();
    $hive = Hive::create(['name' => 'H1', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    Hive::create(['name' => 'H2', 'apiary' => $this->apiary->id(), 'status' => 'dead'])->save();
    $elsewhere = Hive::create(['name' => 'H3', 'apiary' => $other->id(), 'status' => 'active']);
    $elsewhere->save();
    foreach ([$hive, $elsewhere] as $h) {
      HiveInspection::create(['hive' => $h->id(), 'inspection_date' => date('Y-m-d')])->save();
    }

    $tiles = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForApiary($this->apiary);
    $this->assertSame('1', $tiles['hivelog_hives']['value']);
    $this->assertStringContainsString('#hives', $tiles['hivelog_hives']['url']->toString());
    // A new apiary starts from the default calendar, so tasks are open; the
    // tile must agree with the pass the dashboard uses.
    $seasonal = \Drupal::service('hivelog.alert_collector')
      ->collectSeasonalAlerts([$this->apiary->id() => $this->apiary], (int) date('Y'), (int) date('W'), new CacheableMetadata());
    $this->assertGreaterThan(0, $seasonal['open_total']);
    $this->assertSame((string) $seasonal['open_total'], $tiles['hivelog_open_tasks']['value']);
    $this->assertSame('1', $tiles['hivelog_inspections_month']['value']);
    $this->assertSame('0', $tiles['hivelog_net_ytd']['value']);
    $this->assertLessThan(0, max(array_column($tiles, 'weight')));
  }

  /**
   * Tests the inspection count helper the dashboard shares.
   */
  public function testInspectionCountIsShared(): void {
    $hive = Hive::create(['name' => 'H1', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $hive->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => date('Y-m-d')])->save();
    $builder = \Drupal::service('hivelog.stat_tile_builder');
    $this->assertSame(1, $builder->inspectionsThisMonth([$this->apiary->id()]));
    $this->assertSame(0, $builder->inspectionsThisMonth([]));
  }

  /**
   * Tests the page row carries the tiles and the cache metadata.
   */
  public function testPageRow(): void {
    $row = \Drupal::service('hivelog.stat_tile_builder')->buildForApiary($this->apiary);
    $this->assertArrayHasKey('hivelog_hives', $row);
    $this->assertContains('hive_list', $row['#cache']['tags']);
    $this->assertContains('user.permissions', $row['#cache']['contexts']);
  }

  /**
   * Tests a user without finance access gets no Net YTD tile.
   */
  public function testNoNetTileWithoutFinanceAccess(): void {
    $user = User::create(['name' => 'plain', 'mail' => 'plain@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);
    $tiles = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForApiary($this->apiary);
    $this->assertArrayNotHasKey('hivelog_net_ytd', $tiles);
    $this->assertArrayHasKey('hivelog_hives', $tiles);
  }

}
