<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dashboard landing page controller (task 0056, ADR-0057).
 *
 * Covers criterion 2 (the shell), criterion 3 (the "Needs attention"
 * widget) and criterion 4 (the six stat tiles).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class DashboardTest extends KernelTestBase {

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('product');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Resolves the dashboard controller from the container.
   */
  private function controller(): DashboardController {
    return \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(DashboardController::class);
  }

  /**
   * Renders a build array to an HTML string.
   */
  private function renderBuild(array $build): string {
    return (string) \Drupal::service('renderer')->renderInIsolation($build);
  }

  /**
   * Creates a user and makes it the current account.
   *
   * The first user created in a kernel test is uid 1 (the superuser), so
   * the dashboard's per-entity access filtering lets it see every apiary.
   */
  private function makeCurrentUser(): User {
    $user = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);
    return $user;
  }

  /**
   * Deletes the 31 calendar actions Apiary::postSave() seeds on insert.
   */
  private function clearSeededCalendarActions(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('calendar_action');
    if ($all = $storage->loadMultiple()) {
      $storage->delete($all);
    }
  }

  /**
   * The current ISO week, or skips the test when a "past week" can't exist.
   */
  private function weekOrSkipEdge(): int {
    $week = (int) date('W');
    if ($week < 2) {
      $this->markTestSkipped('An overdue action cannot be constructed in ISO week 1.');
    }
    return $week;
  }

  // ---------------------------------------------------------------------------
  // Shell (criterion 2).
  // ---------------------------------------------------------------------------

  /**
   * With no visible apiaries the dashboard shows the first-run welcome card.
   */
  public function testFirstRunWelcomeState(): void {
    $this->makeCurrentUser();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Welcome to HiveLog', $html);
    $this->assertStringContainsString('31-entry seasonal calendar', $html);
    $this->assertStringContainsString('/hivelog/apiary/add', $html);
    $this->assertStringNotContainsString('Go to Apiaries', $html);
    $this->assertStringNotContainsString('Needs attention', $html);
  }

  /**
   * With at least one visible apiary the welcome is replaced by the widgets.
   */
  public function testWidgetsShownWhenApiariesExist(): void {
    $user = $this->makeCurrentUser();
    Apiary::create(['name' => 'Home Apiary', 'uid' => $user->id()])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringNotContainsString('Welcome to HiveLog', $html);
    $this->assertStringContainsString('Needs attention', $html);
    $this->assertStringContainsString('Go to Apiaries', $html);
  }

  /**
   * The header strip prints the current ISO week and year.
   */
  public function testHeaderShowsCurrentWeek(): void {
    $this->makeCurrentUser();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('<strong>' . ((int) date('W')) . '</strong>', $html);
    $this->assertStringContainsString((string) ((int) date('Y')), $html);
  }

  /**
   * The CBR summary line renders its three states from the dashboard.
   */
  public function testCbrSummaryStates(): void {
    $with = User::create([
      'name' => 'cbr-with',
      'mail' => 'cbr-with@example.com',
      'cbr_number' => 'IE-4242',
    ]);
    $with->save();
    $without = User::create([
      'name' => 'cbr-without',
      'mail' => 'cbr-without@example.com',
    ]);
    $without->save();

    \Drupal::currentUser()->setAccount($with);
    $this->assertStringContainsString(
      'Your CBR number: IE-4242',
      $this->renderBuild($this->controller()->view())
    );

    \Drupal::currentUser()->setAccount($without);
    $html = $this->renderBuild($this->controller()->view());
    $this->assertStringContainsString('have not set a CBR number yet', $html);
    $this->assertStringContainsString('Update your profile', $html);
  }

  /**
   * The stat-tile SDC renders its value, label, link and sub-line variant.
   */
  public function testStatTileComponentRenders(): void {
    $build = [
      '#type' => 'component',
      '#component' => 'hivelog:stat-tile',
      '#props' => [
        'value' => '7',
        'label' => 'Active hives',
        'url' => '/hivelog/hives',
        'sublabel' => '2 overdue',
        'sublabel_variant' => 'critical',
      ],
    ];

    $html = $this->renderBuild($build);

    $this->assertStringContainsString('class="hivelog-stat-tile"', $html);
    $this->assertStringContainsString('href="/hivelog/hives"', $html);
    $this->assertStringContainsString('Active hives', $html);
    $this->assertStringContainsString('>7<', $html);
    $this->assertStringContainsString('hivelog-stat-tile__sub--critical', $html);
    $this->assertStringContainsString('2 overdue', $html);
  }

  /**
   * Cache metadata: user context, apiary list tag, ISO-week-bounded max-age.
   */
  public function testCacheMetadata(): void {
    $this->makeCurrentUser();

    $build = $this->controller()->view();

    $this->assertContains('user', $build['#cache']['contexts']);
    $this->assertContains('apiary_list', $build['#cache']['tags']);
    $this->assertGreaterThan(0, $build['#cache']['max-age']);
    $this->assertLessThanOrEqual(7 * 24 * 3600, $build['#cache']['max-age']);
  }

  // ---------------------------------------------------------------------------
  // Needs attention (criterion 3).
  // ---------------------------------------------------------------------------

  /**
   * With nothing overdue / due / low the panel shows the caught-up message.
   */
  public function testNeedsAttentionAllCaughtUp(): void {
    $user = $this->makeCurrentUser();
    Apiary::create(['name' => 'Quiet Apiary', 'uid' => $user->id()])->save();
    $this->clearSeededCalendarActions();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Needs attention', $html);
    $this->assertStringContainsString('All caught up for week ' . ((int) date('W')), $html);
  }

  /**
   * An overdue, unreported apiary-scoped action becomes a critical row.
   */
  public function testOverdueApiaryActionAppears(): void {
    $week = $this->weekOrSkipEdge();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Ravnholt', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Wasp defence check',
      'description' => 'Reduce entrances.',
      'week_start' => max(1, $week - 4),
      'week_end' => $week - 1,
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Wasp defence check', $html);
    $this->assertStringContainsString('hivelog-attention__row--critical', $html);
    $this->assertStringContainsString('Overdue', $html);
    $this->assertStringNotContainsString('All caught up', $html);
    $this->assertStringContainsString('/log/add?status=done', $html);
  }

  /**
   * An action whose window covers the current week becomes a warning row.
   */
  public function testDueThisWeekActionAppears(): void {
    $week = (int) date('W');
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Ravnholt', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Feed winter stores',
      'description' => 'Syrup.',
      'week_start' => $week,
      'week_end' => $week,
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Feed winter stores', $html);
    $this->assertStringContainsString('hivelog-attention__row--warning', $html);
    $this->assertStringContainsString('Due wk ' . $week, $html);
  }

  /**
   * An action whose window is still ahead is not shown.
   */
  public function testUpcomingActionHidden(): void {
    $week = (int) date('W');
    if ($week > 51) {
      $this->markTestSkipped('A future week cannot be constructed near week 53.');
    }
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Ravnholt', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Mouse guards fitted',
      'description' => 'Later.',
      'week_start' => $week + 2,
      'week_end' => $week + 2,
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringNotContainsString('Mouse guards fitted', $html);
    $this->assertStringContainsString('All caught up', $html);
  }

  /**
   * An action already reported "done" for this year is not shown.
   */
  public function testReportedActionDoesNotAppear(): void {
    $week = $this->weekOrSkipEdge();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Ravnholt', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    $action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Varroa autumn treatment',
      'description' => 'Formic.',
      'week_start' => max(1, $week - 3),
      'week_end' => $week - 1,
      'scope' => 'apiary',
    ]);
    $action->save();

    ApiaryActionLog::create([
      'apiary' => $apiary->id(),
      'calendar_action' => $action->id(),
      'year' => (int) date('Y'),
      'status' => 'done',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringNotContainsString('Varroa autumn treatment', $html);
    $this->assertStringContainsString('All caught up', $html);
  }

  /**
   * An overdue hive-scoped action produces one row per hive in the apiary.
   */
  public function testHiveScopedOverdueAppearsPerHive(): void {
    $week = $this->weekOrSkipEdge();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Sondermarken', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    Hive::create(['name' => 'Hive S-1', 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    Hive::create(['name' => 'Hive S-2', 'apiary' => $apiary->id(), 'status' => 'active'])->save();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Autumn weight check',
      'description' => 'Heft each hive.',
      'week_start' => max(1, $week - 3),
      'week_end' => $week - 1,
      'scope' => 'hive',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertSame(2, substr_count($html, 'hivelog-attention__row-title">Autumn weight check'));
    $this->assertStringContainsString('Hive S-1', $html);
    $this->assertStringContainsString('Hive S-2', $html);
    $this->assertStringContainsString('/hivelog/hive/', $html);
  }

  /**
   * A consumable item at or below its threshold becomes a low-stock row.
   */
  public function testLowStockItemAppears(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Store Apiary', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Formic acid pads',
      'unit' => 'pad',
      'low_stock_threshold' => 10,
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Formic acid pads', $html);
    $this->assertStringContainsString('Low stock', $html);
    $this->assertStringContainsString('reorder at 10', $html);
    $this->assertStringContainsString('/inventory-purchase/add', $html);
  }

  /**
   * A discontinued item is never surfaced as low stock.
   */
  public function testDiscontinuedLowStockItemExcluded(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Store Apiary', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Old smoker fuel',
      'unit' => 'kg',
      'low_stock_threshold' => 5,
      'status' => 'discontinued',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringNotContainsString('Old smoker fuel', $html);
    $this->assertStringContainsString('All caught up', $html);
  }

  // ---------------------------------------------------------------------------
  // Stat tiles (criterion 4).
  // ---------------------------------------------------------------------------

  /**
   * The tile grid renders with the apiary and active-hive counts.
   */
  public function testStatTilesGridRenders(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();
    Hive::create(['name' => 'H1', 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    Hive::create(['name' => 'H2', 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    Hive::create(['name' => 'H3', 'apiary' => $apiary->id(), 'status' => 'inactive'])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('hivelog-stat-tiles', $html);
    $this->assertStringContainsString('Apiaries', $html);
    $this->assertStringContainsString('Active hives', $html);
    // 1 apiary; 2 active hives (H3 is inactive).
    $this->assertStringContainsString('hivelog-stat-tile__value">1<', $html);
    $this->assertStringContainsString('hivelog-stat-tile__value">2<', $html);
  }

  /**
   * Counts "Inspections this month" over the current calendar month only.
   */
  public function testInspectionsThisMonthTile(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();
    Hive::create(['name' => 'H1', 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    $hive = Hive::create(['name' => 'H2', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => date('Y-m-15')])->save();
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => date('Y-m-15', strtotime('first day of last month')),
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Inspections this month', $html);
    // Apiaries "1" + Inspections this month "1"; last month's is excluded.
    $this->assertSame(2, substr_count($html, 'hivelog-stat-tile__value">1<'));
  }

  /**
   * Tallies open seasonal actions and the overdue subset for the tile.
   */
  public function testOpenSeasonalTasksTile(): void {
    $week = $this->weekOrSkipEdge();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Overdue task',
      'description' => 'x',
      'week_start' => max(1, $week - 3),
      'week_end' => $week - 1,
      'scope' => 'apiary',
    ])->save();
    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Later task',
      'description' => 'x',
      'week_start' => min(53, $week + 3),
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Open seasonal tasks', $html);
    $this->assertStringContainsString('hivelog-stat-tile__value">2<', $html);
    $this->assertStringContainsString('1 overdue', $html);
    $this->assertStringContainsString('hivelog-stat-tile__sub--critical', $html);
  }

  /**
   * Counts inventory items at or below their low-stock threshold.
   */
  public function testLowStockTile(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    InventoryItem::create(['apiary' => $apiary->id(), 'name' => 'Pads', 'unit' => 'pad', 'low_stock_threshold' => 10])->save();
    InventoryItem::create(['apiary' => $apiary->id(), 'name' => 'Syrup', 'unit' => 'kg', 'low_stock_threshold' => 5])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Low-stock items', $html);
    $this->assertStringContainsString('hivelog-stat-tile__value">2<', $html);
  }

  /**
   * The Net YTD tile is shown to a user with inventory-view access.
   */
  public function testNetYtdTileShownForPermittedUser(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    $this->assertStringContainsString('Net YTD', $this->renderBuild($this->controller()->view()));
  }

  /**
   * The Net YTD tile is hidden from a user without inventory-view access.
   */
  public function testNetYtdTileHiddenWithoutInventoryPermission(): void {
    // The first user is uid 1 (superuser) — not the account under test.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();

    $role = Role::create(['id' => 'apiary_viewer', 'label' => 'Apiary viewer']);
    $role->grantPermission('view any apiary');
    $role->grantPermission('view any hive');
    $role->grantPermission('view any hive inspection');
    $role->grantPermission('view any calendar action');
    $role->save();

    $viewer = User::create(['name' => 'viewer', 'mail' => 'viewer@example.com']);
    $viewer->save();
    $viewer->addRole('apiary_viewer');
    $viewer->save();
    \Drupal::currentUser()->setAccount($viewer);

    $apiary = Apiary::create(['name' => 'Shared Apiary']);
    $apiary->save();
    $this->clearSeededCalendarActions();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('hivelog-stat-tiles', $html);
    $this->assertStringContainsString('Apiaries', $html);
    $this->assertStringNotContainsString('Net YTD', $html);
  }

  /**
   * The needs-attention roll-up declares the list cache tags it reads.
   */
  public function testNeedsAttentionCacheTags(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Tagged Apiary', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Any enabled action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ])->save();
    InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => 'Tracked item',
      'unit' => 'kg',
      'low_stock_threshold' => 1,
    ])->save();

    $tags = $this->controller()->view()['#cache']['tags'];

    $this->assertContains('calendar_action_list', $tags);
    $this->assertContains('apiary_action_log_list', $tags);
    $this->assertContains('hive_action_log_list', $tags);
    $this->assertContains('inventory_item_list', $tags);
    $this->assertContains('inventory_purchase_list', $tags);
  }

}
