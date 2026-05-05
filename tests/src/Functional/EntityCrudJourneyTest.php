<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional coverage for add/edit/delete happy paths for each entity type.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class EntityCrudJourneyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['hivelog'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $admin = $this->drupalCreateUser(['administer hivelog']);
    $this->drupalLogin($admin);
  }

  /**
   * Tests adding, editing and deleting an apiary through the UI.
   */
  public function testApiaryCrudJourney(): void {
    // Create.
    $this->drupalGet('/hivelog/apiary/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name[0][value]' => 'Home Apiary',
      'location[0][value]' => 'Back garden',
      'notes[0][value]' => 'Sheltered spot.',
    ], 'Save');
    $this->assertSession()->pageTextContains('Home Apiary');

    $apiaries = \Drupal::entityTypeManager()
      ->getStorage('apiary')
      ->loadByProperties(['name' => 'Home Apiary']);
    $this->assertCount(1, $apiaries);
    /** @var \Drupal\hivelog\Entity\Apiary $apiary */
    $apiary = reset($apiaries);

    // Edit.
    $this->drupalGet('/hivelog/apiary/' . $apiary->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name[0][value]' => 'Home Apiary Renamed',
    ], 'Save');
    $reloaded = Apiary::load($apiary->id());
    $this->assertEquals('Home Apiary Renamed', $reloaded->label());

    // Delete.
    $this->drupalGet('/hivelog/apiary/' . $apiary->id() . '/delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');
    $this->assertNull(Apiary::load($apiary->id()));
  }

  /**
   * Tests adding, editing and deleting a hive through the scoped add route.
   */
  public function testHiveCrudJourney(): void {
    $apiary = Apiary::create(['name' => 'CRUD Apiary']);
    $apiary->save();

    // Create via the scoped /apiary/{apiary}/hive/add route so the parent
    // reference is pre-populated by HiveController::addForm().
    $this->drupalGet('/hivelog/apiary/' . $apiary->id() . '/hive/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name[0][value]' => 'CRUD Hive',
      'status' => 'active',
    ], 'Save');

    $hives = \Drupal::entityTypeManager()
      ->getStorage('hive')
      ->loadByProperties(['name' => 'CRUD Hive']);
    $this->assertCount(1, $hives);
    /** @var \Drupal\hivelog\Entity\Hive $hive */
    $hive = reset($hives);
    $this->assertEquals($apiary->id(), $hive->get('apiary')->target_id);

    // Edit.
    $this->drupalGet('/hivelog/hive/' . $hive->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'name[0][value]' => 'CRUD Hive Renamed',
      'status' => 'inactive',
    ], 'Save');
    $reloaded = Hive::load($hive->id());
    $this->assertEquals('CRUD Hive Renamed', $reloaded->label());
    $this->assertEquals('inactive', $reloaded->get('status')->value);

    // Delete.
    $this->drupalGet('/hivelog/hive/' . $hive->id() . '/delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');
    $this->assertNull(Hive::load($hive->id()));
  }

  /**
   * Tests adding, editing and deleting an inspection through the scoped add
   * route.
   */
  public function testInspectionCrudJourney(): void {
    $apiary = Apiary::create(['name' => 'CRUD Apiary']);
    $apiary->save();
    $hive = Hive::create([
      'name' => 'CRUD Hive',
      'apiary' => $apiary->id(),
      'status' => 'active',
    ]);
    $hive->save();

    // Create via /hive/{hive}/inspection/add.
    $this->drupalGet('/hivelog/hive/' . $hive->id() . '/inspection/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'inspection_date[0][value][date]' => '2024-06-15',
      'notes[0][value]' => 'Routine inspection.',
    ], 'Save');

    $inspections = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->loadByProperties(['hive' => $hive->id()]);
    $this->assertCount(1, $inspections);
    /** @var \Drupal\hivelog\Entity\HiveInspection $inspection */
    $inspection = reset($inspections);
    $this->assertEquals('2024-06-15', $inspection->get('inspection_date')->value);

    // Edit.
    $this->drupalGet('/hivelog/inspection/' . $inspection->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'notes[0][value]' => 'Updated notes.',
    ], 'Save');
    $reloaded = HiveInspection::load($inspection->id());
    $this->assertEquals('Updated notes.', $reloaded->get('notes')->value);

    // Delete.
    $this->drupalGet('/hivelog/inspection/' . $inspection->id() . '/delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');
    $this->assertNull(HiveInspection::load($inspection->id()));
  }

}
