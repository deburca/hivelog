<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Calendar Action Product Yield entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionProductYieldTest extends KernelTestBase {

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
   * A test product, belonging to `$apiary`.
   */
  protected Product $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest Summer Honey',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $this->calendarAction->save();

    $this->product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $this->product->save();
  }

  /**
   * Tests creating, updating and deleting a yield.
   */
  public function testCrud(): void {
    $yield = CalendarActionProductYield::create([
      'calendar_action' => $this->calendarAction->id(),
      'product' => $this->product->id(),
      'quantity' => 20,
    ]);
    $yield->save();

    $loaded = CalendarActionProductYield::load($yield->id());
    $this->assertEquals($this->calendarAction->id(), $loaded->get('calendar_action')->target_id);
    $this->assertEquals($this->product->id(), $loaded->get('product')->target_id);
    $this->assertEquals(20, $loaded->get('quantity')->value);
    $this->assertStringContainsString('Honey', (string) $loaded->label());
    $this->assertStringContainsString('20', (string) $loaded->label());
    $this->assertStringContainsString('kg', (string) $loaded->label());

    // Update.
    $yield->set('quantity', 25);
    $yield->save();
    $reloaded = CalendarActionProductYield::load($yield->id());
    $this->assertEquals(25, $reloaded->get('quantity')->value);

    // Delete.
    $id = $yield->id();
    $yield->delete();
    $this->assertNull(CalendarActionProductYield::load($id));
  }

  /**
   * Tests that a yield's product must belong to its calendar action's apiary.
   */
  public function testProductMustBelongToSameApiaryAsCalendarAction(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();

    $other_action = CalendarAction::create([
      'apiary' => $other_apiary->id(),
      'title' => 'Other Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $other_action->save();

    $yield = CalendarActionProductYield::create([
      'calendar_action' => $other_action->id(),
      'product' => $this->product->id(),
      'quantity' => 5,
    ]);
    // save() wraps the preSave() InvalidArgumentException in an
    // EntityStorageException, matching CalendarActionTest::
    // testWeekEndBeforeWeekStartRejected's expectation style.
    $this->expectException(\Exception::class);
    $yield->save();
  }

  /**
   * Tests that `calendar_action`, `product` and `quantity` are required.
   */
  public function testRequiredFields(): void {
    $missing_action = CalendarActionProductYield::create([
      'product' => $this->product->id(),
      'quantity' => 5,
    ]);
    $this->assertViolationOnProperty($missing_action->validate(), 'calendar_action');

    $missing_product = CalendarActionProductYield::create([
      'calendar_action' => $this->calendarAction->id(),
      'quantity' => 5,
    ]);
    $this->assertViolationOnProperty($missing_product->validate(), 'product');

    $missing_quantity = CalendarActionProductYield::create([
      'calendar_action' => $this->calendarAction->id(),
      'product' => $this->product->id(),
    ]);
    $this->assertViolationOnProperty($missing_quantity->validate(), 'quantity');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $yield = CalendarActionProductYield::create([
      'calendar_action' => $this->calendarAction->id(),
      'product' => $this->product->id(),
      'quantity' => 5,
    ]);
    $this->assertCount(0, $yield->validate());
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
