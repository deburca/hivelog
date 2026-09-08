<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dashboard landing page controller (task 0056, ADR-0057).
 *
 * Covers criteria 2–6: the shell, the "Needs attention" widget, the six
 * stat tiles, the "Upcoming" / "Recent activity" split, and the closing
 * "Apiaries" section.
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

  /**
   * The current ISO week, or skips when too little of the year remains.
   *
   * The "Upcoming" tests need real week numbers a few weeks ahead of now.
   */
  private function weekOrSkipLateYear(): int {
    $week = (int) date('W');
    if ($week > 45) {
      $this->markTestSkipped('Not enough weeks left in the year for the look-ahead tests.');
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
    $this->assertStringContainsString('Add Apiary', $html);
    $this->assertStringContainsString('Home Apiary', $html);
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
   * A far-future action is in neither "Needs attention" nor "Upcoming".
   */
  public function testUpcomingActionHidden(): void {
    $week = $this->weekOrSkipLateYear();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'Ravnholt', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    // Six weeks out â past the 4-week "Upcoming" window and not yet due.
    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Mouse guards fitted',
      'description' => 'Later.',
      'week_start' => $week + 6,
      'week_end' => $week + 6,
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringNotContainsString('Mouse guards fitted', $html);
    $this->assertStringContainsString('All caught up', $html);
    $this->assertStringContainsString('Nothing scheduled for the next four weeks', $html);
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

    // Gone from the needs-attention queue (its action-log echoes the title
    // in "Recent activity", so check the row markup, not the bare title).
    $this->assertStringNotContainsString('hivelog-attention__row-title">Varroa autumn treatment', $html);
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
    // The first user is uid 1 (superuser) â not the account under test.
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

  // ---------------------------------------------------------------------------
  // Upcoming + Recent activity (criterion 5).
  // ---------------------------------------------------------------------------

  /**
   * Lists "Upcoming" actions starting in the next four weeks, not beyond.
   */
  public function testUpcomingShowsActionsInWindow(): void {
    $week = $this->weekOrSkipLateYear();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Fit mouse guards',
      'description' => 'x',
      'week_start' => $week + 2,
      'scope' => 'apiary',
    ])->save();
    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Winter oxalic treatment',
      'description' => 'x',
      'week_start' => $week + 8,
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Upcoming', $html);
    $this->assertStringContainsString('Fit mouse guards', $html);
    $this->assertStringContainsString('Wk ' . ($week + 2), $html);
    $this->assertStringNotContainsString('Winter oxalic treatment', $html);
  }

  /**
   * An apiary-scoped action already reported for the year drops off "Upcoming".
   */
  public function testUpcomingSkipsReportedApiaryAction(): void {
    $week = $this->weekOrSkipLateYear();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    $action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'CBR renewal',
      'description' => 'x',
      'week_start' => $week + 3,
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

    // No upcoming rows at all (the action-log still echoes the title in
    // "Recent activity", so assert on the row markup, not the title).
    $this->assertStringNotContainsString('hivelog-upcoming__row', $html);
    $this->assertStringContainsString('Nothing scheduled for the next four weeks', $html);
  }

  /**
   * Shows the "Upcoming" empty state when the window is clear.
   */
  public function testUpcomingEmptyState(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Nothing scheduled for the next four weeks', $html);
  }

  /**
   * Merges "Recent activity" record types, newest first, linking each row.
   */
  public function testRecentActivityMergesTypesNewestFirst(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();
    $hive = Hive::create(['name' => 'H1', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();

    $inspection = HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => date('Y-m-d')]);
    $inspection->set('created', time() - 500);
    $inspection->save();

    $item = InventoryItem::create(['apiary' => $apiary->id(), 'name' => 'Pads', 'unit' => 'pad']);
    $item->save();
    $purchase = InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => date('Y-m-d'),
      'quantity' => 20,
      'unit_price' => 1.5,
    ]);
    $purchase->set('created', time() - 50);
    $purchase->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Recent activity', $html);
    $this->assertStringContainsString('/hivelog/inspection/' . $inspection->id(), $html);
    $this->assertStringContainsString('/hivelog/inventory-purchase/' . $purchase->id(), $html);
    // The newer purchase row precedes the older inspection row.
    $this->assertLessThan(
      strpos($html, '/hivelog/inspection/' . $inspection->id()),
      strpos($html, '/hivelog/inventory-purchase/' . $purchase->id()),
    );
  }

  /**
   * A harvest yield (no canonical route) links to its owning action log.
   */
  public function testRecentActivityHarvestYieldLinksToLog(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    $action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Summer harvest',
      'description' => 'x',
      'week_start' => 25,
      'scope' => 'apiary',
    ]);
    $action->save();
    $log = ApiaryActionLog::create([
      'apiary' => $apiary->id(),
      'calendar_action' => $action->id(),
      'year' => (int) date('Y'),
      'status' => 'done',
    ]);
    $log->save();
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $product->save();
    HarvestYield::create([
      'product' => $product->id(),
      'quantity' => 14.2,
      'apiary_action_log' => $log->id(),
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Harvest yield', $html);
    $this->assertStringContainsString('/hivelog/apiary-action-log/' . $log->id(), $html);
  }

  /**
   * The upcoming / recent widgets declare the list cache tags they read.
   */
  public function testUpcomingRecentCacheTags(): void {
    $user = $this->makeCurrentUser();
    Apiary::create(['name' => 'A1', 'uid' => $user->id()])->save();

    $tags = $this->controller()->view()['#cache']['tags'];

    $this->assertContains('calendar_action_list', $tags);
    $this->assertContains('hive_inspection_list', $tags);
    $this->assertContains('queen_observation_list', $tags);
    $this->assertContains('inventory_purchase_list', $tags);
    $this->assertContains('harvest_yield_list', $tags);
  }

  // ---------------------------------------------------------------------------
  // Apiaries section (criterion 6).
  // ---------------------------------------------------------------------------

  /**
   * The closing section shows one row per apiary with its hive count.
   */
  public function testApiariesSectionShowsPerApiaryRows(): void {
    $user = $this->makeCurrentUser();
    $a = Apiary::create(['name' => 'Ravnholt Home', 'uid' => $user->id()]);
    $a->save();
    $b = Apiary::create(['name' => 'Sondermarken', 'uid' => $user->id()]);
    $b->save();
    $this->clearSeededCalendarActions();
    Hive::create(['name' => 'H1', 'apiary' => $a->id(), 'status' => 'active'])->save();
    Hive::create(['name' => 'H2', 'apiary' => $a->id(), 'status' => 'active'])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('Ravnholt Home', $html);
    $this->assertStringContainsString('Sondermarken', $html);
    $this->assertStringContainsString('Add Apiary', $html);
    $this->assertStringContainsString('/hivelog/apiary/add', $html);
    // Ravnholt has two hives, Sondermarken none.
    $this->assertStringContainsString('data-label="Hives">2<', $html);
    $this->assertStringContainsString('data-label="Hives">0<', $html);
  }

  /**
   * The "Open tasks" cell shows the count and an overdue callout.
   */
  public function testApiariesSectionOpenTasksOverdue(): void {
    $week = $this->weekOrSkipEdge();
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();

    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Overdue',
      'description' => 'x',
      'week_start' => max(1, $week - 3),
      'week_end' => $week - 1,
      'scope' => 'apiary',
    ])->save();
    CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => 'Due now',
      'description' => 'x',
      'week_start' => $week,
      'week_end' => $week,
      'scope' => 'apiary',
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('data-label="Open tasks">2 (1 overdue)<', $html);
  }

  /**
   * The "Low stock" cell shows a count or an em-dash.
   */
  public function testApiariesSectionLowStockCell(): void {
    $user = $this->makeCurrentUser();
    $with = Apiary::create(['name' => 'With Stock Issue', 'uid' => $user->id()]);
    $with->save();
    $without = Apiary::create(['name' => 'All Good', 'uid' => $user->id()]);
    $without->save();
    $this->clearSeededCalendarActions();

    InventoryItem::create([
      'apiary' => $with->id(),
      'name' => 'Pads',
      'unit' => 'pad',
      'low_stock_threshold' => 10,
    ])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('data-label="Low stock">1<', $html);
    $this->assertStringContainsString('data-label="Low stock">—<', $html);
  }

  /**
   * The "Last activity" cell shows a date when there is a recent inspection.
   */
  public function testApiariesSectionLastActivity(): void {
    $user = $this->makeCurrentUser();
    $apiary = Apiary::create(['name' => 'A1', 'uid' => $user->id()]);
    $apiary->save();
    $this->clearSeededCalendarActions();
    $hive = Hive::create(['name' => 'H1', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => date('Y-m-d')])->save();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('data-label="Last activity">' . date('j M') . '<', $html);
  }

  /**
   * With no activity at all the "Last activity" cell is an em-dash.
   */
  public function testApiariesSectionLastActivityEmpty(): void {
    $user = $this->makeCurrentUser();
    Apiary::create(['name' => 'Quiet', 'uid' => $user->id()])->save();
    $this->clearSeededCalendarActions();

    $html = $this->renderBuild($this->controller()->view());

    $this->assertStringContainsString('data-label="Last activity">—<', $html);
  }

}
