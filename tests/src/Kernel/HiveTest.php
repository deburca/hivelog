<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Hive entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'options',
    'geofield',
    'hivelog',
  ];

  /**
   * A test apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests basic hive creation and field values.
   */
  public function testCreateHive(): void {
    $hive = Hive::create([
      'name' => 'Hive 1',
      'apiary' => $this->apiary->id(),
      'hive_type' => 'langstroth',
      'hive_material' => 'wood',
      'queen_year' => 2024,
      'bee_breed' => 'buckfast',
      'temperament' => 'calm',
      'status' => 'active',
      'notes' => 'Strong colony.',
    ]);
    $hive->save();

    $loaded = Hive::load($hive->id());
    $this->assertEquals('Hive 1', $loaded->label());
    $this->assertEquals('langstroth', $loaded->get('hive_type')->value);
    $this->assertEquals('wood', $loaded->get('hive_material')->value);
    $this->assertEquals(2024, $loaded->get('queen_year')->value);
    $this->assertEquals('buckfast', $loaded->get('bee_breed')->value);
    $this->assertEquals('calm', $loaded->get('temperament')->value);
    $this->assertEquals('active', $loaded->get('status')->value);
    $this->assertEquals('Strong colony.', $loaded->get('notes')->value);
  }

  /**
   * Tests the apiary entity reference relationship.
   */
  public function testApiaryRelationship(): void {
    $hive = Hive::create([
      'name' => 'Linked Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $loaded = Hive::load($hive->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);

    // Verify the referenced entity can be loaded.
    $referenced_apiary = $loaded->get('apiary')->entity;
    $this->assertNotNull($referenced_apiary);
    $this->assertEquals('Test Apiary', $referenced_apiary->label());
  }

  /**
   * Tests queen colour auto-calculation for all year endings.
   *
   * International queen marking colours:
   *   White: years ending 1, 6
   *   Yellow: years ending 2, 7
   *   Red: years ending 3, 8
   *   Green: years ending 4, 9
   *   Blue: years ending 0, 5
   */
  public function testQueenColourAutoCalculation(): void {
    $expected = [
      2020 => 'blue',
      2021 => 'white',
      2022 => 'yellow',
      2023 => 'red',
      2024 => 'green',
      2025 => 'blue',
      2026 => 'white',
      2027 => 'yellow',
      2028 => 'red',
      2029 => 'green',
    ];

    foreach ($expected as $year => $colour) {
      $hive = Hive::create([
        'name' => "Hive $year",
        'apiary' => $this->apiary->id(),
        'queen_year' => $year,
        'status' => 'active',
      ]);
      $hive->save();

      $loaded = Hive::load($hive->id());
      $this->assertEquals(
        $colour,
        $loaded->get('queen_colour')->value,
        "Year $year should produce colour '$colour'."
      );
    }
  }

  /**
   * Tests that queen colour is empty when no queen year is set.
   */
  public function testQueenColourWithoutYear(): void {
    $hive = Hive::create([
      'name' => 'No Queen Year',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $loaded = Hive::load($hive->id());
    $this->assertEmpty($loaded->get('queen_colour')->value);
  }

  /**
   * Tests that queen colour updates when queen year changes.
   */
  public function testQueenColourUpdatesOnYearChange(): void {
    $hive = Hive::create([
      'name' => 'Requeened Hive',
      'apiary' => $this->apiary->id(),
      'queen_year' => 2023,
      'status' => 'active',
    ]);
    $hive->save();

    $loaded = Hive::load($hive->id());
    $this->assertEquals('red', $loaded->get('queen_colour')->value);

    // Simulate requeening in a new year.
    $loaded->set('queen_year', 2025);
    $loaded->save();

    $reloaded = Hive::load($hive->id());
    $this->assertEquals('blue', $reloaded->get('queen_colour')->value);
  }

  /**
   * Tests all hive type options.
   */
  public function testHiveTypes(): void {
    $types = ['10x12', 'norwegian', 'langstroth', 'trugstad', 'normal'];
    foreach ($types as $type) {
      $hive = Hive::create([
        'name' => "Hive type $type",
        'apiary' => $this->apiary->id(),
        'hive_type' => $type,
        'status' => 'active',
      ]);
      $hive->save();

      $loaded = Hive::load($hive->id());
      $this->assertEquals($type, $loaded->get('hive_type')->value);
    }
  }

  /**
   * Tests all hive material options.
   */
  public function testHiveMaterials(): void {
    $materials = ['wood', 'styrofoam'];
    foreach ($materials as $material) {
      $hive = Hive::create([
        'name' => "Hive $material",
        'apiary' => $this->apiary->id(),
        'hive_material' => $material,
        'status' => 'active',
      ]);
      $hive->save();

      $loaded = Hive::load($hive->id());
      $this->assertEquals($material, $loaded->get('hive_material')->value);
    }
  }

  /**
   * Tests all hive status options.
   */
  public function testHiveStatuses(): void {
    $statuses = ['active', 'inactive', 'dead', 'sold', 'merged'];
    foreach ($statuses as $status) {
      $hive = Hive::create([
        'name' => "Hive $status",
        'apiary' => $this->apiary->id(),
        'status' => $status,
      ]);
      $hive->save();

      $loaded = Hive::load($hive->id());
      $this->assertEquals($status, $loaded->get('status')->value);
    }
  }

  /**
   * Tests updating a hive.
   */
  public function testUpdateHive(): void {
    $hive = Hive::create([
      'name' => 'Original Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'bee_breed' => 'italian',
    ]);
    $hive->save();

    $hive->set('name', 'Renamed Hive');
    $hive->set('bee_breed', 'buckfast');
    $hive->set('status', 'merged');
    $hive->save();

    $loaded = Hive::load($hive->id());
    $this->assertEquals('Renamed Hive', $loaded->label());
    $this->assertEquals('buckfast', $loaded->get('bee_breed')->value);
    $this->assertEquals('merged', $loaded->get('status')->value);
  }

  /**
   * Tests deleting a hive.
   */
  public function testDeleteHive(): void {
    $hive = Hive::create([
      'name' => 'To Delete',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();
    $id = $hive->id();

    $hive->delete();
    $this->assertNull(Hive::load($id));
  }

  /**
   * Tests that the weight histogram renders above the inspection list.
   */
  public function testHiveViewWeightHistogram(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'histogram-tester',
      'mail' => 'histogram-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create([
      'name' => 'Histogram Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    // Most recent year: 2025 — two inspections with weights.
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
    // Earlier year inspection — should be excluded from the histogram.
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-01',
      'weight' => 22.0,
    ])->save();
    // Inspection in the most recent year without a weight — should be ignored.
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2025-06-06',
    ])->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertArrayHasKey('weight_histogram', $build);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    // Letterboxed container is present.
    $this->assertStringContainsString('hivelog-weight-histogram--letterboxed', $html);
    $this->assertStringContainsString('<svg', $html);
    // Heading references the most recent year.
    $this->assertStringContainsString('Inspection weights for 2025', $html);
    // Bars for the two 2025 inspections with weights.
    $this->assertStringContainsString('28.5 kg', $html);
    $this->assertStringContainsString('35.25 kg', $html);
    // mm/dd labels.
    $this->assertStringContainsString('05/03', $html);
    $this->assertStringContainsString('07/12', $html);

    // Isolate the SVG markup so we can assert the 2024 inspection weight
    // (22 kg) is not drawn as a bar in the histogram. It is still expected
    // to appear later in the inspections table row for that entry.
    $svg_start = strpos($html, '<svg');
    $svg_end = strpos($html, '</svg>');
    $this->assertNotFalse($svg_start);
    $this->assertNotFalse($svg_end);
    $svg = substr($html, $svg_start, $svg_end - $svg_start);
    $this->assertStringNotContainsString('22 kg', $svg);
    $this->assertStringNotContainsString('06/01', $svg);

    // Histogram must appear before the inspections table.
    $histogram_pos = strpos($html, 'hivelog-weight-histogram');
    $table_pos = strpos($html, '<table');
    $this->assertNotFalse($histogram_pos);
    $this->assertNotFalse($table_pos);
    $this->assertLessThan($table_pos, $histogram_pos, 'Histogram should appear before the inspections table.');
  }

  /**
   * Tests that no histogram is rendered when no inspections have weights.
   */
  public function testHiveViewWeightHistogramAbsentWhenNoData(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'no-histogram-tester',
      'mail' => 'no-histogram-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create([
      'name' => 'No Weight Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2025-05-03',
    ])->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertArrayNotHasKey('weight_histogram', $build);
  }

  /**
   * Tests that the hive view inspection table includes weight before queen.
   */
  public function testHiveViewInspectionTableWeightColumn(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'tester',
      'mail' => 'tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create([
      'name' => 'Weight Column Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $inspection = HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
      'weight' => 32.5,
      'queen_seen' => TRUE,
    ]);
    $inspection->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    // Verify the Weight header and value are present.
    $this->assertStringContainsString('Weight', $html);
    $this->assertStringContainsString('32.5 kg', $html);

    // Verify Weight column appears before Queen column in the header.
    $weight_pos = strpos($html, '>Weight<');
    $queen_pos = strpos($html, '>Queen<');
    $this->assertNotFalse($weight_pos);
    $this->assertNotFalse($queen_pos);
    $this->assertLessThan($queen_pos, $weight_pos, 'Weight column should appear before Queen column.');
  }

}
