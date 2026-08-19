<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Calendar Action Item Requirement entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionItemRequirementTest extends KernelTestBase {

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
   * A test calendar action, belonging to `$apiary`.
   */
  protected CalendarAction $calendarAction;

  /**
   * A test inventory item, belonging to `$apiary`.
   */
  protected InventoryItem $item;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Varroa Treatment (Spring)',
      'description' => 'Desc.',
      'week_start' => 15,
    ]);
    $this->calendarAction->save();

    $this->item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Apivar Strips',
      'unit' => 'strip',
      'item_type' => 'consumable',
    ]);
    $this->item->save();
  }

  /**
   * Tests creating, updating and deleting a requirement.
   */
  public function testCrud(): void {
    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $this->item->id(),
      'quantity' => 2,
    ]);
    $requirement->save();

    $loaded = CalendarActionItemRequirement::load($requirement->id());
    $this->assertEquals($this->calendarAction->id(), $loaded->get('calendar_action')->target_id);
    $this->assertEquals($this->item->id(), $loaded->get('item')->target_id);
    $this->assertEquals(2, $loaded->get('quantity')->value);
    $this->assertStringContainsString('Apivar Strips', (string) $loaded->label());
    $this->assertStringContainsString('2', (string) $loaded->label());
    $this->assertStringContainsString('strip', (string) $loaded->label());

    // Update.
    $requirement->set('quantity', 3);
    $requirement->save();
    $reloaded = CalendarActionItemRequirement::load($requirement->id());
    $this->assertEquals(3, $reloaded->get('quantity')->value);

    // Delete.
    $id = $requirement->id();
    $requirement->delete();
    $this->assertNull(CalendarActionItemRequirement::load($id));
  }

  /**
   * Tests that a requirement's item must belong to its calendar action's apiary.
   */
  public function testItemMustBelongToSameApiaryAsCalendarAction(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();

    $other_action = CalendarAction::create([
      'apiary' => $other_apiary->id(),
      'title' => 'Other Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $other_action->save();

    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $other_action->id(),
      'item' => $this->item->id(),
      'quantity' => 2,
    ]);
    // save() wraps the preSave() InvalidArgumentException in an
    // EntityStorageException, matching CalendarActionTest::
    // testWeekEndBeforeWeekStartRejected's expectation style.
    $this->expectException(\Exception::class);
    $requirement->save();
  }

  /**
   * Tests that `calendar_action`, `item` and `quantity` are required.
   */
  public function testRequiredFields(): void {
    $missing_action = CalendarActionItemRequirement::create([
      'item' => $this->item->id(),
      'quantity' => 2,
    ]);
    $this->assertViolationOnProperty($missing_action->validate(), 'calendar_action');

    $missing_item = CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'quantity' => 2,
    ]);
    $this->assertViolationOnProperty($missing_item->validate(), 'item');

    $missing_quantity = CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $this->item->id(),
    ]);
    $this->assertViolationOnProperty($missing_quantity->validate(), 'quantity');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $this->calendarAction->id(),
      'item' => $this->item->id(),
      'quantity' => 2,
    ]);
    $this->assertCount(0, $requirement->validate());
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
