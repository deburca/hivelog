<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that every harvest carries its own apiary, hive and date.
 *
 * Task 0285, ADR-0110: a harvest reported with an action log is stamped
 * from its log, existing ones are backfilled by hivelog_update_10040(), and
 * the report reads a harvest's own fields.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HarvestStampTest extends KernelTestBase {

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
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A hive in the test apiary.
   */
  protected Hive $hive;

  /**
   * A product of the test apiary, priced at 10 a kg.
   */
  protected Product $honey;

  /**
   * A hive-scoped calendar action.
   */
  protected CalendarAction $hiveAction;

  /**
   * An apiary-scoped calendar action.
   */
  protected CalendarAction $apiaryAction;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('hive_reminder');
    $this->installEntityType('hive_reminder');
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'hive_component',
      'queen', 'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'hive_action_log', 'apiary_action_log', 'inventory_item', 'inventory_purchase',
      'inventory_usage', 'product', 'harvest_yield',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    // The first user is uid 1, so access passes.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $this->apiary = Apiary::create(['name' => 'Home Apiary']);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'Hive One', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();
    $this->honey = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Heather honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $this->honey->save();
    $this->hiveAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Take the heather honey',
      'week_start' => 36,
      'scope' => 'hive',
    ]);
    $this->hiveAction->save();
    $this->apiaryAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Extract for the whole yard',
      'week_start' => 37,
      'scope' => 'apiary',
    ]);
    $this->apiaryAction->save();
  }

  /**
   * Creates a done hive action log.
   */
  protected function hiveLog(int $year, ?int $week = NULL): HiveActionLog {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->hiveAction->id(),
      'year' => $year,
      'status' => 'done',
      'week_completed' => $week,
    ]);
    $log->save();
    return $log;
  }

  /**
   * Creates a done apiary action log.
   */
  protected function apiaryLog(int $year, ?int $week = NULL): ApiaryActionLog {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->apiaryAction->id(),
      'year' => $year,
      'status' => 'done',
      'week_completed' => $week,
    ]);
    $log->save();
    return $log;
  }

  /**
   * Creates a harvest of the test product from the given values.
   */
  protected function harvest(array $values, float $quantity = 5): HarvestYield {
    $harvest = HarvestYield::create($values + ['product' => $this->honey->id(), 'quantity' => $quantity]);
    $harvest->save();
    return $harvest;
  }

  /**
   * The potential income the report gives the test apiary for a year.
   */
  protected function income(Apiary $apiary, int $year): float {
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(InventoryReportController::class);
    return (float) $controller->computeApiaryYearTotals($apiary, $year)['income_total'];
  }

  /**
   * A harvest reported with a hive log takes its hive, apiary and date.
   */
  public function testHiveLogHarvestIsStampedFromItsLog(): void {
    $harvest = $this->harvest(['hive_action_log' => $this->hiveLog((int) date('Y'), 36)->id()]);

    $this->assertFalse($harvest->isDirect());
    $this->assertEquals($this->hive->id(), $harvest->get('hive')->target_id);
    $this->assertEquals($this->apiary->id(), $harvest->get('apiary')->target_id);
    // A log of this year: today, whatever week it names.
    $this->assertSame(date('Y-m-d'), $harvest->get('harvest_date')->value);
  }

  /**
   * A harvest reported with an apiary log takes its apiary and names no hive.
   */
  public function testApiaryLogHarvestNamesNoHive(): void {
    $harvest = $this->harvest(['apiary_action_log' => $this->apiaryLog((int) date('Y'))->id()]);

    $this->assertEquals($this->apiary->id(), $harvest->get('apiary')->target_id);
    $this->assertTrue($harvest->get('hive')->isEmpty());
    $this->assertSame(date('Y-m-d'), $harvest->get('harvest_date')->value);
  }

  /**
   * A log for another year gives a date inside that year.
   */
  public function testLogOfAnotherYearDatesTheHarvestInThatYear(): void {
    $last_year = (int) date('Y') - 1;
    $with_week = $this->harvest(['hive_action_log' => $this->hiveLog($last_year, 36)->id()]);
    $without = $this->harvest(['apiary_action_log' => $this->apiaryLog($last_year)->id()]);

    $this->assertSame(HarvestYield::dateFromLogWeek($last_year, 36), $with_week->get('harvest_date')->value);
    $this->assertStringStartsWith((string) $last_year, $with_week->get('harvest_date')->value);
    $this->assertSame($last_year . '-07-01', $without->get('harvest_date')->value);
  }

  /**
   * The week-to-date rule, including the week that starts in December.
   */
  public function testDateFromLogWeek(): void {
    // 2026-W27 starts on Monday 29 June.
    $this->assertSame('2026-06-29', HarvestYield::dateFromLogWeek(2026, 27));
    // 2026-W01 starts on Monday 29 December 2025: kept inside 2026.
    $this->assertSame('2026-01-01', HarvestYield::dateFromLogWeek(2026, 1));
    $this->assertSame('2026-07-01', HarvestYield::dateFromLogWeek(2026, NULL));
    $this->assertSame('2026-07-01', HarvestYield::dateFromLogWeek(2026, 0));
  }

  /**
   * The report counts logged and direct harvests alike, each once.
   */
  public function testReportCountsEveryHarvestOnce(): void {
    $year = (int) date('Y');
    $this->harvest(['hive_action_log' => $this->hiveLog($year, 36)->id()], 12);
    $this->harvest(['apiary_action_log' => $this->apiaryLog($year, 37)->id()], 3);
    $this->harvest(['hive' => $this->hive->id(), 'harvest_date' => $year . '-06-20'], 2);
    $this->harvest(['hive_action_log' => $this->hiveLog($year - 1, 36)->id()], 100);

    // 12 + 3 + 2 kg at 10 this year; 100 kg last year.
    $this->assertEqualsWithDelta(170.0, $this->income($this->apiary, $year), 0.001);
    $this->assertEqualsWithDelta(1000.0, $this->income($this->apiary, $year - 1), 0.001);
  }

  /**
   * The update hook fills in harvests written before they had these fields.
   */
  public function testUpdateHookBackfillsOldHarvests(): void {
    $year = (int) date('Y');
    $from_hive = $this->harvest(['hive_action_log' => $this->hiveLog($year, 36)->id()], 12);
    $for_apiary = $this->harvest(['apiary_action_log' => $this->apiaryLog($year - 1)->id()], 3);
    $direct = $this->harvest(['hive' => $this->hive->id(), 'harvest_date' => $year . '-06-20'], 2);

    // Put the two logged rows back to how they were stored before.
    $database = \Drupal::database();
    $database->update('hivelog_harvest_yield')
      ->fields(['apiary' => NULL, 'hive' => NULL, 'harvest_date' => NULL, 'changed' => 1000])
      ->condition('id', [$from_hive->id(), $for_apiary->id()], 'IN')
      ->execute();
    $storage = \Drupal::entityTypeManager()->getStorage('harvest_yield');
    $storage->resetCache();
    // Without their own fields the report no longer sees them.
    $this->assertEqualsWithDelta(20.0, $this->income($this->apiary, $year), 0.001);

    \Drupal::moduleHandler()->loadInclude('hivelog', 'install');
    $sandbox = [];
    $message = (string) hivelog_update_10040($sandbox);
    $this->assertStringContainsString('2 calendar harvest', $message);

    $from_hive = HarvestYield::load($from_hive->id());
    $this->assertEquals($this->apiary->id(), $from_hive->get('apiary')->target_id);
    $this->assertEquals($this->hive->id(), $from_hive->get('hive')->target_id);
    $this->assertSame(HarvestYield::dateFromLogWeek($year, 36), $from_hive->get('harvest_date')->value);
    // A direct update: the row's changed time is not touched.
    $this->assertEquals(1000, $from_hive->get('changed')->value);

    $for_apiary = HarvestYield::load($for_apiary->id());
    $this->assertEquals($this->apiary->id(), $for_apiary->get('apiary')->target_id);
    $this->assertTrue($for_apiary->get('hive')->isEmpty());
    $this->assertSame(($year - 1) . '-07-01', $for_apiary->get('harvest_date')->value);

    // The direct harvest is left as it was, and each year's income is back.
    $this->assertSame($year . '-06-20', HarvestYield::load($direct->id())->get('harvest_date')->value);
    $this->assertEqualsWithDelta(140.0, $this->income($this->apiary, $year), 0.001);
    $this->assertEqualsWithDelta(30.0, $this->income($this->apiary, $year - 1), 0.001);

    // Running it again writes nothing.
    $this->assertStringContainsString('0 calendar harvest', (string) hivelog_update_10040($sandbox));
  }

  /**
   * A harvest stays in the apiary its log was written in when the hive moves.
   */
  public function testBackfillUsesTheLogsApiaryNotTheHivesApiaryNow(): void {
    $year = (int) date('Y');
    $harvest = $this->harvest(['hive_action_log' => $this->hiveLog($year, 36)->id()], 12);
    $elsewhere = Apiary::create(['name' => 'New Apiary']);
    $elsewhere->save();

    // The hive has since moved, and the harvest is an old-style row.
    $database = \Drupal::database();
    $database->update('hivelog_hive')->fields(['apiary' => $elsewhere->id()])->condition('id', $this->hive->id())->execute();
    $database->update('hivelog_harvest_yield')
      ->fields(['apiary' => NULL, 'hive' => NULL, 'harvest_date' => NULL])
      ->condition('id', $harvest->id())
      ->execute();
    \Drupal::entityTypeManager()->getStorage('harvest_yield')->resetCache();
    \Drupal::entityTypeManager()->getStorage('hive')->resetCache();

    \Drupal::moduleHandler()->loadInclude('hivelog', 'install');
    $sandbox = [];
    hivelog_update_10040($sandbox);

    $harvest = HarvestYield::load($harvest->id());
    $this->assertEquals($this->apiary->id(), $harvest->get('apiary')->target_id);
    $this->assertEqualsWithDelta(120.0, $this->income($this->apiary, $year), 0.001);
    $this->assertEqualsWithDelta(0.0, $this->income($elsewhere, $year), 0.001);
  }

}
