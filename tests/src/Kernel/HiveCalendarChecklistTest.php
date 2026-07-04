<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\CalendarActionController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the seasonal calendar checklist behaviour on the apiary/hive pages.
 *
 * Covers the specific requirements added across
 * docs/project-management/tasks/0020-apiary-and-hive-calendar-ui.md and
 * 0021-hive-calendar-filtering-and-report-actions.md.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveCalendarChecklistTest extends KernelTestBase {

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
   * A test hive.
   */
  protected Hive $hive;

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

    $this->hive = Hive::create([
      'name' => 'Checklist Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();
  }

  /**
   * Pushes a request with the given query parameters onto the request stack.
   */
  protected function pushRequestWithQuery(array $query, string $path = '/hivelog/hive/1'): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests that a disabled CalendarAction is hidden from views.
   *
   * A `CalendarAction` with `enabled = FALSE` never appears on its scope's
   * checklist (the apiary-scoped checklist on the apiary page, or a hive's
   * checklist for hive-scoped actions), but does appear in
   * entity.calendar_action.collection. Since task 0027, the apiary page's
   * checklist only shows `scope = apiary` actions and a hive's checklist
   * only shows `scope = hive` actions, so this test uses one enabled/
   * disabled pair per scope to exercise both checklists.
   */
  public function testDisabledCalendarActionHiddenFromViewsButVisibleInCollection(): void {
    $enabled_hive = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Enabled Hive Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'hive',
    ]);
    $enabled_hive->save();

    $disabled_hive = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Disabled Hive Action',
      'description' => 'Desc.',
      'week_start' => 12,
      'enabled' => FALSE,
      'scope' => 'hive',
    ]);
    $disabled_hive->save();

    $enabled_apiary = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Enabled Apiary Action',
      'description' => 'Desc.',
      'week_start' => 14,
      'scope' => 'apiary',
    ]);
    $enabled_apiary->save();

    $disabled_apiary = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Disabled Apiary Action',
      'description' => 'Desc.',
      'week_start' => 16,
      'enabled' => FALSE,
      'scope' => 'apiary',
    ]);
    $disabled_apiary->save();

    // Apiary's calendar checklist only shows apiary-scoped actions.
    $this->pushRequestWithQuery(['status' => 'all'], '/hivelog/apiary/1');
    $apiary_controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $apiary_build = $apiary_controller->view($this->apiary);
    $apiary_html = (string) \Drupal::service('renderer')->renderInIsolation($apiary_build);
    $this->assertStringContainsString('Enabled Apiary Action', $apiary_html);
    $this->assertStringNotContainsString('Disabled Apiary Action', $apiary_html);
    $this->assertStringNotContainsString('Enabled Hive Action', $apiary_html);
    $this->assertStringNotContainsString('Disabled Hive Action', $apiary_html);

    // Hive checklist only shows hive-scoped actions (status=all so we're
    // not additionally filtering by report status — we specifically want
    // to isolate the enabled/disabled behaviour here).
    $this->pushRequestWithQuery(['status' => 'all']);
    $hive_controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $hive_build = $hive_controller->view($this->hive);
    $hive_html = (string) \Drupal::service('renderer')->renderInIsolation($hive_build);
    $this->assertStringContainsString('Enabled Hive Action', $hive_html);
    $this->assertStringNotContainsString('Disabled Hive Action', $hive_html);
    $this->assertStringNotContainsString('Enabled Apiary Action', $hive_html);
    $this->assertStringNotContainsString('Disabled Apiary Action', $hive_html);

    // The list builder (management view) still shows all four, regardless
    // of scope or enabled state.
    $list_builder = \Drupal::entityTypeManager()->getListBuilder('calendar_action');
    $list_build = $list_builder->render();
    $list_html = (string) \Drupal::service('renderer')->renderInIsolation($list_build);
    $this->assertStringContainsString('Enabled Hive Action', $list_html);
    $this->assertStringContainsString('Disabled Hive Action', $list_html);
    $this->assertStringContainsString('Enabled Apiary Action', $list_html);
    $this->assertStringContainsString('Disabled Apiary Action', $list_html);
  }

  /**
   * Tests that the checklist defaults to unreported items for this year.
   *
   * A hive's checklist defaults to showing only unreported items (no log,
   * or a pending log) for the current year.
   */
  public function testChecklistDefaultsToUnreportedForCurrentYear(): void {
    $pending_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Pending Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $pending_action->save();

    $done_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Done Action',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $done_action->save();
    HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $done_action->id(),
      'status' => 'done',
      'year' => (int) date('Y'),
    ])->save();

    // No query args at all → default view.
    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($this->hive);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Pending Action', $html);
    $this->assertStringNotContainsString('Done Action', $html);
  }

  /**
   * Tests that previewing next year surfaces the action as unreported.
   *
   * Switching the year filter to next year, with no logs yet created for
   * it, surfaces every enabled recurring CalendarAction as unreported —
   * the "view all pending items for the coming year" requirement.
   */
  public function testNextYearPreviewShowsRecurringActionAsUnreported(): void {
    $current_year = (int) date('Y');
    $next_year = $current_year + 1;

    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Recurring Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'recurring' => TRUE,
    ]);
    $action->save();

    // This year's occurrence has already been reported done.
    HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $action->id(),
      'status' => 'done',
      'year' => $current_year,
    ])->save();

    // Default (this year) view: hidden, since it's already reported.
    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build_this_year = $controller->view($this->hive);
    $html_this_year = (string) \Drupal::service('renderer')->renderInIsolation($build_this_year);
    $this->assertStringNotContainsString('Recurring Action', $html_this_year);

    // Next year: no log exists yet for that year, so it reappears as
    // pending/unreported — the core "preview the coming year" behaviour.
    $this->pushRequestWithQuery(['year' => (string) $next_year]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build_next_year = $controller->view($this->hive);
    $html_next_year = (string) \Drupal::service('renderer')->renderInIsolation($build_next_year);
    $this->assertStringContainsString('Recurring Action', $html_next_year);
  }

  /**
   * Tests that reporting an item removes it from the default view.
   *
   * Reporting an item (done or ignored) removes it from the default
   * unreported view and surfaces it under the matching status filter.
   */
  public function testReportingRemovesFromDefaultViewAndSurfacesUnderStatusFilter(): void {
    $done_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Will Be Done',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $done_action->save();

    $ignored_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Will Be Ignored',
      'description' => 'Desc.',
      'week_start' => 12,
    ]);
    $ignored_action->save();

    HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $done_action->id(),
      'status' => 'done',
      'year' => (int) date('Y'),
    ])->save();
    HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $ignored_action->id(),
      'status' => 'ignored',
      'year' => (int) date('Y'),
    ])->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);

    // Default view: neither appears (both reported).
    $this->pushRequestWithQuery([]);
    $default_build = $controller->view($this->hive);
    $default_html = (string) \Drupal::service('renderer')->renderInIsolation($default_build);
    $this->assertStringNotContainsString('Will Be Done', $default_html);
    $this->assertStringNotContainsString('Will Be Ignored', $default_html);

    // status=done: only the done one appears.
    $this->pushRequestWithQuery(['status' => 'done']);
    $done_build = $controller->view($this->hive);
    $done_html = (string) \Drupal::service('renderer')->renderInIsolation($done_build);
    $this->assertStringContainsString('Will Be Done', $done_html);
    $this->assertStringNotContainsString('Will Be Ignored', $done_html);

    // status=ignored: only the ignored one appears.
    $this->pushRequestWithQuery(['status' => 'ignored']);
    $ignored_build = $controller->view($this->hive);
    $ignored_html = (string) \Drupal::service('renderer')->renderInIsolation($ignored_build);
    $this->assertStringContainsString('Will Be Ignored', $ignored_html);
    $this->assertStringNotContainsString('Will Be Done', $ignored_html);

    // status=all: both appear.
    $this->pushRequestWithQuery(['status' => 'all']);
    $all_build = $controller->view($this->hive);
    $all_html = (string) \Drupal::service('renderer')->renderInIsolation($all_build);
    $this->assertStringContainsString('Will Be Done', $all_html);
    $this->assertStringContainsString('Will Be Ignored', $all_html);
  }

  /**
   * Tests that description bullet rendering is properly escaped.
   *
   * `description` lines starting with "- "/"* " render as <li> items inside
   * a <ul>; other lines render as escaped paragraphs. Guards against a raw
   * <script>-style payload to confirm Html::escape() is applied before
   * list-wrapping.
   */
  public function testDescriptionBulletRenderingIsEscaped(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'XSS Test Action',
      'description' => "Intro paragraph with <script>alert('xss')</script>.\n- Bullet one <script>alert('bullet')</script>\n- Bullet two",
      'week_start' => 10,
    ]);
    $action->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class);
    $build = $controller->view($action);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    // The bullets are wrapped in a real <ul><li> list.
    $this->assertStringContainsString('<ul><li>', $html);
    $this->assertStringContainsString('</li><li>', $html);
    $this->assertStringContainsString('</li></ul>', $html);
    $this->assertStringContainsString('Bullet one', $html);
    $this->assertStringContainsString('Bullet two', $html);

    // The intro paragraph is present, wrapped in a <p>.
    $this->assertStringContainsString('Intro paragraph with', $html);

    // Crucially: no raw, executable <script> tag reached the page — it must
    // have been escaped to &lt;script&gt; before any list/paragraph markup
    // was added.
    $this->assertStringNotContainsString('<script>alert', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

}
