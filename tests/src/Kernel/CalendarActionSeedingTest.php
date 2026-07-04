<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests seeding a default starter calendar when a new apiary is created.
 *
 * See Apiary::postSave() and
 * CalendarAction::DEFAULT_STARTER_CALENDAR /
 * CalendarAction::seedDefaultsForApiary().
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionSeedingTest extends KernelTestBase {

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Loads the calendar_action rows referencing the given apiary.
   *
   * @return \Drupal\hivelog\Entity\CalendarAction[]
   *   The calendar action entities referencing the given apiary.
   */
  protected function calendarActionsFor(Apiary $apiary): array {
    $storage = \Drupal::entityTypeManager()->getStorage('calendar_action');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary->id())
      ->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Tests that creating a new apiary seeds the expected number of rows.
   */
  public function testNewApiarySeedsExpectedCount(): void {
    $apiary = Apiary::create(['name' => 'Seeding Test Apiary']);
    $apiary->save();

    $actions = $this->calendarActionsFor($apiary);
    $this->assertCount(count(CalendarAction::DEFAULT_STARTER_CALENDAR), $actions);
  }

  /**
   * Tests that seeded rows match the expected content.
   *
   * Spot-checks title/category/week and confirms all rows are enabled +
   * recurring, with no "is default" flag on the schema.
   */
  public function testSeededRowsMatchExpectedContent(): void {
    $apiary = Apiary::create(['name' => 'Seeding Test Apiary']);
    $apiary->save();

    $actions = $this->calendarActionsFor($apiary);
    $by_title = [];
    foreach ($actions as $action) {
      $by_title[$action->label()] = $action;
    }

    $this->assertArrayHasKey('Midwinter Cluster Check', $by_title);
    $this->assertEquals('winter_prep', $by_title['Midwinter Cluster Check']->get('category')->value);
    $this->assertEquals(1, $by_title['Midwinter Cluster Check']->get('week_start')->value);
    $this->assertEquals(3, $by_title['Midwinter Cluster Check']->get('week_end')->value);

    $this->assertArrayHasKey('Renew Central Beehive Registration (CBR)', $by_title);
    $this->assertArrayHasKey('Harvest Spring Honey', $by_title);
    $this->assertArrayHasKey('Harvest Summer Honey', $by_title);

    foreach ($actions as $action) {
      $this->assertTrue((bool) $action->get('enabled')->value, $action->label() . ' should be enabled.');
      $this->assertTrue((bool) $action->get('recurring')->value, $action->label() . ' should be recurring.');
      $this->assertCount(0, $action->validate(), $action->label() . ' should have no validation violations.');
    }
  }

  /**
   * Tests that the starter calendar seeds the expected scope split.
   *
   * Task 0027: exactly 5 starter items are apiary-scoped (Renew CBR, Order
   * & Prepare Equipment, Apiary Site & Hygiene Check, Set Bait Hives /
   * Swarm Traps, Apiary Record-Keeping & Season Review); every other
   * starter item remains hive-scoped.
   */
  public function testSeededRowsHaveExpectedScopeSplit(): void {
    $apiary = Apiary::create(['name' => 'Seeding Test Apiary']);
    $apiary->save();

    $actions = $this->calendarActionsFor($apiary);
    $by_title = [];
    foreach ($actions as $action) {
      $by_title[$action->label()] = $action;
    }

    $expected_apiary_scoped = [
      'Renew Central Beehive Registration (CBR)',
      'Order & Prepare Equipment for the Season',
      'Apiary Site & Hygiene Check',
      'Set Bait Hives / Swarm Traps',
      'Apiary Record-Keeping & Season Review',
    ];

    $apiary_scoped_count = 0;
    $hive_scoped_count = 0;
    foreach ($actions as $action) {
      if ($action->get('scope')->value === 'apiary') {
        $apiary_scoped_count++;
      }
      else {
        $hive_scoped_count++;
      }
    }
    $this->assertEquals(5, $apiary_scoped_count);
    $this->assertEquals(count(CalendarAction::DEFAULT_STARTER_CALENDAR) - 5, $hive_scoped_count);

    foreach ($expected_apiary_scoped as $title) {
      $this->assertArrayHasKey($title, $by_title, "Expected \"$title\" to be seeded.");
      $this->assertEquals('apiary', $by_title[$title]->get('scope')->value, "\"$title\" should be apiary-scoped.");
    }

    // Spot-check a couple of hive-scoped titles to confirm they were not
    // accidentally swept into the apiary-scoped set.
    foreach (['Harvest Spring Honey', 'Harvest Summer Honey', 'Midwinter Cluster Check'] as $title) {
      $this->assertArrayHasKey($title, $by_title, "Expected \"$title\" to be seeded.");
      $this->assertEquals('hive', $by_title[$title]->get('scope')->value, "\"$title\" should be hive-scoped.");
    }
  }

  /**
   * Tests that updating an existing apiary does not duplicate seeded rows.
   *
   * Seeding only ever runs on insert.
   */
  public function testUpdatingExistingApiaryDoesNotDuplicate(): void {
    $apiary = Apiary::create(['name' => 'Seeding Test Apiary']);
    $apiary->save();

    $count_after_create = count($this->calendarActionsFor($apiary));
    $this->assertGreaterThan(0, $count_after_create);

    $apiary->set('name', 'Seeding Test Apiary (renamed)');
    $apiary->save();
    $apiary->set('name', 'Seeding Test Apiary (renamed again)');
    $apiary->save();

    $count_after_updates = count($this->calendarActionsFor($apiary));
    $this->assertEquals($count_after_create, $count_after_updates);
  }

  /**
   * Tests that a seeding failure does not prevent the apiary from saving.
   *
   * `Apiary::postSave()` wraps the seeding call in a try/catch. Simulated
   * by renaming the live calendar_action table out from under the running
   * test, forcing a genuine database exception during seeding, then
   * restoring it afterwards regardless of outcome.
   */
  public function testSeedingFailureDoesNotBlockApiaryCreation(): void {
    $schema = \Drupal::database()->schema();
    $schema->renameTable('hivelog_calendar_action', 'hivelog_calendar_action_test_disabled');

    try {
      $apiary = Apiary::create(['name' => 'Seeding Failure Test Apiary']);
      $apiary->save();
      $this->assertNotNull($apiary->id(), 'The apiary itself must still save even when seeding the starter calendar fails.');
    }
    finally {
      $schema->renameTable('hivelog_calendar_action_test_disabled', 'hivelog_calendar_action');
    }

    // With the table restored, confirm the apiary really has no seeded
    // rows (the failed seeding attempt did not partially succeed).
    $reloaded = Apiary::load($apiary->id());
    $this->assertCount(0, $this->calendarActionsFor($reloaded));
  }

}
