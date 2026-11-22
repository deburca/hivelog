<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\HiveReminder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Next visit" reminder on the inspection form (task 0274).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveInspectionNextVisitTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_reminder', 'hive_inspection',
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
  }

  /**
   * Builds the add form for a new inspection and returns [form, state].
   */
  protected function addForm(array $values = []): array {
    $inspection = HiveInspection::create([
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
   * A date and note on a new inspection create a pending reminder.
   */
  public function testNextVisitCreatesReminder(): void {
    $due = date('Y-m-d', strtotime('+5 days'));
    [$form_object, $form_state] = $this->addForm([
      'next_visit_date' => $due,
      'next_visit_note' => 'Check the queen is laying',
    ]);
    $form_object->save([], $form_state);

    $reminders = HiveReminder::loadMultiple();
    $this->assertCount(1, $reminders);
    $reminder = reset($reminders);
    $this->assertSame('Check the queen is laying', $reminder->label());
    $this->assertSame($due, $reminder->get('due_date')->value);
    $this->assertSame('pending', $reminder->get('status')->value);
    $this->assertEquals($this->hive->id(), $reminder->get('hive')->target_id);
  }

  /**
   * With no note the reminder reads "Next visit".
   */
  public function testEmptyNoteReadsNextVisit(): void {
    [$form_object, $form_state] = $this->addForm([
      'next_visit_date' => date('Y-m-d', strtotime('+2 days')),
      'next_visit_note' => '  ',
    ]);
    $form_object->save([], $form_state);

    $reminders = HiveReminder::loadMultiple();
    $this->assertSame('Next visit', reset($reminders)->label());
  }

  /**
   * No date means no reminder.
   */
  public function testNoDateCreatesNothing(): void {
    [$form_object, $form_state] = $this->addForm(['next_visit_date' => '', 'next_visit_note' => 'ignored']);
    $form_object->save([], $form_state);

    $this->assertCount(0, HiveReminder::loadMultiple());
  }

  /**
   * Editing a saved inspection never adds a reminder.
   */
  public function testEditingCreatesNoReminder(): void {
    $inspection = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => date('Y-m-d')]);
    $inspection->save();
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_inspection', 'edit');
    $form_object->setEntity($inspection);
    $form_state = new FormState();
    $form_state->setValues(['next_visit_date' => date('Y-m-d', strtotime('+3 days'))]);
    $form_object->save([], $form_state);

    $this->assertCount(0, HiveReminder::loadMultiple());
  }

  /**
   * A date before today is refused; today is allowed.
   */
  public function testPastDateIsRefused(): void {
    [$form_object, $form_state] = $this->addForm(['next_visit_date' => date('Y-m-d', strtotime('-1 day'))]);
    $form_object->validateNextVisit($form_state);

    $this->assertArrayHasKey('next_visit_date', $form_state->getErrors());

    // Today is fine.
    [$form_object, $form_state] = $this->addForm(['next_visit_date' => date('Y-m-d')]);
    $form_object->validateNextVisit($form_state);
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * A new inspection's form offers the section.
   */
  public function testSectionIsOfferedForNewInspection(): void {
    [$form_object, $form_state] = $this->addForm();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $this->assertArrayHasKey('next_visit_section', $form);
    $this->assertArrayHasKey('next_visit_date', $form['next_visit_section']);
  }

  /**
   * Someone who may not add reminders is not offered the section.
   */
  public function testSectionIsHiddenWithoutPermission(): void {
    $user = User::create(['name' => 'plain', 'mail' => 'plain@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    [$form_object, $form_state] = $this->addForm();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $this->assertArrayNotHasKey('next_visit_section', $form);
  }

}
