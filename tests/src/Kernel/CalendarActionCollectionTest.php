<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\CalendarActionController;
use Drupal\hivelog\Controller\DashboardController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the global Calendar Actions collection page (task 0073).
 *
 * Covers the week-range filter added to /hivelog/calendar-actions, and the
 * dashboard "Open seasonal tasks" stat tile linking there with the filter
 * pre-filled to "this week through the end of the year".
 *
 * @see docs/project-management/tasks/0073-calendar-actions-week-filter.md
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionCollectionTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
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
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Collection Test Apiary']);
    $this->apiary->save();
    // Delete the 31 auto-seeded starter actions so tests deal only with
    // the rows they create themselves.
    $storage = \Drupal::entityTypeManager()->getStorage('calendar_action');
    if ($all = $storage->loadMultiple()) {
      $storage->delete($all);
    }
  }

  /**
   * Pushes a request with the given query parameters onto the request stack.
   */
  protected function pushRequestWithQuery(array $query, string $path = '/hivelog/calendar-actions'): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Resolves the calendar action controller from the container.
   */
  protected function controller(): CalendarActionController {
    return \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class);
  }

  /**
   * With no query args, lists every action across apiaries, any week.
   */
  public function testCollectionListsEveryActionByDefault(): void {
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Early Season Action',
      'description' => 'Desc.',
      'week_start' => 5,
      'scope' => 'apiary',
    ])->save();
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Late Season Action',
      'description' => 'Desc.',
      'week_start' => 48,
      'scope' => 'apiary',
      'enabled' => FALSE,
    ])->save();

    $this->pushRequestWithQuery([]);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->controller()->collection());

    $this->assertStringContainsString('Early Season Action', $html);
    // Unlike the Full Calendar page, this page's default keeps
    // CalendarActionListBuilder's original "show disabled too" behaviour.
    $this->assertStringContainsString('Late Season Action', $html);
    $this->assertStringContainsString('Collection Test Apiary', $html);
  }

  /**
   * The week_from / week_to filter narrows to actions starting in range.
   */
  public function testWeekRangeFilterNarrowsResults(): void {
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Week 10 Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ])->save();
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Week 40 Action',
      'description' => 'Desc.',
      'week_start' => 40,
      'scope' => 'apiary',
    ])->save();
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Week 50 Action',
      'description' => 'Desc.',
      'week_start' => 50,
      'scope' => 'apiary',
    ])->save();

    $this->pushRequestWithQuery(['week_from' => '38', 'week_to' => '53']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->controller()->collection());

    $this->assertStringNotContainsString('Week 10 Action', $html);
    $this->assertStringContainsString('Week 40 Action', $html);
    $this->assertStringContainsString('Week 50 Action', $html);
  }

  /**
   * A reversed range (week_from > week_to) is swapped rather than empty.
   */
  public function testReversedWeekRangeIsSwapped(): void {
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Mid Year Action',
      'description' => 'Desc.',
      'week_start' => 25,
      'scope' => 'apiary',
    ])->save();

    $this->pushRequestWithQuery(['week_from' => '30', 'week_to' => '20']);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->controller()->collection());

    $this->assertStringContainsString('Mid Year Action', $html);
  }

  /**
   * The page offers Edit/Delete operations and the calculation footnote.
   */
  public function testCollectionHasOperationsAndFootnote(): void {
    CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Editable Collection Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ])->save();

    $this->pushRequestWithQuery([]);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($this->controller()->collection());

    $this->assertStringContainsString('/edit', $html);
    $this->assertStringContainsString('/delete', $html);
    $this->assertStringContainsString('hivelog-list-footnote', $html);
    $this->assertStringContainsString('Open seasonal tasks', $html);
  }

  /**
   * The "Open seasonal tasks" tile links with the week filter pre-filled.
   *
   * Pre-filled to "this week through week 53".
   */
  public function testDashboardTileLinksWithWeekFilter(): void {
    $current_week = (int) date('W');
    if ($current_week > 45) {
      $this->markTestSkipped('Not enough weeks left in the year to distinguish week_from from 53.');
    }

    $dashboard = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(DashboardController::class);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($dashboard->view());

    $this->assertStringContainsString(
      '/hivelog/calendar-actions?week_from=' . $current_week . '&amp;week_to=53',
      $html
    );
  }

}
