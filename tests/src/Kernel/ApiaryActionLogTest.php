<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryActionLogController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Apiary Action Log entity.
 *
 * Mirrors HiveActionLogTest — see task 0027
 * (docs/project-management/tasks/0027-apiary-vs-hive-scoped-calendar-items.md)
 * for why ApiaryActionLog exists as the apiary-scoped sibling of
 * HiveActionLog, and why it deliberately has no `inspection` field.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryActionLogTest extends KernelTestBase {

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
   * A test calendar action, scoped to the apiary.
   */
  protected CalendarAction $calendarAction;

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
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Renew Central Beehive Registration (CBR)',
      'description' => 'Desc.',
      'week_start' => 2,
      'scope' => 'apiary',
    ]);
    $this->calendarAction->save();
  }

  /**
   * Tests creating, updating and deleting an apiary action log.
   */
  public function testCrud(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
      'week_completed' => 3,
      'notes' => 'Renewed online.',
    ]);
    $log->save();

    $loaded = ApiaryActionLog::load($log->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals($this->calendarAction->id(), $loaded->get('calendar_action')->target_id);
    $this->assertEquals('done', $loaded->get('status')->value);
    $this->assertEquals(3, $loaded->get('week_completed')->value);
    $this->assertEquals('Renewed online.', $loaded->get('notes')->value);

    // Label combines the calendar action title, apiary name, and year.
    $this->assertStringContainsString('Renew Central Beehive Registration (CBR)', (string) $loaded->label());
    $this->assertStringContainsString('Test Apiary', (string) $loaded->label());

    // Update.
    $log->set('status', 'ignored');
    $log->save();
    $reloaded = ApiaryActionLog::load($log->id());
    $this->assertEquals('ignored', $reloaded->get('status')->value);

    // Delete.
    $id = $log->id();
    $log->delete();
    $this->assertNull(ApiaryActionLog::load($id));
  }

  /**
   * Tests that `status` defaults to `pending`.
   */
  public function testStatusDefaultsToPending(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $this->assertEquals('pending', $log->get('status')->value);
  }

  /**
   * Tests that `year` defaults to the current calendar year.
   */
  public function testYearDefaultsToCurrentYear(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $this->assertEquals((int) date('Y'), $log->get('year')->value);
  }

  /**
   * Tests that `apiary` is required.
   */
  public function testApiaryRequired(): void {
    $log = ApiaryActionLog::create([
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $violations = $log->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'apiary');
  }

  /**
   * Tests that `calendar_action` is required.
   */
  public function testCalendarActionRequired(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
    ]);
    $violations = $log->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'calendar_action');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'pending',
    ]);
    $this->assertCount(0, $log->validate());
  }

  /**
   * Tests that `status` only accepts pending/done/ignored.
   */
  public function testStatusAllowedValues(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'not_a_real_status',
    ]);
    $violations = $log->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'status');

    foreach (['pending', 'done', 'ignored'] as $valid_status) {
      $log->set('status', $valid_status);
      $this->assertCount(0, $log->validate(), "\"$valid_status\" should be a valid status.");
    }
  }

  /**
   * Tests that `week_completed` rejects values outside 1-53.
   */
  public function testWeekCompletedRangeConstraint(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'week_completed' => 99,
    ]);
    $this->assertViolationOnProperty($log->validate(), 'week_completed');

    $log->set('week_completed', 53);
    $this->assertCount(0, $log->validate());
  }

  /**
   * Tests that ApiaryActionLog has no `inspection` field.
   *
   * Deliberate design decision (see ApiaryActionLog's docblock and task
   * 0027) — there is no apiary-level equivalent of a hive inspection, so
   * unlike HiveActionLog, this entity must never gain one by accident.
   */
  public function testHasNoInspectionField(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $this->assertFalse($log->hasField('inspection'));
  }

  /**
   * Tests that multiple logs per (apiary, calendar_action, year) are allowed.
   *
   * This is a deliberate design decision, mirroring HiveActionLog (see
   * ADR-0025 Consequences) — assert it explicitly so a future change
   * doesn't accidentally add a uniqueness invariant without updating the
   * ADR.
   */
  public function testMultipleLogsPerApiaryCalendarActionYearAreAllowed(): void {
    $year = (int) date('Y');

    $first = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'year' => $year,
      'status' => 'done',
      'week_completed' => 2,
    ]);
    $first->save();

    $second = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'year' => $year,
      'status' => 'done',
      'week_completed' => 3,
      'notes' => 'Second attempt.',
    ]);
    $second->save();

    $this->assertNotEquals($first->id(), $second->id());

    $count = \Drupal::entityTypeManager()
      ->getStorage('apiary_action_log')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $this->apiary->id())
      ->condition('calendar_action', $this->calendarAction->id())
      ->condition('year', $year)
      ->count()
      ->execute();
    $this->assertEquals(2, $count);
  }

  /**
   * Tests that the canonical view renders its detail table with the styled class.
   *
   * Regression test for a table CSS gap found via a code audit: the
   * table used `hivelog-apiary-action-log-table`, but that class was
   * never added to `css/hivelog.tables.css`'s selector groups, so the
   * page rendered fully unstyled.
   */
  public function testViewRendersStyledTable(): void {
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
    ]);
    $log->save();

    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApiaryActionLogController::class);
    $build = $controller->view($log);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('hivelog-apiary-action-log-table', $html);
  }

  /**
   * Asserts that a constraint violation list has a violation on a property.
   */
  protected function assertViolationOnProperty($violations, string $property_prefix): void {
    $found = FALSE;
    foreach ($violations as $violation) {
      if (str_starts_with((string) $violation->getPropertyPath(), $property_prefix)) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, sprintf('Expected a validation violation on "%s".', $property_prefix));
  }

}
