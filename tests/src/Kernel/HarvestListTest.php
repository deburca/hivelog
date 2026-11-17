<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\HivelogHarvestTotals;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the one list of harvests and the totals that count them all.
 *
 * Task 0286, ADR-0110: a harvest reported with the calendar and one recorded
 * directly are listed together, with their source, and counted together.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HarvestListTest extends KernelTestBase {

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
      'queen', 'queen_observation', 'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
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
   * Renders a controller's page for an entity and returns its build.
   */
  protected function page(string $controller_class, Hive|Apiary $entity): array {
    $request = Request::create('/hivelog/' . $entity->getEntityTypeId() . '/' . $entity->id());
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    return \Drupal::service('class_resolver')->getInstanceFromDefinition($controller_class)->view($entity);
  }

  /**
   * One harvest from the calendar, one recorded directly, one last year.
   *
   * @return array{logged: \Drupal\hivelog\Entity\HarvestYield, direct: \Drupal\hivelog\Entity\HarvestYield, log: \Drupal\hivelog\Entity\HiveActionLog}
   *   The two harvests of this year and the log.
   */
  protected function mixedHarvests(): array {
    $year = (int) date('Y');
    $log = $this->hiveLog($year, 36);
    $logged = $this->harvest(['hive_action_log' => $log->id(), 'harvest_date' => $year . '-09-01'], 12);
    $direct = $this->harvest(['hive' => $this->hive->id(), 'harvest_date' => $year . '-06-20'], 2);
    $this->harvest(['hive' => $this->hive->id(), 'harvest_date' => ($year - 1) . '-06-20'], 40);
    return ['logged' => $logged, 'direct' => $direct, 'log' => $log];
  }

  /**
   * The hive page lists calendar and direct harvests together, with source.
   */
  public function testHivePageListsEveryHarvestWithItsSource(): void {
    $made = $this->mixedHarvests();
    $table = $this->page(HiveController::class, $this->hive)['harvests']['table']['#props'];

    $this->assertSame(['Date', 'Product', 'Quantity', 'Source', 'Notes', 'Operations'], $table['headers']);
    $this->assertCount(3, $table['rows']);
    [$logged, $direct] = array_column($table['rows'], 'cells');

    // Newest first: the calendar harvest of September.
    $this->assertSame('12 kg', $logged[2]);
    $this->assertStringContainsString('Calendar: Take the heather honey', $logged[3]);
    $this->assertStringContainsString('/hivelog/hive-action-log/' . $made['log']->id(), $logged[3]);
    // It is changed where it was reported, and not deleted from here.
    $this->assertStringContainsString('Edit in log', $logged[5]);
    $this->assertStringContainsString('/hivelog/hive-action-log/' . $made['log']->id() . '/edit', $logged[5]);
    $this->assertStringNotContainsString('/hivelog/harvest/', $logged[5]);

    $this->assertSame('2 kg', $direct[2]);
    $this->assertSame('Recorded directly', $direct[3]);
    $this->assertStringContainsString('/hivelog/harvest/' . $made['direct']->id() . '/edit', $direct[5]);
    $this->assertStringContainsString('/hivelog/harvest/' . $made['direct']->id() . '/delete', $direct[5]);
  }

  /**
   * The apiary page also lists a harvest reported for the whole apiary.
   */
  public function testApiaryPageListsApiaryWideCalendarHarvests(): void {
    $this->mixedHarvests();
    $log = $this->apiaryLog((int) date('Y'), 37);
    $this->harvest(['apiary_action_log' => $log->id(), 'harvest_date' => date('Y') . '-09-10'], 3);

    $table = $this->page(ApiaryController::class, $this->apiary)['harvests']['table']['#props'];
    $this->assertSame(['Date', 'Product', 'Quantity', 'Hive', 'Source', 'Notes', 'Operations'], $table['headers']);
    $this->assertCount(4, $table['rows']);
    $newest = $table['rows'][0]['cells'];
    $this->assertSame('3 kg', $newest[2]);
    $this->assertSame('', $newest[3], 'An apiary-wide harvest names no hive.');
    $this->assertStringContainsString('Calendar: Extract for the whole yard', $newest[4]);
  }

  /**
   * With no harvest the message does not mention the seasonal calendar.
   */
  public function testEmptyMessage(): void {
    $table = $this->page(HiveController::class, $this->hive)['harvests']['table']['#props'];
    $this->assertSame([], $table['rows']);
    $this->assertSame('No harvest has been recorded for this hive yet.', $table['empty_message']);
  }

  /**
   * The hive's totals and tile count calendar and direct harvests alike.
   */
  public function testHiveTotalsCountEveryHarvest(): void {
    $this->mixedHarvests();
    $year = (int) date('Y');

    $totals = HivelogHarvestTotals::perProduct(\Drupal::entityTypeManager(), $this->hive, $year);
    $this->assertSame(14.0, reset($totals)['quantity']);

    $all = HivelogHarvestTotals::perProduct(\Drupal::entityTypeManager(), $this->hive);
    $this->assertSame([(string) $year => 14.0, (string) ($year - 1) => 40.0], reset($all)['by_year']);

    $tile = \Drupal::service('hivelog.stat_tile_builder')->coreTilesForHive($this->hive)['hivelog_harvest'];
    $this->assertSame('14 kg', $tile['value']);
  }

  /**
   * The Products table says what was harvested of each product this year.
   */
  public function testProductsTableShowsHarvestedThisYear(): void {
    $this->mixedHarvests();
    $this->harvest(['apiary_action_log' => $this->apiaryLog((int) date('Y'), 37)->id()], 3);

    $table = $this->page(ApiaryController::class, $this->apiary)['products_table']['#props'];
    $this->assertContains('Harvested this year', $table['headers']);
    $column = array_search('Harvested this year', $table['headers'], TRUE);
    $harvested = [];
    foreach ($table['rows'] as $row) {
      $harvested[strip_tags((string) $row['cells'][0])] = $row['cells'][$column];
    }
    // 12 from the hive's calendar harvest, 2 direct, 3 for the whole apiary;
    // last year's 40 is not this year's. Nothing was harvested of the
    // apiary's starter Beeswax.
    $this->assertSame('17 kg', $harvested['Heather honey']);
    $this->assertSame('', $harvested['Beeswax']);
  }

}
