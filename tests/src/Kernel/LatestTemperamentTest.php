<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a hive's and queen's temperament follow the latest record (0295).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class LatestTemperamentTest extends KernelTestBase {

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
   * A hive in a test apiary.
   */
  protected Hive $hive;

  /**
   * The hive's active queen.
   */
  protected Queen $queen;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'queen',
      'queen_observation', 'calendar_action', 'calendar_action_item_requirement',
      'calendar_action_product_yield', 'inventory_item', 'product',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $apiary = Apiary::create(['name' => 'Home Apiary']);
    $apiary->save();
    $this->hive = Hive::create([
      'name' => 'Hive One',
      'apiary' => $apiary->id(),
      'status' => 'active',
      'temperament' => 'calm',
    ]);
    $this->hive->save();
    $this->queen = Queen::create([
      'name' => 'Q-1',
      'hive' => $this->hive->id(),
      'status' => 'active',
      'queen_year' => 2025,
      'temperament' => 'calm',
    ]);
    $this->queen->save();
  }

  /**
   * Saves an inspection of the hive.
   */
  protected function inspect(string $date, ?string $temperament): HiveInspection {
    $inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => $date,
      'temperament' => $temperament,
    ]);
    $inspection->save();
    return $inspection;
  }

  /**
   * Saves an observation of the queen.
   */
  protected function observe(string $date, ?string $temperament): QueenObservation {
    $observation = QueenObservation::create([
      'queen' => $this->queen->id(),
      'observation_date' => $date,
      'temperament' => $temperament,
      'active' => TRUE,
    ]);
    $observation->save();
    return $observation;
  }

  /**
   * The hive's current temperament, read fresh.
   */
  protected function hiveTemperament(): ?string {
    return Hive::load($this->hive->id())->get('temperament')->value;
  }

  /**
   * The queen's current temperament, read fresh.
   */
  protected function queenTemperament(): ?string {
    return Queen::load($this->queen->id())->get('temperament')->value;
  }

  /**
   * A newer inspection's temperament becomes the hive's.
   */
  public function testLatestInspectionSetsHive(): void {
    $this->inspect('2026-05-01', 'moderate');
    $this->assertSame('moderate', $this->hiveTemperament());
    $this->inspect('2026-06-01', 'aggressive');
    $this->assertSame('aggressive', $this->hiveTemperament());
  }

  /**
   * A back-dated inspection does not overwrite a newer one's value.
   */
  public function testBackDatedInspectionDoesNotOverwrite(): void {
    $this->inspect('2026-06-01', 'aggressive');
    $this->inspect('2026-04-01', 'calm');
    $this->assertSame('aggressive', $this->hiveTemperament());
  }

  /**
   * An inspection with no temperament leaves the hive's value.
   */
  public function testEmptyTemperamentChangesNothing(): void {
    $this->inspect('2026-06-01', NULL);
    $this->assertSame('calm', $this->hiveTemperament());
  }

  /**
   * An observation sets the queen's temperament, not the hive's.
   */
  public function testObservationSetsQueen(): void {
    $this->observe('2026-05-01', 'aggressive');
    $this->assertSame('aggressive', $this->queenTemperament());
    $this->assertSame('calm', $this->hiveTemperament());
  }

  /**
   * A back-dated observation does not overwrite a newer one's value.
   */
  public function testBackDatedObservationDoesNotOverwrite(): void {
    $this->observe('2026-06-01', 'moderate');
    $this->observe('2026-03-01', 'calm');
    $this->assertSame('moderate', $this->queenTemperament());
  }

  /**
   * Editing the latest inspection's temperament updates the hive.
   */
  public function testEditingLatestInspectionUpdatesHive(): void {
    $inspection = $this->inspect('2026-05-01', 'moderate');
    $inspection->set('temperament', 'aggressive')->save();
    $this->assertSame('aggressive', $this->hiveTemperament());
  }

}
