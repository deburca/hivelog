<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the apiary-scoped seasonal calendar checklist (task 0027).
 *
 * Mirrors HiveCalendarChecklistTest's key behaviours (default status/year
 * filtering, next-year preview, reporting flow) for ApiaryController's
 * checklist, and covers the new Full Calendar page.
 *
 * @see docs/project-management/tasks/0027-apiary-vs-hive-scoped-calendar-items.md
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryCalendarChecklistTest extends KernelTestBase {

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
    $this->installSchema('file', ['file_usage']);

    $user = User::create([
      'name' => 'tester',
      'mail' => 'tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Checklist Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Pushes a request with the given query parameters onto the request stack.
   */
  protected function pushRequestWithQuery(array $query, string $path = '/hivelog/apiary/1'): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests that the apiary checklist defaults to unreported for the current year.
   */
  public function testChecklistDefaultsToUnreportedForCurrentYear(): void {
    $pending_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Pending Apiary Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $pending_action->save();

    $done_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Done Apiary Action',
      'description' => 'Desc.',
      'week_start' => 15,
      'scope' => 'apiary',
    ]);
    $done_action->save();
    ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $done_action->id(),
      'status' => 'done',
      'year' => (int) date('Y'),
    ])->save();

    // No query args at all -> default view.
    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Pending Apiary Action', $html);
    $this->assertStringNotContainsString('Done Apiary Action', $html);
  }

  /**
   * Tests that previewing next year surfaces the action as unreported.
   */
  public function testNextYearPreviewShowsRecurringActionAsUnreported(): void {
    $current_year = (int) date('Y');
    $next_year = $current_year + 1;

    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Recurring Apiary Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'recurring' => TRUE,
      'scope' => 'apiary',
    ]);
    $action->save();

    // This year's occurrence has already been reported done.
    ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $action->id(),
      'status' => 'done',
      'year' => $current_year,
    ])->save();

    // Default (this year) view: hidden, since it's already reported.
    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build_this_year = $controller->view($this->apiary);
    $html_this_year = (string) \Drupal::service('renderer')->renderInIsolation($build_this_year);
    $this->assertStringNotContainsString('Recurring Apiary Action', $html_this_year);

    // Next year: no log exists yet for that year, so it reappears as
    // pending/unreported.
    $this->pushRequestWithQuery(['year' => (string) $next_year]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build_next_year = $controller->view($this->apiary);
    $html_next_year = (string) \Drupal::service('renderer')->renderInIsolation($build_next_year);
    $this->assertStringContainsString('Recurring Apiary Action', $html_next_year);
  }

  /**
   * Tests that reporting an item removes it from the default view.
   */
  public function testReportingRemovesFromDefaultViewAndSurfacesUnderStatusFilter(): void {
    $done_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Will Be Done',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $done_action->save();

    $ignored_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Will Be Ignored',
      'description' => 'Desc.',
      'week_start' => 12,
      'scope' => 'apiary',
    ]);
    $ignored_action->save();

    ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $done_action->id(),
      'status' => 'done',
      'year' => (int) date('Y'),
    ])->save();
    ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $ignored_action->id(),
      'status' => 'ignored',
      'year' => (int) date('Y'),
    ])->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);

    // Default view: neither appears (both reported).
    $this->pushRequestWithQuery([]);
    $default_build = $controller->view($this->apiary);
    $default_html = (string) \Drupal::service('renderer')->renderInIsolation($default_build);
    $this->assertStringNotContainsString('Will Be Done', $default_html);
    $this->assertStringNotContainsString('Will Be Ignored', $default_html);

    // status=done: only the done one appears.
    $this->pushRequestWithQuery(['status' => 'done']);
    $done_build = $controller->view($this->apiary);
    $done_html = (string) \Drupal::service('renderer')->renderInIsolation($done_build);
    $this->assertStringContainsString('Will Be Done', $done_html);
    $this->assertStringNotContainsString('Will Be Ignored', $done_html);

    // status=ignored: only the ignored one appears.
    $this->pushRequestWithQuery(['status' => 'ignored']);
    $ignored_build = $controller->view($this->apiary);
    $ignored_html = (string) \Drupal::service('renderer')->renderInIsolation($ignored_build);
    $this->assertStringContainsString('Will Be Ignored', $ignored_html);
    $this->assertStringNotContainsString('Will Be Done', $ignored_html);

    // status=all: both appear.
    $this->pushRequestWithQuery(['status' => 'all']);
    $all_build = $controller->view($this->apiary);
    $all_html = (string) \Drupal::service('renderer')->renderInIsolation($all_build);
    $this->assertStringContainsString('Will Be Done', $all_html);
    $this->assertStringContainsString('Will Be Ignored', $all_html);
  }

  /**
   * Tests that the Full Calendar page lists both scopes, unfiltered by status.
   */
  public function testFullCalendarListsBothScopes(): void {
    $hive_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Full Calendar Hive Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'hive',
    ]);
    $hive_action->save();

    $apiary_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Full Calendar Apiary Action',
      'description' => 'Desc.',
      'week_start' => 20,
      'scope' => 'apiary',
    ]);
    $apiary_action->save();

    $disabled_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Full Calendar Disabled Action',
      'description' => 'Desc.',
      'week_start' => 30,
      'scope' => 'apiary',
      'enabled' => FALSE,
    ]);
    $disabled_action->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->fullCalendar($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Full Calendar Hive Action', $html);
    $this->assertStringContainsString('Full Calendar Apiary Action', $html);
    $this->assertStringNotContainsString('Full Calendar Disabled Action', $html);
  }

  /**
   * Tests that the apiary view links to the Full Calendar page.
   */
  public function testApiaryViewLinksToFullCalendar(): void {
    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('/hivelog/apiary/' . $this->apiary->id() . '/calendar', $html);
  }

  /**
   * Tests that the checklist's report buttons never leak a hive id.
   *
   * Report Done/Ignored actions link to the apiary_action_log add route,
   * which never involves a hive at all.
   */
  public function testChecklistReportButtonsLinkToApiaryActionLogAddRoute(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Reportable Apiary Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $action->save();

    // Give the apiary a hive too, to make sure the checklist doesn't
    // accidentally leak a hive-scoped log route.
    Hive::create([
      'name' => 'Bystander Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ])->save();

    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $expected_path = '/hivelog/apiary/' . $this->apiary->id() . '/calendar-action/' . $action->id() . '/log/add';
    $this->assertStringContainsString($expected_path, $html);
    // The bystander hive legitimately appears in the Hives table (e.g.
    // /hivelog/hive/1), but no hivelog.hive_action_log.add link
    // (/hivelog/hive/{hive}/calendar-action/...) should ever appear on the
    // apiary's checklist, which is exclusively apiary-scoped.
    $this->assertDoesNotMatchRegularExpression('#/hivelog/hive/\d+/calendar-action/#', $html);
  }

}
