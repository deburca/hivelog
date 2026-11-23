<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
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
 * Tests the queen observation section of the inspection form (task 0294).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInspectionQueenObservationTest extends KernelTestBase {

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
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_reminder', 'queen_observation', 'hive_inspection',
      'queen', 'calendar_action', 'calendar_action_item_requirement', 'calendar_action_product_yield',
      'inventory_item', 'product',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    // The first user is uid 1, who passes every access check.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $apiary = Apiary::create(['name' => 'Home Apiary']);
    $apiary->save();
    $this->hive = Hive::create(['name' => 'Hive One', 'apiary' => $apiary->id(), 'status' => 'active']);
    $this->hive->save();
    $this->queen = Queen::create([
      'name' => 'Q-1',
      'hive' => $this->hive->id(),
      'status' => 'active',
      'queen_year' => 2025,
    ]);
    $this->queen->save();
  }

  /**
   * Builds the add form for a new inspection and returns [form, state].
   */
  protected function addForm(array $values = [], array $inspection_values = []): array {
    $inspection = HiveInspection::create($inspection_values + [
      'hive' => $this->hive->id(),
      'inspection_date' => date('Y-m-d'),
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_inspection', 'add');
    $form_object->setEntity($inspection);
    $form_state = new FormState();
    $form_state->setValues($values);
    return [$form_object, $form_state];
  }

  /**
   * Health on a new inspection creates an observation of the active queen.
   */
  public function testHealthCreatesObservation(): void {
    [$form_object, $form_state] = $this->addForm(
      ['queen_observation_health' => 'good', 'queen_observation_active' => 0],
      ['inspection_date' => '2026-05-01', 'temperament' => 'calm'],
    );
    $form_object->save([], $form_state);

    $observations = QueenObservation::loadMultiple();
    $this->assertCount(1, $observations);
    $observation = reset($observations);
    $this->assertEquals($this->queen->id(), $observation->get('queen')->target_id);
    $this->assertSame('good', $observation->get('health')->value);
    $this->assertSame('2026-05-01', $observation->get('observation_date')->value);
    $this->assertSame('calm', $observation->get('temperament')->value);
    $this->assertFalse((bool) $observation->get('active')->value);
  }

  /**
   * Ticking laying alone creates one, marked active.
   */
  public function testLayingAloneCreatesObservation(): void {
    [$form_object, $form_state] = $this->addForm(['queen_observation_health' => '', 'queen_observation_active' => 1]);
    $form_object->save([], $form_state);

    $observations = QueenObservation::loadMultiple();
    $this->assertCount(1, $observations);
    $this->assertTrue((bool) reset($observations)->get('active')->value);
  }

  /**
   * Eggs seen counts as laying when only health was given.
   */
  public function testEggsSeenCountsAsLaying(): void {
    [$form_object, $form_state] = $this->addForm(
      ['queen_observation_health' => 'excellent', 'queen_observation_active' => 0],
      ['eggs_seen' => TRUE],
    );
    $form_object->save([], $form_state);

    $observations = QueenObservation::loadMultiple();
    $this->assertTrue((bool) reset($observations)->get('active')->value);
  }

  /**
   * Nothing filled in creates nothing.
   */
  public function testNothingFilledInCreatesNothing(): void {
    [$form_object, $form_state] = $this->addForm(['queen_observation_health' => '', 'queen_observation_active' => 0]);
    $form_object->save([], $form_state);

    $this->assertCount(0, QueenObservation::loadMultiple());
  }

  /**
   * Editing a saved inspection never adds an observation.
   */
  public function testEditingCreatesNothing(): void {
    $inspection = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => date('Y-m-d')]);
    $inspection->save();
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_inspection', 'edit');
    $form_object->setEntity($inspection);
    $form_state = new FormState();
    $form_state->setValues(['queen_observation_health' => 'poor']);
    $form_object->save([], $form_state);

    $this->assertCount(0, QueenObservation::loadMultiple());
  }

  /**
   * The section is offered on a new inspection of a hive with an active queen.
   */
  public function testSectionIsOffered(): void {
    [$form_object, $form_state] = $this->addForm();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $this->assertArrayHasKey('queen_observation_section', $form);
    $this->assertArrayHasKey('queen_observation_health', $form['queen_observation_section']);
  }

  /**
   * A hive without an active queen is not offered the section.
   */
  public function testSectionIsHiddenWithoutActiveQueen(): void {
    $this->queen->set('status', 'inactive')->save();

    [$form_object, $form_state] = $this->addForm();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $this->assertArrayNotHasKey('queen_observation_section', $form);
  }

  /**
   * Someone who may not add observations is not offered the section.
   */
  public function testSectionIsHiddenWithoutPermission(): void {
    $user = User::create(['name' => 'plain', 'mail' => 'plain@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    [$form_object, $form_state] = $this->addForm();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $this->assertArrayNotHasKey('queen_observation_section', $form);
  }

}
