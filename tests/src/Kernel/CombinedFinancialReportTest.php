<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests InventoryReportController::combinedReport() (task 0059).
 *
 * The combined report runs the same per-apiary aggregation as
 * costReport() once for every apiary the current user may view and sums
 * it: one summary row per apiary plus an "All apiaries" total, and one
 * 5-year trend. It is the dashboard "Net YTD" tile's target when more
 * than one apiary is visible. Durable-item depreciation with
 * `useful_life_years = 1` gives deterministic single-year totals without
 * a full action-log flow (mirroring InventoryCostReportTest's trend
 * test).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CombinedFinancialReportTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Makes an "administer hivelog" user the current account.
   */
  private function makeAdmin(): User {
    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);
    return $user;
  }

  /**
   * Renders combinedReport() to an HTML string for the given year.
   */
  private function renderCombined(int $year): string {
    $request = Request::create('/hivelog/apiaries/financial-report', 'GET', ['year' => $year]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InventoryReportController::class);
    return (string) \Drupal::service('renderer')->renderInIsolation($controller->combinedReport());
  }

  /**
   * Adds a durable item to an apiary that depreciates fully in one year.
   */
  private function addOneYearDurable(Apiary $apiary, string $name, int $year, float $price): void {
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => $name,
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 1,
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => $year . '-01-01',
      'quantity' => 1,
      'unit_price' => $price,
    ])->save();
  }

  /**
   * The summary sums every viewable apiary into an "All apiaries" row.
   */
  public function testSumsNetAcrossApiaries(): void {
    $this->makeAdmin();
    $year = (int) date('Y');

    $a = Apiary::create(['name' => 'Ravnholt Home']);
    $a->save();
    $b = Apiary::create(['name' => 'Sondermarken']);
    $b->save();

    // A: 500 over 1 year -> net -500. B: 300 over 1 year -> net -300.
    $this->addOneYearDurable($a, 'Extractor', $year, 500);
    $this->addOneYearDurable($b, 'Wax press', $year, 300);

    $html = $this->renderCombined($year);

    $this->assertStringContainsString('Ravnholt Home', $html);
    $this->assertStringContainsString('Sondermarken', $html);
    $this->assertStringContainsString('-500.00', $html);
    $this->assertStringContainsString('-300.00', $html);
    // Combined total row.
    $this->assertStringContainsString('All apiaries', $html);
    $this->assertStringContainsString('-800.00', $html);
  }

  /**
   * The summary marks its label column and tags the total row for styling.
   */
  public function testSummaryTableAlignmentHooks(): void {
    $this->makeAdmin();
    $year = (int) date('Y');
    $apiary = Apiary::create(['name' => 'Only One']);
    $apiary->save();
    $this->addOneYearDurable($apiary, 'Extractor', $year, 100);

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->combinedReport();

    // The figure columns get their leading label column left-aligned via
    // this modifier (see css/hivelog.tables.css).
    $this->assertContains('hivelog-inventory-report-table--labelled', $build['summary']['#attributes']['class']);

    // The "All apiaries" row is plain cells (not header => TRUE) with a
    // class carrying the emphasis, so its typeface matches the data rows.
    $total = end($build['summary']['#rows']);
    $this->assertContains('hivelog-inventory-report-total', $total['class']);
    $this->assertSame('All apiaries', (string) $total['data'][0]);
    $this->assertSame('-100.00', $total['data'][5]);
  }

  /**
   * The year selector sits in a right-floated heading row.
   */
  public function testYearSelectorSitsInFloatedHeadingRow(): void {
    $this->makeAdmin();
    Apiary::create(['name' => 'A'])->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InventoryReportController::class);
    $selector = $controller->combinedReport()['year_selector'];

    $this->assertContains('hivelog-list-heading', $selector['#attributes']['class']);
    $this->assertContains('hivelog-list-heading__action', $selector['actions']['#attributes']['class']);
    $this->assertSame('hivelog:button-group', $selector['actions']['group']['#component']);
    $this->assertContains('hivelog/buttons', $selector['#attached']['library']);
  }

  /**
   * Each apiary row links to that apiary's own per-item report.
   */
  public function testApiaryRowsLinkToPerApiaryReport(): void {
    $this->makeAdmin();
    $year = (int) date('Y');

    $apiary = Apiary::create(['name' => 'Only One']);
    $apiary->save();
    $this->addOneYearDurable($apiary, 'Extractor', $year, 100);

    $html = $this->renderCombined($year);

    $this->assertStringContainsString(
      'href="/hivelog/apiary/' . $apiary->id() . '/inventory/cost-report?year=' . $year . '"',
      $html,
    );
  }

  /**
   * With no viewable apiaries the summary shows its empty state.
   */
  public function testEmptyStateWithNoApiaries(): void {
    $this->makeAdmin();

    $html = $this->renderCombined((int) date('Y'));

    $this->assertStringContainsString('No apiaries to report on.', $html);
  }

  /**
   * The trend table always covers the current year plus the five before.
   */
  public function testTrendCoversSixYears(): void {
    $this->makeAdmin();
    $current_year = (int) date('Y');

    $apiary = Apiary::create(['name' => 'Trend Apiary']);
    $apiary->save();
    // Activity four years ago only: -100 that row, zeros elsewhere.
    $this->addOneYearDurable($apiary, 'Old kit', $current_year - 4, 100);

    $request = Request::create('/hivelog/apiaries/financial-report', 'GET', ['year' => $current_year]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InventoryReportController::class);
    $build = $controller->combinedReport();

    $trend_rows = $build['trend']['table']['#rows'];
    $this->assertCount(6, $trend_rows);

    $by_year = [];
    foreach ($trend_rows as $row) {
      $by_year[$row[0]] = $row;
    }
    // Column order: Year, consumable, depreciation, cost, income, net.
    $this->assertEquals('-100.00', $by_year[(string) ($current_year - 4)][5]);
    $this->assertEquals('0.00', $by_year[(string) $current_year][5]);
  }

  /**
   * Apiaries the current user cannot view are left out of the report.
   */
  public function testExcludesApiariesUserCannotView(): void {
    // The superuser (uid 1) owns the apiary the viewer must not see.
    $root = User::create(['name' => 'root', 'mail' => 'root@example.com']);
    $root->save();

    $role = Role::create(['id' => 'own_viewer', 'label' => 'Own viewer']);
    $role->grantPermission('view own apiary');
    $role->grantPermission('view own inventory item');
    $role->save();
    $viewer = User::create(['name' => 'viewer', 'mail' => 'viewer@example.com']);
    $viewer->addRole('own_viewer');
    $viewer->save();

    $mine = Apiary::create(['name' => 'Viewer Apiary', 'uid' => $viewer->id()]);
    $mine->save();
    $theirs = Apiary::create(['name' => 'Root Apiary', 'uid' => $root->id()]);
    $theirs->save();

    \Drupal::currentUser()->setAccount($viewer);
    $year = (int) date('Y');
    $this->addOneYearDurable($mine, 'Extractor', $year, 40);
    $this->addOneYearDurable($theirs, 'Extractor', $year, 999);

    $html = $this->renderCombined($year);

    $this->assertStringContainsString('Viewer Apiary', $html);
    $this->assertStringNotContainsString('Root Apiary', $html);
    // The hidden apiary's figure must not leak into the total.
    $this->assertStringNotContainsString('999.00', $html);
    $this->assertStringContainsString('-40.00', $html);
  }

}
