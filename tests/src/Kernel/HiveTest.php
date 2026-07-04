<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\hivelog\Controller\HiveController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
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
    'file',
    'image',
    'geofield',
    'hivelog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function installQueenEntitySchema(): void {
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
  }

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
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');
    $this->installQueenEntitySchema();
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Creates and saves a managed image file for tests.
   */
  protected function createTestImageFile(string $filename): FileInterface {
    $directory = 'public://hivelog-test';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $uri = $directory . '/' . $filename;
    // A tiny valid 1x1 PNG.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAfbLI3wAAAABJRU5ErkJggg==');
    file_put_contents($uri, $png);
    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => 1,
    ]);
    $file->save();
    return $file;
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
   * Tests that queen info is no longer stored on the hive itself.
   *
   * Queen tracking now lives on the separate Queen entity (issue #51); the
   * hive should not expose queen_year or queen_colour base fields anymore.
   */
  public function testHiveNoLongerStoresQueenInfoDirectly(): void {
    $hive = Hive::create([
      'name' => 'No Inline Queen',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $this->assertFalse($hive->hasField('queen_year'));
    $this->assertFalse($hive->hasField('queen_colour'));
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
   * Tests that attached hive pictures render in a grid below the inspections.
   */
  public function testHiveViewImagesGridBelowInspections(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'images-tester',
      'mail' => 'images-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $file_a = $this->createTestImageFile('hive-photo-a.png');
    $file_b = $this->createTestImageFile('hive-photo-b.png');

    $hive = Hive::create([
      'name' => 'Pictured Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
      'images' => [
        ['target_id' => $file_a->id(), 'alt' => 'Photo A'],
        ['target_id' => $file_b->id(), 'alt' => 'Photo B'],
      ],
    ]);
    $hive->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    // No hero anymore.
    $this->assertArrayNotHasKey('hero', $build);
    $this->assertArrayHasKey('images', $build);
    // Images block weight must be greater than inspections table weight so it
    // sorts below the list of inspections.
    $this->assertGreaterThan(
      $build['inspections_table']['#weight'],
      $build['images']['#weight'],
      'Images grid should sort after the inspections table.'
    );

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringNotContainsString('hivelog-hive-hero', $html);
    $this->assertStringContainsString('hivelog-photos-grid', $html);
    $this->assertStringContainsString('hivelog-photos-grid__item', $html);
    $this->assertStringContainsString('hive-photo-a.png', $html);
    $this->assertStringContainsString('hive-photo-b.png', $html);
    $this->assertStringContainsString('alt="Photo A"', $html);
    $this->assertStringContainsString('alt="Photo B"', $html);

    // Grid must appear after the inspections table in the final HTML.
    $inspections_pos = strpos($html, '<table');
    $grid_pos = strpos($html, 'hivelog-photos-grid');
    $this->assertNotFalse($inspections_pos);
    $this->assertNotFalse($grid_pos);
    $this->assertGreaterThan(
      $inspections_pos,
      $grid_pos,
      'Pictures grid should render below the inspections table in the final HTML.'
    );
  }

  /**
   * Tests that no images grid is rendered when the hive has no images.
   */
  public function testHiveViewImagesGridAbsentWhenNoImage(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'no-images-tester',
      'mail' => 'no-images-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create([
      'name' => 'No Pictures Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    $this->assertArrayNotHasKey('hero', $build);
    $this->assertArrayNotHasKey('images', $build);
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
   * Tests that the hive view renders an empty queen section when no queen exists.
   */
  public function testHiveViewShowsAddQueenWhenNoActiveQueen(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'no-queen-tester',
      'mail' => 'no-queen-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create([
      'name' => 'Queenless Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $this->assertNull($hive->getActiveQueen());

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertArrayHasKey('queen', $build);
    $this->assertStringContainsString('No active queen is recorded for this hive.', $html);
    $this->assertStringContainsString('Add Queen', $html);
  }

  /**
   * Tests that the hive view renders the active queen summary.
   */
  public function testHiveViewShowsActiveQueenDetails(): void {
    $this->installConfig(['system']);

    $user = User::create([
      'name' => 'queen-tester',
      'mail' => 'queen-tester@example.com',
    ]);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $hive = Hive::create([
      'name' => 'Queenright Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    $queen = Queen::create([
      'name' => 'Q-2024-001',
      'hive' => $hive->id(),
      'queen_year' => 2024,
      'introduction_date' => '2024-05-01',
      'status' => 'active',
    ]);
    $queen->save();

    // Add one inspection with a weight so the histogram renders.
    HiveInspection::create([
      'hive' => $hive->id(),
      'inspection_date' => '2024-06-15',
      'weight' => 30.0,
    ])->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(HiveController::class);
    $build = $controller->view($hive);

    // Queen weight must be after the histogram weight so the histogram
    // stays on top.
    $this->assertGreaterThan(
      $build['weight_histogram']['#weight'],
      $build['queen']['#weight'],
      'Queen section should sort after the weight histogram.'
    );
    // And before the inspections heading so it still precedes the list.
    $this->assertLessThan(
      $build['inspections_heading']['#weight'],
      $build['queen']['#weight'],
      'Queen section should sort before the inspections heading.'
    );

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Q-2024-001', $html);
    // The queen's marking colour is derived from the hatch year.
    $this->assertStringContainsString('Green', $html);
    $this->assertStringContainsString('2024-05-01', $html);
    $this->assertStringContainsString('Edit Queen', $html);
    // Issue #55: Add Observation button renders beside Edit Queen.
    $this->assertStringContainsString('Add Observation', $html);
    $edit_pos = strpos($html, 'Edit Queen');
    $add_pos = strpos($html, 'Add Observation');
    $this->assertNotFalse($edit_pos);
    $this->assertNotFalse($add_pos);
    $this->assertLessThan(
      $add_pos,
      $edit_pos,
      'Edit Queen button should render before Add Observation button.'
    );
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

    // Verify Weight column appears before Queen column in the inspections
    // table. Scope the search to the first <table> onward so the Queen
    // section heading (rendered earlier on the page) doesn't confuse the
    // ordering check.
    $table_start = strpos($html, '<table');
    $this->assertNotFalse($table_start);
    $table_html = substr($html, $table_start);
    $weight_pos = strpos($table_html, '>Weight<');
    $queen_pos = strpos($table_html, '>Queen<');
    $this->assertNotFalse($weight_pos);
    $this->assertNotFalse($queen_pos);
    $this->assertLessThan($queen_pos, $weight_pos, 'Weight column should appear before Queen column.');
  }

}
