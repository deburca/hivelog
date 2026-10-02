<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests filters on the full Hives, Inspections and Queen Observations lists.
 *
 * Task 0132: `/hivelog/hives`, `/hivelog/inspections` and
 * `/hivelog/queen-observations` (previously unfiltered) now carry the
 * same `HivelogHiveFilterForm` / `HivelogInspectionFilterForm` /
 * `HivelogQueenObservationFilterForm` as their embedded versions — built
 * with no parent entity, so Reset targets the collection route instead
 * of a parent's canonical page.
 *
 * The embedded tables' own filtering (with a parent) is
 * `EmbeddedTableFilterPaginationTest`'s job, unchanged by this task —
 * confirmed still green after the extract()/apply() logic moved onto
 * the form classes.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class FullListFilterTest extends KernelTestBase {

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
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Pushes a request onto the stack, routed as `$route_name`.
   *
   * Matches the request through the real router first, so
   * `current_route_match` (and therefore `Url::fromRoute('<current>')`,
   * which Reset relies on with no parent entity) resolves to this route
   * — a bare `Request::create()` alone leaves no route attributes set.
   */
  protected function pushRoutedRequest(string $route_name, string $path, array $query = []): void {
    $request = Request::create($path, 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->attributes->add(\Drupal::service('router')->matchRequest($request));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests the full Hives list filters by status and Reset clears it.
   */
  public function testHiveListStatusFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    Hive::create(['name' => 'Active Hive', 'apiary' => $apiary->id(), 'status' => 'active'])->save();
    Hive::create(['name' => 'Inactive Hive', 'apiary' => $apiary->id(), 'status' => 'inactive'])->save();

    $this->pushRoutedRequest('entity.hive.collection', '/hivelog/hives', ['status' => 'active']);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('Active Hive', (string) $build['table']['#props']['rows'][0]['cells'][0]);
    $this->assertArrayHasKey('filter', $build);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/hives', $reset_url);
    $this->assertStringNotContainsString('status', $reset_url);
  }

  /**
   * Tests the full Hives list's empty state names the active filter.
   */
  public function testHiveListEmptyStateDistinguishesFilteredFromUnfiltered(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    Hive::create(['name' => 'Active Hive', 'apiary' => $apiary->id(), 'status' => 'active'])->save();

    $this->pushRoutedRequest('entity.hive.collection', '/hivelog/hives');
    $build = \Drupal::entityTypeManager()->getListBuilder('hive')->render();
    $this->assertStringContainsString('There are no', $build['table']['#props']['empty_message']);

    $this->pushRoutedRequest('entity.hive.collection', '/hivelog/hives', ['status' => 'inactive']);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive')->render();
    $this->assertCount(0, $build['table']['#props']['rows']);
    $this->assertStringContainsString('match the current filters', $build['table']['#props']['empty_message']);
  }

  /**
   * Tests the full Inspections list filters by date and Reset clears it.
   */
  public function testInspectionListDateFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-01-15'])->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-06-15'])->save();

    $this->pushRoutedRequest('entity.hive_inspection.collection', '/hivelog/inspections', ['date_from' => '2026-05-01']);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive_inspection')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/inspections', $reset_url);
    $this->assertStringNotContainsString('date_from', $reset_url);
  }

  /**
   * Tests the full Queen Observations list filters by health (obs_ prefix).
   */
  public function testQueenObservationListHealthFilterAndReset(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $queen = Queen::create(['name' => 'Q1', 'hive' => $hive->id(), 'queen_year' => 2025, 'status' => 'active']);
    $queen->save();
    QueenObservation::create(['queen' => $queen->id(), 'observation_date' => '2026-01-01', 'health' => 'good'])->save();
    QueenObservation::create(['queen' => $queen->id(), 'observation_date' => '2026-01-02', 'health' => 'poor'])->save();

    $this->pushRoutedRequest('entity.queen_observation.collection', '/hivelog/queen-observations', ['obs_health' => 'poor']);
    $build = \Drupal::entityTypeManager()->getListBuilder('queen_observation')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);

    $reset_url = $this->resetUrl($build);
    $this->assertStringContainsString('/hivelog/queen-observations', $reset_url);
    $this->assertStringNotContainsString('obs_health', $reset_url);
  }

  /**
   * Tests a reversed Inspections date range is swapped, not silently empty.
   *
   * Task 0169: "From" later than "To" used to match nothing with no
   * explanation.
   */
  public function testInspectionListReversedDateRangeIsSwappedWithNotice(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-01-15'])->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-06-15'])->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-12-15'])->save();

    $this->pushRoutedRequest('entity.hive_inspection.collection', '/hivelog/inspections', [
      'date_from' => '2026-09-01',
      'date_to' => '2026-03-01',
    ]);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive_inspection')->render();

    $this->assertCount(1, $build['table']['#props']['rows'], 'Swapped to Mar-Sep: only the June inspection.');
    $this->assertStringContainsString('swapped', $build['filter']['range_notice']['#markup']);
    $this->assertEquals('2026-03-01', $build['filter']['filters']['date_from']['#default_value']);
    $this->assertEquals('2026-09-01', $build['filter']['filters']['date_to']['#default_value']);
  }

  /**
   * Tests a malformed Inspections date is ignored with a notice.
   */
  public function testInspectionListMalformedDateIsIgnoredWithNotice(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-01-15'])->save();
    HiveInspection::create(['hive' => $hive->id(), 'inspection_date' => '2026-06-15'])->save();

    $this->pushRoutedRequest('entity.hive_inspection.collection', '/hivelog/inspections', ['date_from' => 'banana']);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive_inspection')->render();

    $this->assertCount(2, $build['table']['#props']['rows'], 'The bad date is dropped, so nothing is filtered out.');
    $this->assertStringContainsString('not a valid date', $build['filter']['range_notice']['#markup']);
  }

  /**
   * Tests a clean range shows no notice at all.
   */
  public function testInspectionListValidRangeShowsNoNotice(): void {
    $this->pushRoutedRequest('entity.hive_inspection.collection', '/hivelog/inspections', [
      'date_from' => '2026-01-01',
      'date_to' => '2026-12-31',
    ]);
    $build = \Drupal::entityTypeManager()->getListBuilder('hive_inspection')->render();

    $this->assertArrayNotHasKey('range_notice', $build['filter']);
  }

  /**
   * Tests a reversed Queen Observations range (obs_ prefix) is swapped.
   */
  public function testQueenObservationListReversedDateRangeIsSwappedWithNotice(): void {
    $apiary = Apiary::create(['name' => 'Test Apiary']);
    $apiary->save();
    $hive = Hive::create(['name' => 'Test Hive', 'apiary' => $apiary->id(), 'status' => 'active']);
    $hive->save();
    $queen = Queen::create(['name' => 'Q1', 'hive' => $hive->id(), 'queen_year' => 2025, 'status' => 'active']);
    $queen->save();
    QueenObservation::create(['queen' => $queen->id(), 'observation_date' => '2026-01-15', 'health' => 'good'])->save();
    QueenObservation::create(['queen' => $queen->id(), 'observation_date' => '2026-06-15', 'health' => 'good'])->save();

    $this->pushRoutedRequest('entity.queen_observation.collection', '/hivelog/queen-observations', [
      'obs_date_from' => '2026-09-01',
      'obs_date_to' => '2026-03-01',
    ]);
    $build = \Drupal::entityTypeManager()->getListBuilder('queen_observation')->render();

    $this->assertCount(1, $build['table']['#props']['rows']);
    $this->assertStringContainsString('swapped', $build['filter']['range_notice']['#markup']);
  }

  /**
   * Extracts the rendered Reset button's URL from a list builder's build.
   */
  protected function resetUrl(array $build): string {
    $reset = $build['filter']['filter_actions']['reset']['#props']['url'] ?? NULL;
    $this->assertNotNull($reset, 'Reset button was not rendered.');
    return $reset;
  }

}
