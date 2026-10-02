<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Form\HivelogHiveActionLogFilterForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filters on the Hive and Apiary Action Log lists (task 0168).
 *
 * `/hivelog/hive-action-logs` and `/hivelog/apiary-action-logs` now carry
 * `HivelogHiveActionLogFilterForm` / `HivelogApiaryActionLogFilterForm`
 * (thin subclasses of `HivelogActionLogFilterFormBase`) — full-page-only,
 * same shape as `ApiaryQueenFilterTest`'s own coverage of task 0155.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ActionLogFilterTest extends KernelTestBase {

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
   * A test hive ("Hive Alpha"), belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * Calendar action "Varroa Treatment", belonging to `$apiary`.
   */
  protected CalendarAction $varroa;

  /**
   * Calendar action "Winter Preparation", belonging to `$apiary`.
   */
  protected CalendarAction $winter;

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
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Ravnholt Home']);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'Hive Alpha', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();

    $this->varroa = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Varroa Treatment',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $this->varroa->save();
    $this->winter = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Winter Preparation',
      'description' => 'Desc.',
      'week_start' => 41,
    ]);
    $this->winter->save();
  }

  /**
   * Pushes a request onto the stack, routed as `$route_name`.
   *
   * Matches the request through the real router first, so Reset's
   * `Url::fromRoute('<current>')` resolves to this route.
   */
  protected function pushRoutedRequest(string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Creates a hive action log.
   */
  protected function hiveLog(CalendarAction $action, string $status, int $year, ?Hive $hive = NULL): HiveActionLog {
    $log = HiveActionLog::create([
      'hive' => ($hive ?? $this->hive)->id(),
      'calendar_action' => $action->id(),
      'status' => $status,
      'year' => $year,
    ]);
    $log->save();
    return $log;
  }

  /**
   * Creates an apiary action log.
   */
  protected function apiaryLog(CalendarAction $action, string $status, int $year, ?Apiary $apiary = NULL): ApiaryActionLog {
    $log = ApiaryActionLog::create([
      'apiary' => ($apiary ?? $this->apiary)->id(),
      'calendar_action' => $action->id(),
      'status' => $status,
      'year' => $year,
    ]);
    $log->save();
    return $log;
  }

  /**
   * Renders a collection and returns its rows.
   */
  protected function rows(string $entity_type, string $path, array $query = []): array {
    $this->pushRoutedRequest($path, $query);
    return $this->buildList($entity_type)['table']['#props']['rows'];
  }

  /**
   * Builds the list builder's render array for the current request.
   */
  protected function buildList(string $entity_type): array {
    return \Drupal::entityTypeManager()->getListBuilder($entity_type)->render();
  }

  /**
   * Tests the Hive Action Log list filters by status and Reset clears it.
   */
  public function testHiveLogStatusFilterAndReset(): void {
    $this->hiveLog($this->varroa, 'done', 2026);
    $this->hiveLog($this->winter, 'pending', 2026);

    $this->pushRoutedRequest('/hivelog/hive-action-logs', ['status' => 'done']);
    $build = $this->buildList('hive_action_log');

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Varroa Treatment', (string) $build['table']['#props']['rows'][0]['cells'][1]);

    $reset_url = $build['filter']['filter_actions']['reset']['#props']['url'];
    $this->assertStringContainsString('/hivelog/hive-action-logs', $reset_url);
    $this->assertStringNotContainsString('status', $reset_url);
  }

  /**
   * Tests the year filter, and that a non-numeric year is ignored.
   */
  public function testHiveLogYearFilterIgnoresNonNumeric(): void {
    $this->hiveLog($this->varroa, 'done', 2025);
    $this->hiveLog($this->varroa, 'done', 2026);

    $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['year' => '2025']);
    $this->assertCount(1, $rows);
    $this->assertEquals('2025', $rows[0]['cells'][2]);

    // 'abc', '-1' and '2025.5' are not all digits: dropped, so no filter.
    foreach (['abc', '-1', '2025.5'] as $bad) {
      $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['year' => $bad]);
      $this->assertCount(2, $rows, "Year '$bad' must be ignored, not filter.");
    }
  }

  /**
   * Tests the calendar-action and hive name filters (substring matches).
   */
  public function testHiveLogActionAndHiveNameFilters(): void {
    $other = Hive::create(['name' => 'Hive Beta', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $other->save();
    $this->hiveLog($this->varroa, 'done', 2026);
    $this->hiveLog($this->varroa, 'done', 2026, $other);
    $this->hiveLog($this->winter, 'done', 2026);

    $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['action' => 'varroa']);
    $this->assertCount(2, $rows);

    $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['parent' => 'Beta']);
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('Hive Beta', (string) $rows[0]['cells'][0]);

    $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['parent' => 'Alpha', 'action' => 'winter']);
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('Winter Preparation', (string) $rows[0]['cells'][1]);
  }

  /**
   * Tests LIKE wildcards in the text filters are matched literally.
   */
  public function testTextFiltersEscapeLikeWildcards(): void {
    $this->hiveLog($this->varroa, 'done', 2026);

    $this->assertCount(0, $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['action' => '%']));
    $this->assertCount(0, $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['parent' => '_']));
  }

  /**
   * Tests the empty state distinguishes "filtered to nothing" from "no rows".
   */
  public function testEmptyStateDistinguishesFilteredFromUnfiltered(): void {
    $this->pushRoutedRequest('/hivelog/hive-action-logs');
    $build = $this->buildList('hive_action_log');
    $this->assertStringContainsString('There are no', $build['table']['#props']['empty_message']);

    $this->hiveLog($this->varroa, 'done', 2026);
    $this->pushRoutedRequest('/hivelog/hive-action-logs', ['status' => 'ignored']);
    $build = $this->buildList('hive_action_log');
    $this->assertCount(0, $build['table']['#props']['rows']);
    $this->assertStringContainsString('match the current filters', $build['table']['#props']['empty_message']);
  }

  /**
   * Tests the Apiary Action Log list filters by status, year and apiary name.
   */
  public function testApiaryLogFilters(): void {
    $other = Apiary::create(['name' => 'Verdigris Yard']);
    $other->save();
    $this->apiaryLog($this->varroa, 'done', 2026);
    $this->apiaryLog($this->winter, 'pending', 2025);
    $other_action = CalendarAction::create([
      'apiary' => $other->id(),
      'title' => 'Other Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $other_action->save();
    $this->apiaryLog($other_action, 'done', 2026, $other);

    $this->assertCount(2, $this->rows('apiary_action_log', '/hivelog/apiary-action-logs', ['status' => 'done']));
    $this->assertCount(1, $this->rows('apiary_action_log', '/hivelog/apiary-action-logs', ['year' => '2025']));

    $rows = $this->rows('apiary_action_log', '/hivelog/apiary-action-logs', ['parent' => 'Verdigris']);
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('Verdigris Yard', (string) $rows[0]['cells'][0]);

    $rows = $this->rows('apiary_action_log', '/hivelog/apiary-action-logs', ['action' => 'winter']);
    $this->assertCount(1, $rows);

    $this->pushRoutedRequest('/hivelog/apiary-action-logs', ['status' => 'done']);
    $reset_url = $this->buildList('apiary_action_log')['filter']['filter_actions']['reset']['#props']['url'];
    $this->assertStringContainsString('/hivelog/apiary-action-logs', $reset_url);
    $this->assertStringNotContainsString('status', $reset_url);
  }

  /**
   * Tests the status select offers exactly the entity's allowed statuses.
   */
  public function testStatusOptionsComeFromTheEntityDefinition(): void {
    $this->pushRoutedRequest('/hivelog/hive-action-logs');
    $form = \Drupal::formBuilder()->getForm(HivelogHiveActionLogFilterForm::class);
    $options = $form['filters']['status']['#options'];
    $this->assertSame(['', 'pending', 'done', 'ignored'], array_keys($options));
  }

  /**
   * Tests pagination applies to the filtered set and keeps the filter.
   */
  public function testPaginationOverFilteredSetKeepsFilterInPagerLinks(): void {
    for ($i = 0; $i < 3; $i++) {
      $this->hiveLog($this->varroa, 'done', 2020 + $i);
    }
    $this->hiveLog($this->winter, 'pending', 2026);
    $this->hiveLog($this->winter, 'pending', 2027);

    $list_builder = \Drupal::entityTypeManager()->getListBuilder('hive_action_log');
    (new \ReflectionProperty($list_builder, 'limit'))->setValue($list_builder, 2);

    $this->pushRoutedRequest('/hivelog/hive-action-logs', ['status' => 'done']);
    $build = $list_builder->render();
    $this->assertCount(2, $build['table']['#props']['rows'], 'First page of the 3 filtered rows holds 2.');
    $this->assertSame('pager', $build['pager']['#type']);

    $this->pushRoutedRequest('/hivelog/hive-action-logs', ['status' => 'done', 'page' => '1']);
    $build = $list_builder->render();
    $this->assertCount(1, $build['table']['#props']['rows'], 'Second page holds the remaining filtered row, not unfiltered ones.');

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['pager']);
    $this->assertStringContainsString('status=done', $html, 'Pager links must carry the active filter.');
  }

  /**
   * Tests filtering never reveals a row the user cannot view.
   */
  public function testFilteredListStillHidesInaccessibleRows(): void {
    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    $role->grantPermission('view own hive action log');
    $role->grantPermission('view own hive');
    $role->grantPermission('view own apiary');
    $role->save();
    $owner = User::create(['name' => 'owner', 'mail' => 'owner@example.com']);
    $owner->addRole('beekeeper');
    $owner->save();
    $stranger = User::create(['name' => 'stranger', 'mail' => 'stranger@example.com']);
    $stranger->addRole('beekeeper');
    $stranger->save();

    $private = Apiary::create(['name' => 'Secret Apiary', 'uid' => $owner->id(), 'visibility' => 'private']);
    $private->save();
    $secret_hive = Hive::create([
      'name' => 'Secret Hive',
      'apiary' => $private->id(),
      'status' => 'active',
      'uid' => $owner->id(),
    ]);
    $secret_hive->save();
    $action = CalendarAction::create([
      'apiary' => $private->id(),
      'title' => 'Secret Action',
      'description' => 'Desc.',
      'week_start' => 5,
      'uid' => $owner->id(),
    ]);
    $action->save();
    $log = HiveActionLog::create([
      'hive' => $secret_hive->id(),
      'calendar_action' => $action->id(),
      'status' => 'done',
      'year' => 2026,
      'uid' => $owner->id(),
    ]);
    $log->save();

    \Drupal::currentUser()->setAccount($stranger);
    $this->assertFalse($log->access('view', $stranger), 'Fixture sanity: stranger cannot view the log.');
    $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['parent' => 'Secret', 'status' => 'done']);
    $this->assertCount(0, $rows);

    \Drupal::currentUser()->setAccount($owner);
    $rows = $this->rows('hive_action_log', '/hivelog/hive-action-logs', ['parent' => 'Secret', 'status' => 'done']);
    $this->assertCount(1, $rows);
  }

}
