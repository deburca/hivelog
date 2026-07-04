<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Hive Action Log entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveActionLogTest extends KernelTestBase {

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
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create([
      'name' => 'Test Hive',
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
  }

  /**
   * Tests creating, updating and deleting a hive action log.
   */
  public function testCrud(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'done',
      'week_completed' => 16,
      'notes' => 'Treated with thymol.',
    ]);
    $log->save();

    $loaded = HiveActionLog::load($log->id());
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals($this->calendarAction->id(), $loaded->get('calendar_action')->target_id);
    $this->assertEquals('done', $loaded->get('status')->value);
    $this->assertEquals(16, $loaded->get('week_completed')->value);
    $this->assertEquals('Treated with thymol.', $loaded->get('notes')->value);

    // Label combines the calendar action title, hive name, and year.
    $this->assertStringContainsString('Varroa Treatment (Spring)', (string) $loaded->label());
    $this->assertStringContainsString('Test Hive', (string) $loaded->label());

    // Update.
    $log->set('status', 'ignored');
    $log->save();
    $reloaded = HiveActionLog::load($log->id());
    $this->assertEquals('ignored', $reloaded->get('status')->value);

    // Delete.
    $id = $log->id();
    $log->delete();
    $this->assertNull(HiveActionLog::load($id));
  }

  /**
   * Tests that `status` defaults to `pending`.
   */
  public function testStatusDefaultsToPending(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $this->assertEquals('pending', $log->get('status')->value);
  }

  /**
   * Tests that `year` defaults to the current calendar year.
   */
  public function testYearDefaultsToCurrentYear(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $this->assertEquals((int) date('Y'), $log->get('year')->value);
  }

  /**
   * Tests that `hive` is required.
   */
  public function testHiveRequired(): void {
    $log = HiveActionLog::create([
      'calendar_action' => $this->calendarAction->id(),
    ]);
    $violations = $log->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'hive');
  }

  /**
   * Tests that `calendar_action` is required.
   */
  public function testCalendarActionRequired(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
    ]);
    $violations = $log->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'calendar_action');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'status' => 'pending',
    ]);
    $this->assertCount(0, $log->validate());
  }

  /**
   * Tests that `status` only accepts pending/done/ignored.
   */
  public function testStatusAllowedValues(): void {
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
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
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'week_completed' => 99,
    ]);
    $this->assertViolationOnProperty($log->validate(), 'week_completed');

    $log->set('week_completed', 53);
    $this->assertCount(0, $log->validate());
  }

  /**
   * Tests that multiple logs per (hive, calendar_action, year) are permitted.
   *
   * This is a deliberate design decision (see ADR-0025 Consequences), not an
   * oversight — assert it explicitly so a future change doesn't
   * accidentally add a uniqueness invariant without updating the ADR.
   */
  public function testMultipleLogsPerHiveCalendarActionYearAreAllowed(): void {
    $year = (int) date('Y');

    $first = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'year' => $year,
      'status' => 'done',
      'week_completed' => 15,
    ]);
    $first->save();

    $second = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $this->calendarAction->id(),
      'year' => $year,
      'status' => 'done',
      'week_completed' => 18,
      'notes' => 'Second treatment round.',
    ]);
    $second->save();

    $this->assertNotEquals($first->id(), $second->id());

    $count = \Drupal::entityTypeManager()
      ->getStorage('hive_action_log')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive', $this->hive->id())
      ->condition('calendar_action', $this->calendarAction->id())
      ->condition('year', $year)
      ->count()
      ->execute();
    $this->assertEquals(2, $count);
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
