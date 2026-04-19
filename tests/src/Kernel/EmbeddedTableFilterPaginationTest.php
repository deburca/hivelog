<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
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
    $request = Request::create('/admin/hivelog/apiary/1', 'GET', $query);
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
      $build['hives_table']['#rows'],
      'First page should contain exactly HIVES_PER_PAGE rows.'
    );

    // Request the second page and verify we see the remainder.
    $this->pushRequestWithQuery(['page' => '1']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($apiary);
    $this->assertCount(5, $build['hives_table']['#rows'], 'Second page should contain the remaining rows.');
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
      (string) $build['hives_table']['#empty']
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

    $this->assertArrayHasKey('inspections_table', $build);
    $this->assertArrayHasKey('inspections_pager', $build);
    $this->assertCount(
      HiveController::INSPECTIONS_PER_PAGE,
      $build['inspections_table']['#rows'],
      'First page should contain exactly INSPECTIONS_PER_PAGE rows.'
    );

    $this->pushRequestWithQuery(['page' => '1']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $this->assertCount(3, $build['inspections_table']['#rows']);
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

    $this->assertCount(1, $build['inspections_table']['#rows']);
    $this->assertEquals('2024-06-01', (string) $build['inspections_table']['#rows'][0][0]);
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
    $this->assertCount(1, $build['inspections_table']['#rows']);
    $this->assertEquals('2024-05-01', (string) $build['inspections_table']['#rows'][0][0]);

    $this->pushRequestWithQuery(['queen_seen' => '0']);
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $this->assertCount(1, $build['inspections_table']['#rows']);
    $this->assertEquals('2024-05-02', (string) $build['inspections_table']['#rows'][0][0]);
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
    $this->assertCount(1, $build['inspections_table']['#rows']);

    // Histogram should still summarise the most recent year (2025).
    $this->assertArrayHasKey('weight_histogram', $build);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Inspection weights for 2025', $html);
    $this->assertStringContainsString('28.5 kg', $html);
    $this->assertStringContainsString('35.25 kg', $html);
  }

  /**
   * Tests that the filter form is rendered with a GET method and preserves
   * submitted values as defaults.
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
    $this->assertContains(
      'hivelog-list-heading__action',
      $build['hives_heading']['add']['#attributes']['class']
    );
    $this->assertContains(
      'hivelog-list-heading',
      $build['hives_heading']['#attributes']['class']
    );
    $this->assertArrayNotHasKey('hives_toolbar', $build);
  }

}
