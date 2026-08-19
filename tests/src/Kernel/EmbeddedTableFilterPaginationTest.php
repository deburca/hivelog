<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that embedded child tables support pagination and filtering.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class EmbeddedTableFilterPaginationTest extends KernelTestBase {

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
  }

  /**
   * Pushes a request with the given query parameters onto the request stack.
   */
  protected function pushRequestWithQuery(array $query): void {
    $request = Request::create('/hivelog/apiary/1', 'GET', $query);
    // FormBuilder requires a session on the current request.
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);
  }

  /**
   * Tests pagination limits the number of hives rendered on the apiary page.
   */
  public function testApiaryHiveTablePaginates(): void {
    $apiary = Apiary::create(['name' => 'Big Apiary']);
    $apiary->save();

    // Seed more hives than the per-page threshold.
    $total = ApiaryController::HIVES_PER_PAGE + 5;
    for ($i = 1; $i <= $total; $i++) {
      Hive::create([
        'name' => sprintf('Hive %03d', $i),
        'apiary' => $apiary->id(),
        'status' => 'active',
      ])->save();
    }

    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $this->assertArrayHasKey('hives_table', $build);
    $this->assertArrayHasKey('hives_pager', $build);
    $this->assertEquals('pager', $build['hives_pager']['#type']);
    $this->assertCount(
      ApiaryController::HIVES_PER_PAGE,
      $build['hives_table']['#props']['rows'],
      'First page should contain exactly HIVES_PER_PAGE rows.'
    );

    // Request the second page and verify we see the remainder.
    $this->pushRequestWithQuery(['page' => '1']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);
    $this->assertCount(5, $build['hives_table']['#props']['rows'], 'Second page should contain the remaining rows.');
  }

  /**
   * Tests the hive status filter on the apiary page.
   */
  public function testApiaryHiveTableStatusFilter(): void {
    $apiary = Apiary::create(['name' => 'Filter Apiary']);
    $apiary->save();

    Hive::create([
      'name' => 'Alpha Active',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();
    Hive::create([
      'name' => 'Beta Inactive',
      'apiary' => $apiary->id(),
      'status' => 'inactive',
    ])->save();
    Hive::create([
      'name' => 'Gamma Dead',
      'apiary' => $apiary->id(),
      'status' => 'dead',
    ])->save();

    $this->pushRequestWithQuery(['status' => 'inactive']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Beta Inactive', $html);
    $this->assertStringNotContainsString('Alpha Active', $html);
    $this->assertStringNotContainsString('Gamma Dead', $html);
  }

  /**
   * Tests the hive breed filter on the apiary page.
   *
   * Breed lives on the active queen, not the hive (see
   * ApiaryController::hiveIdsForActiveQueenBreed()), so this exercises the
   * query that resolves hives via their active queen's breed.
   */
  public function testApiaryHiveTableBreedFilter(): void {
    $apiary = Apiary::create(['name' => 'Breed Filter Apiary']);
    $apiary->save();

    $buckfast_hive = Hive::create([
      'name' => 'Buckfast Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $buckfast_hive->save();
    \Drupal::entityTypeManager()->getStorage('queen')->create([
      'name' => 'Q-Buckfast',
      'hive' => $buckfast_hive->id(),
      'breed' => 'buckfast',
      'status' => 'active',
    ])->save();

    $italian_hive = Hive::create([
      'name' => 'Italian Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $italian_hive->save();
    \Drupal::entityTypeManager()->getStorage('queen')->create([
      'name' => 'Q-Italian',
      'hive' => $italian_hive->id(),
      'breed' => 'italian',
      'status' => 'active',
    ])->save();

    // No active queen at all — must never match a breed filter.
    Hive::create([
      'name' => 'Queenless Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();

    $this->pushRequestWithQuery(['breed' => 'buckfast']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Buckfast Hive', $html);
    $this->assertStringNotContainsString('Italian Hive', $html);
    $this->assertStringNotContainsString('Queenless Hive', $html);
  }

  /**
   * Tests the hive name substring filter on the apiary page.
   */
  public function testApiaryHiveTableNameFilter(): void {
    $apiary = Apiary::create(['name' => 'Name Filter Apiary']);
    $apiary->save();

    Hive::create([
      'name' => 'Willow Queen',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();
    Hive::create([
      'name' => 'Oak Queen',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();
    Hive::create([
      'name' => 'Ash Worker',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();

    $this->pushRequestWithQuery(['name' => 'Queen']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Willow Queen', $html);
    $this->assertStringContainsString('Oak Queen', $html);
    $this->assertStringNotContainsString('Ash Worker', $html);
  }

  /**
   * Tests the empty-filters message on the apiary page.
   */
  public function testApiaryHiveTableFilteredEmptyMessage(): void {
    $apiary = Apiary::create(['name' => 'Empty Filters Apiary']);
    $apiary->save();

    Hive::create([
      'name' => 'Sole Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();

    $this->pushRequestWithQuery(['status' => 'dead']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $this->assertEquals(
      'No hives match the current filters.',
      $build['hives_table']['#props']['empty_message']
    );
  }

  /**
   * Tests pagination on the inspections table on the hive page.
   */
  public function testHiveInspectionTablePaginates(): void {
    $apiary = Apiary::create(['name' => 'Pager Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Pager Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $total = HiveController::INSPECTIONS_PER_PAGE + 3;
    for ($i = 1; $i <= $total; $i++) {
      HiveInspection::create([
        'hive' => $hive->id(),
        // Distinct dates so ordering is deterministic.
        'inspection_date' => sprintf('2024-01-%02d', $i),
      ])->save();
    }

    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertArrayHasKey('inspections', $build['hive_activity']);
    $this->assertArrayHasKey('pager', $build['hive_activity']['inspections']);
    $this->assertCount(
      HiveController::INSPECTIONS_PER_PAGE,
      $build['hive_activity']['inspections']['table']['#props']['rows'],
      'First page should contain exactly INSPECTIONS_PER_PAGE rows.'
    );

    $this->pushRequestWithQuery(['page' => '1']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $this->assertCount(3, $build['hive_activity']['inspections']['table']['#props']['rows']);
  }

  /**
   * Tests date range filtering on the inspections table.
   */
  public function testHiveInspectionTableDateFilter(): void {
    $apiary = Apiary::create(['name' => 'Date Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Date Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-03-01',
      'notes' => 'March inspection',
    ])->save();
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-01',
      'notes' => 'June inspection',
    ])->save();
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-09-01',
      'notes' => 'September inspection',
    ])->save();

    $this->pushRequestWithQuery([
      'date_from' => '2024-05-01',
      'date_to' => '2024-08-01',
    ]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertCount(1, $build['hive_activity']['inspections']['table']['#props']['rows']);
    $this->assertEquals('2024-06-01', (string) $build['hive_activity']['inspections']['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Tests queen seen filter on the inspections table.
   */
  public function testHiveInspectionTableQueenSeenFilter(): void {
    $apiary = Apiary::create(['name' => 'Queen Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Queen Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-05-01',
      'queen_seen' => TRUE,
    ])->save();
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-05-02',
      'queen_seen' => FALSE,
    ])->save();

    $this->pushRequestWithQuery(['queen_seen' => '1']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $this->assertCount(1, $build['hive_activity']['inspections']['table']['#props']['rows']);
    $this->assertEquals('2024-05-01', (string) $build['hive_activity']['inspections']['table']['#props']['rows'][0]['cells'][0]);

    $this->pushRequestWithQuery(['queen_seen' => '0']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $this->assertCount(1, $build['hive_activity']['inspections']['table']['#props']['rows']);
    $this->assertEquals('2024-05-02', (string) $build['hive_activity']['inspections']['table']['#props']['rows'][0]['cells'][0]);
  }

  /**
   * Tests the date range filter on the Queen Observations table.
   */
  public function testHiveObservationsTableDateFilter(): void {
    $apiary = Apiary::create(['name' => 'Observation Date Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Observation Date Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();
    $queen = Queen::create([
      'name' => 'Q-obs-date',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $queen->save();

    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-03-01',
    ])->save();
    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-06-01',
    ])->save();
    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-09-01',
    ])->save();

    $this->pushRequestWithQuery([
      'obs_date_from' => '2024-05-01',
      'obs_date_to' => '2024-08-01',
    ]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $rows = $build['hive_activity']['observations']['table']['#props']['rows'];
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('2024-06-01', (string) $rows[0]['cells'][0]);
  }

  /**
   * Tests the health filter on the Queen Observations table.
   */
  public function testHiveObservationsTableHealthFilter(): void {
    $apiary = Apiary::create(['name' => 'Observation Health Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Observation Health Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();
    $queen = Queen::create([
      'name' => 'Q-obs-health',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $queen->save();

    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-05-01',
      'health' => 'excellent',
    ])->save();
    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-05-02',
      'health' => 'poor',
    ])->save();

    $this->pushRequestWithQuery(['obs_health' => 'poor']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $rows = $build['hive_activity']['observations']['table']['#props']['rows'];
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('2024-05-02', (string) $rows[0]['cells'][0]);
  }

  /**
   * Tests the active (laying) filter on the Queen Observations table.
   */
  public function testHiveObservationsTableActiveFilter(): void {
    $apiary = Apiary::create(['name' => 'Observation Active Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Observation Active Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();
    $queen = Queen::create([
      'name' => 'Q-obs-active',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $queen->save();

    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-05-01',
      'active' => TRUE,
    ])->save();
    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-05-02',
      'active' => FALSE,
    ])->save();

    $this->pushRequestWithQuery(['obs_active' => '0']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $rows = $build['hive_activity']['observations']['table']['#props']['rows'];
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('2024-05-02', (string) $rows[0]['cells'][0]);
  }

  /**
   * Tests the Queen filter on the Queen Observations table, and that the
   * filter option only appears once a hive has had more than one queen.
   */
  public function testHiveObservationsTableQueenFilter(): void {
    $apiary = Apiary::create(['name' => 'Observation Queen Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Observation Queen Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $first_queen = Queen::create([
      'name' => 'Q-obs-first',
      'hive' => $hive->id(),
      'queen_year' => 2023,
      'status' => 'active',
    ]);
    $first_queen->save();
    QueenObservation::create([
      'queen' => $first_queen->id(),
      'observation_date' => '2023-06-01',
    ])->save();

    // Only one queen so far: no Queen filter should be offered yet.
    $this->pushRequestWithQuery([]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $this->assertArrayNotHasKey('obs_queen', $build['hive_activity']['observations']['filter']['filters']);

    $second_queen = Queen::create([
      'name' => 'Q-obs-second',
      'hive' => $hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
    ]);
    $second_queen->save();
    QueenObservation::create([
      'queen' => $second_queen->id(),
      'observation_date' => '2025-06-01',
    ])->save();

    $build = $controller->view($hive);
    $this->assertArrayHasKey('obs_queen', $build['hive_activity']['observations']['filter']['filters']);

    $this->pushRequestWithQuery(['obs_queen' => $first_queen->id()]);
    $build = $controller->view($hive);
    $rows = $build['hive_activity']['observations']['table']['#props']['rows'];
    $this->assertCount(1, $rows);
    $this->assertStringContainsString('2023-06-01', (string) $rows[0]['cells'][0]);
  }

  /**
   * Tests that the inspection and observation filter forms don't collide.
   *
   * Both forms are GET forms sharing the same page's query string; the
   * observation filter keys are prefixed `obs_` specifically so that,
   * e.g., filtering inspections by date does not also filter
   * observations by date (and vice versa).
   */
  public function testInspectionAndObservationFiltersAreIndependent(): void {
    $apiary = Apiary::create(['name' => 'Independent Filters Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Independent Filters Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();
    $queen = Queen::create([
      'name' => 'Q-independent',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'status' => 'active',
    ]);
    $queen->save();

    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-05-01',
    ])->save();
    QueenObservation::create([
      'queen' => $queen->id(),
      'observation_date' => '2024-09-01',
    ])->save();

    // Filtering inspections to a window that excludes the inspection above
    // must not affect the (differently-dated) observation.
    $this->pushRequestWithQuery([
      'date_from' => '2024-01-01',
      'date_to' => '2024-01-31',
    ]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertCount(0, $build['hive_activity']['inspections']['table']['#props']['rows']);
    $this->assertCount(1, $build['hive_activity']['observations']['table']['#props']['rows']);
  }

  /**
   * Tests that the weight histogram is unaffected by filters/pagination.
   */
  public function testWeightHistogramIsNotFiltered(): void {
    $apiary = Apiary::create(['name' => 'Histogram Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'Histogram Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    // Two 2025 inspections, with weights; one 2024 inspection, with weight.
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2025-05-03',
      'weight' => 28.5,
    ])->save();
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2025-07-12',
      'weight' => 35.25,
    ])->save();
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-01',
      'weight' => 22.0,
    ])->save();

    // Restrict the table to a window that excludes the 2025 data.
    $this->pushRequestWithQuery([
      'date_from' => '2024-01-01',
      'date_to' => '2024-12-31',
    ]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    // Table should reflect the filter (only 2024 row).
    $this->assertCount(1, $build['hive_activity']['inspections']['table']['#props']['rows']);

    // Histogram should still summarise the most recent year (2025).
    $this->assertArrayHasKey('weight_histogram', $build);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Inspection weights for 2025', $html);
    $this->assertStringContainsString('28.5 kg', $html);
    $this->assertStringContainsString('35.25 kg', $html);
  }

  /**
   * Tests that the filter form uses GET and preserves submitted values.
   */
  public function testFilterFormIsGetAndPreservesDefaults(): void {
    $apiary = Apiary::create(['name' => 'Form Apiary']);
    $apiary->save();
    Hive::create([
      'name' => 'A Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ])->save();

    $this->pushRequestWithQuery([
      'status' => 'active',
      'name' => 'Hive',
    ]);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);

    $filter = $build['hives_filter'];
    $this->assertEquals('get', $filter['#method']);
    $this->assertEquals('active', $filter['filters']['status']['#default_value']);
    $this->assertEquals('Hive', $filter['filters']['name']['#default_value']);

    // The Add Hive action lives in the heading row (top-right of the list
    // section), not inline with the filter form.
    $this->assertArrayHasKey('add', $build['hives_heading']);
    $this->assertEquals('hivelog:button', $build['hives_heading']['add']['#component']);
    $this->assertStringContainsString(
      'hivelog-list-heading__action',
      $build['hives_heading']['add']['#props']['extra_classes']
    );
    $this->assertContains(
      'hivelog-list-heading',
      $build['hives_heading']['#attributes']['class']
    );
    $this->assertArrayNotHasKey('hives_toolbar', $build);
  }

}
