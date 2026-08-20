<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Form\HiveActionLogForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests optionally linking a "done" report to a new hive inspection record.
 *
 * See HiveActionLogForm::createLinkedInspection() and
 * docs/project-management/tasks/0023-link-hive-action-log-to-inspection.md.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveActionLogInspectionLinkTest extends KernelTestBase {

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
   * A test apiary.
   */
  protected Apiary $apiary;

  /**
   * A test hive.
   */
  protected Hive $hive;

  /**
   * A test calendar action.
   */
  protected CalendarAction $calendarAction;

  /**
   * A privileged user (can add hive action logs and hive inspections).
   */
  protected User $privilegedUser;

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
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('harvest_yield');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Link Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Link Test Hive',
      'apiary' => $this->apiary->id(),
      'status' => 'active',
    ]);
    $this->hive->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Varroa Treatment (Spring)',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $this->calendarAction->save();

    $role = Role::create(['id' => 'full_reporter', 'label' => 'Full Reporter']);
    $role->grantPermission('add hive action log');
    $role->grantPermission('add hive inspection');
    $role->save();

    $this->privilegedUser = User::create(['name' => 'privileged', 'mail' => 'privileged@example.com']);
    $this->privilegedUser->addRole('full_reporter');
    $this->privilegedUser->save();
    \Drupal::currentUser()->setAccount($this->privilegedUser);
  }

  /**
   * Builds a HiveActionLogForm instance with the given entity attached.
   */
  protected function buildFormObject(HiveActionLog $log): HiveActionLogForm {
    $form_object = \Drupal::entityTypeManager()->getFormObject('hive_action_log', 'add');
    $form_object->setEntity($log);
    return $form_object;
  }

  /**
   * Tests that a checked "done" report creates exactly one linked inspection.
   *
   * Confirms the linked HiveInspection has the expected action_taken text
   * and that HiveActionLog.inspection is set.
   */
  public function testCheckedDoneCreatesExactlyOneLinkedInspection(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
      'notes' => 'Treated with thymol.',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('create_inspection', 1);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $this->assertNotNull($saved_log);
    $linked = $saved_log->get('inspection')->entity;
    $this->assertInstanceOf(HiveInspection::class, $linked);
    $this->assertEquals($this->hive->id(), $linked->get('hive')->target_id);
    $this->assertEquals(date('Y-m-d'), $linked->get('inspection_date')->value);
    $this->assertEquals('Varroa Treatment (Spring): Treated with thymol.', $linked->get('action_taken')->value);

    $inspection_count = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive', $this->hive->id())
      ->count()
      ->execute();
    $this->assertEquals(1, $inspection_count);

    $redirect = $form_state->getRedirect();
    $this->assertEquals('entity.hive_inspection.edit_form', $redirect->getRouteName());
    $this->assertEquals(['hive_inspection' => $linked->id()], $redirect->getRouteParameters());
  }

  /**
   * Tests that reporting done without the checkbox never creates an inspection.
   */
  public function testDoneWithoutCheckboxNeverCreatesInspection(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = new FormState();
    // create_inspection deliberately left unset.
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $this->assertTrue($saved_log->get('inspection')->isEmpty());

    $inspection_count = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(0, $inspection_count);
  }

  /**
   * Tests that reporting ignored never creates an inspection.
   *
   * Holds true even if the checkbox is somehow submitted as checked.
   */
  public function testIgnoredNeverCreatesInspectionEvenIfCheckboxSubmitted(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'ignored',
    ]);

    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('create_inspection', 1);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $this->assertTrue($saved_log->get('inspection')->isEmpty());

    $inspection_count = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(0, $inspection_count);
  }

  /**
   * Tests that a user without permission never sees or triggers the option.
   *
   * A user without `add hive inspection` never sees the checkbox in the
   * built form, and cannot trigger inspection creation even if
   * `create_inspection` is force-submitted.
   */
  public function testUserWithoutInspectionPermissionNeverSeesOrTriggersOption(): void {
    $limited_role = Role::create(['id' => 'log_only', 'label' => 'Log Only']);
    $limited_role->grantPermission('add hive action log');
    $limited_role->save();

    $limited_user = User::create(['name' => 'limited', 'mail' => 'limited@example.com']);
    $limited_user->addRole('log_only');
    $limited_user->save();
    \Drupal::currentUser()->setAccount($limited_user);

    // (a) The checkbox is absent from the built form.
    $log_for_form = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($log_for_form, 'add');
    $this->assertArrayNotHasKey('create_inspection', $build);

    // (b) Even if force-submitted, save() does not create an inspection.
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $form_object = $this->buildFormObject($log);
    $form_state = (new FormState())->setValue('create_inspection', 1);
    $form_object->save([], $form_state);

    $saved_log = HiveActionLog::load($form_object->getEntity()->id());
    $this->assertTrue($saved_log->get('inspection')->isEmpty());

    $inspection_count = \Drupal::entityTypeManager()
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(0, $inspection_count);
  }

  /**
   * Tests that an already-linked log does not offer the checkbox again.
   *
   * Prevents an orphaned first inspection if the checkbox were re-offered.
   */
  public function testEditingAlreadyLinkedLogDoesNotOfferCheckboxAgain(): void {
    $existing_inspection = HiveInspection::create([
      'hive' => $this->hive->id(),
      'inspection_date' => date('Y-m-d'),
    ]);
    $existing_inspection->save();

    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
      'inspection' => $existing_inspection->id(),
    ]);
    $log->save();

    $build = \Drupal::service('entity.form_builder')->getForm($log, 'edit');
    $this->assertArrayNotHasKey('create_inspection', $build);
    $this->assertArrayHasKey('inspection', $build);
    $this->assertFalse($build['inspection']['#access']);
  }

}
