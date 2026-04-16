<?php

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the Hive Inspection entity.
 *
 * @group hivelog
 */
class HiveInspectionTest extends KernelTestBase {

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
   * A test hive.
   *
   * @var \Drupal\hivelog\Entity\Hive
   */
  protected Hive $hive;

  /**
   * A test apiary.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiary;

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('hive_inspection');

    $this->user = User::create([
      'name' => 'inspector',
      'mail' => 'inspector@example.com',
    ]);
    $this->user->save();

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Hive Alpha',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();
  }

  /**
   * Tests creating an inspection with all fields populated.
   */
  public function testCreateFullInspection(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'queen_seen' => TRUE,
      'queen_cells' => FALSE,
      'eggs_seen' => TRUE,
      'brood_pattern' => 'good',
      'queen_brood' => TRUE,
      'honey_stores' => 'abundant',
      'pollen_stores' => 'adequate',
      'temperament' => 'calm',
      'population' => 'strong',
      'varroa_check' => TRUE,
      'varroa_count' => 3,
      'disease_signs' => 'none',
      'fed' => FALSE,
      'feed_type' => '',
      'supers' => 2,
      'action_taken' => 'Added super, checked for swarm cells.',
      'notes' => 'Excellent colony. No issues.',
      'uid' => $this->user->id(),
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());

    // Verify all fields.
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals('2024-06-15', $loaded->get('inspection_date')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('queen_seen')->value);
    $this->assertEquals(FALSE, (bool) $loaded->get('queen_cells')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('eggs_seen')->value);
    $this->assertEquals('good', $loaded->get('brood_pattern')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('queen_brood')->value);
    $this->assertEquals('abundant', $loaded->get('honey_stores')->value);
    $this->assertEquals('adequate', $loaded->get('pollen_stores')->value);
    $this->assertEquals('calm', $loaded->get('temperament')->value);
    $this->assertEquals('strong', $loaded->get('population')->value);
    $this->assertEquals(TRUE, (bool) $loaded->get('varroa_check')->value);
    $this->assertEquals(3, $loaded->get('varroa_count')->value);
    $this->assertEquals('none', $loaded->get('disease_signs')->value);
    $this->assertEquals(FALSE, (bool) $loaded->get('fed')->value);
    $this->assertEquals(2, $loaded->get('supers')->value);
    $this->assertEquals('Added super, checked for swarm cells.', $loaded->get('action_taken')->value);
    $this->assertEquals('Excellent colony. No issues.', $loaded->get('notes')->value);
    $this->assertEquals($this->user->id(), $loaded->getOwnerId());
  }

  /**
   * Tests the hive entity reference relationship.
   */
  public function testHiveRelationship(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-07-01',
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $referenced_hive = $loaded->get('hive')->entity;
    $this->assertNotNull($referenced_hive);
    $this->assertEquals('Hive Alpha', $referenced_hive->label());

    // Verify the full chain: inspection → hive → apiary.
    $referenced_apiary = $referenced_hive->get('apiary')->entity;
    $this->assertNotNull($referenced_apiary);
    $this->assertEquals('Test Apiary', $referenced_apiary->label());
  }

  /**
   * Tests the dynamic label generation.
   */
  public function testInspectionLabel(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-08-20',
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $label = (string) $loaded->label();
    $this->assertStringContainsString('Hive Alpha', $label);
    $this->assertStringContainsString('2024-08-20', $label);
  }

  /**
   * Tests that multiple inspections can reference the same hive.
   */
  public function testMultipleInspectionsPerHive(): void {
    $dates = ['2024-04-01', '2024-05-01', '2024-06-01'];
    $ids = [];

    foreach ($dates as $date) {
      $inspection = HiveInspection::create([
        'hive' => $this->hive->id(),
        'inspection_date' => $date,
      ]);
      $inspection->save();
      $ids[] = $inspection->id();
    }

    // Verify all three inspections exist and reference the same hive.
    foreach ($ids as $id) {
      $loaded = HiveInspection::load($id);
      $this->assertNotNull($loaded);
      $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    }

    // Verify we can query inspections by hive.
    $results = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->loadByProperties(['hive' => $this->hive->id()]);
    $this->assertCount(3, $results);
  }

  /**
   * Tests all brood pattern options.
   */
  public function testBroodPatternOptions(): void {
    $patterns = ['good', 'fair', 'poor', 'none'];
    foreach ($patterns as $pattern) {
      $inspection = HiveInspection::create([
        'hive' => $this->hive->id(),
        'inspection_date' => '2024-06-01',
        'brood_pattern' => $pattern,
      ]);
      $inspection->save();

      $loaded = HiveInspection::load($inspection->id());
      $this->assertEquals($pattern, $loaded->get('brood_pattern')->value);
    }
  }

  /**
   * Tests all disease sign options.
   */
  public function testDiseaseSignOptions(): void {
    $diseases = ['none', 'nosema', 'chalkbrood', 'efb', 'afb', 'sacbrood', 'other'];
    foreach ($diseases as $disease) {
      $inspection = HiveInspection::create([
        'hive' => $this->hive->id(),
        'inspection_date' => '2024-06-01',
        'disease_signs' => $disease,
      ]);
      $inspection->save();

      $loaded = HiveInspection::load($inspection->id());
      $this->assertEquals($disease, $loaded->get('disease_signs')->value);
    }
  }

  /**
   * Tests feeding fields together.
   */
  public function testFeedingFields(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-10-01',
      'fed' => TRUE,
      'feed_type' => 'Sugar syrup 2:1',
    ]);
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $this->assertEquals(TRUE, (bool) $loaded->get('fed')->value);
    $this->assertEquals('Sugar syrup 2:1', $loaded->get('feed_type')->value);
  }

  /**
   * Tests updating an inspection.
   */
  public function testUpdateInspection(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
      'queen_seen' => FALSE,
      'honey_stores' => 'low',
    ]);
    $inspection->save();

    $inspection->set('queen_seen', TRUE);
    $inspection->set('honey_stores', 'adequate');
    $inspection->set('notes', 'Found queen on frame 4.');
    $inspection->save();

    $loaded = HiveInspection::load($inspection->id());
    $this->assertEquals(TRUE, (bool) $loaded->get('queen_seen')->value);
    $this->assertEquals('adequate', $loaded->get('honey_stores')->value);
    $this->assertEquals('Found queen on frame 4.', $loaded->get('notes')->value);
  }

  /**
   * Tests deleting an inspection.
   */
  public function testDeleteInspection(): void {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => '2024-06-15',
    ]);
    $inspection->save();
    $id = $inspection->id();

    $inspection->delete();
    $this->assertNull(HiveInspection::load($id));
  }

}
