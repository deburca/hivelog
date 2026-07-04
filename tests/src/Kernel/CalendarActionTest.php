<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Calendar Action entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionTest extends KernelTestBase {

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

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests creating, updating and deleting a calendar action.
   */
  public function testCrud(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest Spring Honey',
      'description' => "Harvest capped spring honey supers.\n- Confirm at least 80% capped.\n- Leave adequate stores.",
      'category' => 'harvest_spring',
      'week_start' => 25,
      'week_end' => 27,
    ]);
    $action->save();

    $loaded = CalendarAction::load($action->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals('Harvest Spring Honey', $loaded->label());
    $this->assertEquals('harvest_spring', $loaded->get('category')->value);
    $this->assertEquals(25, $loaded->get('week_start')->value);
    $this->assertEquals(27, $loaded->get('week_end')->value);

    // Update.
    $action->set('title', 'Harvest Spring Honey (updated)');
    $action->save();
    $reloaded = CalendarAction::load($action->id());
    $this->assertEquals('Harvest Spring Honey (updated)', $reloaded->label());

    // Delete.
    $id = $action->id();
    $action->delete();
    $this->assertNull(CalendarAction::load($id));
  }

  /**
   * Tests that `enabled` and `recurring` both default to TRUE.
   */
  public function testEnabledAndRecurringDefaults(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Defaults Test',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $this->assertTrue((bool) $action->get('enabled')->value);
    $this->assertTrue((bool) $action->get('recurring')->value);
    $action->save();
    $loaded = CalendarAction::load($action->id());
    $this->assertTrue((bool) $loaded->get('enabled')->value);
    $this->assertTrue((bool) $loaded->get('recurring')->value);
  }

  /**
   * Tests that `apiary` is required.
   */
  public function testApiaryRequired(): void {
    $action = CalendarAction::create([
      'title' => 'No Apiary',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $violations = $action->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'apiary');
  }

  /**
   * Tests that `title` is required.
   */
  public function testTitleRequired(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $violations = $action->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'title');
  }

  /**
   * Tests that `description` is required.
   */
  public function testDescriptionRequired(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'No Description',
      'week_start' => 10,
    ]);
    $violations = $action->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'description');
  }

  /**
   * Tests that `week_start` is required.
   */
  public function testWeekStartRequired(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'No Week Start',
      'description' => 'Desc.',
    ]);
    $violations = $action->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'week_start');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Valid Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'week_end' => 12,
    ]);
    $this->assertCount(0, $action->validate());
  }

  /**
   * Tests that `category` only accepts real allowed values.
   */
  public function testCategoryAllowedValues(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Bad Category',
      'description' => 'Desc.',
      'week_start' => 10,
      'category' => 'not_a_real_category',
    ]);
    $violations = $action->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertViolationOnProperty($violations, 'category');

    $action->set('category', 'varroa_treatment');
    $this->assertCount(0, $action->validate());
  }

  /**
   * Tests that `week_start`/`week_end` reject values outside 1-53.
   */
  public function testWeekRangeConstraint(): void {
    $too_low = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Week Too Low',
      'description' => 'Desc.',
      'week_start' => 0,
    ]);
    $this->assertViolationOnProperty($too_low->validate(), 'week_start');

    $too_high = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Week Too High',
      'description' => 'Desc.',
      'week_start' => 54,
    ]);
    $this->assertViolationOnProperty($too_high->validate(), 'week_start');

    $week_end_too_high = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Week End Too High',
      'description' => 'Desc.',
      'week_start' => 10,
      'week_end' => 99,
    ]);
    $this->assertViolationOnProperty($week_end_too_high->validate(), 'week_end');

    // The boundary values themselves (1 and 53) are valid.
    $boundaries = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Boundary Weeks',
      'description' => 'Desc.',
      'week_start' => 1,
      'week_end' => 53,
    ]);
    $this->assertCount(0, $boundaries->validate());
  }

  /**
   * Tests that saving with `week_end < week_start` is rejected.
   */
  public function testWeekEndBeforeWeekStartRejected(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Bad Week Order',
      'description' => 'Desc.',
      'week_start' => 20,
      'week_end' => 10,
    ]);
    $this->expectException(\Exception::class);
    $action->save();
  }

  /**
   * Tests that `week_end` equal to `week_start` is accepted (single week).
   */
  public function testWeekEndEqualToWeekStartIsAccepted(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Single Week',
      'description' => 'Desc.',
      'week_start' => 20,
      'week_end' => 20,
    ]);
    $action->save();
    $this->assertNotNull($action->id());
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
