<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Controller\ApiaryController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Product entity.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ProductTest extends KernelTestBase {

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
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests creating, updating and deleting a product.
   */
  public function testCrud(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12.5,
    ]);
    $product->save();

    $loaded = Product::load($product->id());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals('Honey', $loaded->label());
    $this->assertEquals('kg', $loaded->get('unit')->value);
    $this->assertEquals(12.5, (float) $loaded->get('expected_unit_price')->value);
    $this->assertEquals('active', $loaded->get('status')->value);

    // Update.
    $product->set('expected_unit_price', 15);
    $product->save();
    $reloaded = Product::load($product->id());
    $this->assertEquals(15.0, (float) $reloaded->get('expected_unit_price')->value);

    // Delete.
    $id = $product->id();
    $product->delete();
    $this->assertNull(Product::load($id));
  }

  /**
   * Tests that `status` defaults to `active`.
   */
  public function testDefaults(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Beeswax',
      'unit' => 'bar',
      'expected_unit_price' => 5,
    ]);
    $this->assertEquals('active', $product->get('status')->value);
    $product->save();
    $this->assertEquals('active', Product::load($product->id())->get('status')->value);
  }

  /**
   * Tests that `apiary`, `name`, `unit`, and `expected_unit_price` are required.
   */
  public function testRequiredFields(): void {
    $missing_apiary = Product::create([
      'name' => 'No Apiary',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $this->assertViolationOnProperty($missing_apiary->validate(), 'apiary');

    $missing_name = Product::create([
      'apiary' => $this->apiary->id(),
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $this->assertViolationOnProperty($missing_name->validate(), 'name');

    $missing_unit = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'No Unit',
      'expected_unit_price' => 10,
    ]);
    $this->assertViolationOnProperty($missing_unit->validate(), 'unit');

    $missing_price = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'No Price',
      'unit' => 'kg',
    ]);
    $this->assertViolationOnProperty($missing_price->validate(), 'expected_unit_price');
  }

  /**
   * Tests that `status` only accepts a real allowed value.
   */
  public function testAllowedValues(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Bad Status',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'status' => 'not_a_real_status',
    ]);
    $this->assertViolationOnProperty($product->validate(), 'status');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Valid Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $this->assertCount(0, $product->validate());
  }

  /**
   * Tests that the collection page uses the hivelog:entity-table component.
   */
  public function testCollectionUsesEntityTableWithHeading(): void {
    Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ])->save();

    $build = \Drupal::entityTypeManager()->getListBuilder('product')->render();

    $this->assertEquals('component', $build['table']['#type']);
    $this->assertEquals('hivelog:entity-table', $build['table']['#component']);
    $this->assertCount(1, $build['table']['#props']['rows']);

    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('hivelog-entity-table', $html);
    $this->assertStringContainsString('Add Product', $html);
  }

  /**
   * Tests that the apiary page embeds a Products table with real data.
   */
  public function testApiaryPageEmbedsProductsTable(): void {
    // ApiaryController::view() applies real access control to every row it
    // renders, unlike this file's other tests (which operate on entities
    // directly). The default anonymous current user has no permissions, so
    // a real user is needed here — created with no explicit role, relying
    // on it becoming uid 1 (Drupal's hardcoded all-permissions bypass),
    // matching EmbeddedTableFilterPaginationTest/ApiaryCalendarChecklistTest's
    // identical pattern for the same reason.
    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Propolis Tincture',
      'unit' => 'bottle',
      'expected_unit_price' => 8,
    ])->save();

    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApiaryController::class);
    $build = $controller->view($this->apiary);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Products', $html);
    $this->assertStringContainsString('Propolis Tincture', $html);
    $this->assertStringContainsString('8.00', $html);
    $this->assertStringContainsString('Add Product', $html);
  }

  /**
   * Tests that the delete form has no reference-count warning by default.
   *
   * See docs/project-management/tasks/0045-warn-before-deleting-referenced-items-and-products.md.
   */
  public function testDeleteWarningAbsentWhenNoHistoricalReferences(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Never Harvested',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $product->save();

    $form_object = \Drupal::entityTypeManager()->getFormObject('product', 'delete');
    $form_object->setEntity($product);
    $description = (string) $form_object->getDescription();

    $this->assertEquals('This action cannot be undone.', $description);
  }

  /**
   * Tests that the delete form warns when harvest yields reference this product.
   */
  public function testDeleteWarningPresentWhenHarvestYieldsExist(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $product->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $calendar_action->save();
    $log = ApiaryActionLog::create([
      'apiary' => $this->apiary->id(),
      'calendar_action' => $calendar_action->id(),
      'year' => (int) date('Y'),
      'status' => 'done',
    ]);
    $log->save();
    HarvestYield::create([
      'product' => $product->id(),
      'quantity' => 5,
      'apiary_action_log' => $log->id(),
    ])->save();

    $form_object = \Drupal::entityTypeManager()->getFormObject('product', 'delete');
    $form_object->setEntity($product);
    $description = (string) $form_object->getDescription();

    $this->assertStringContainsString('1 historical yield record', $description);
    $this->assertStringContainsString('Unknown product', $description);
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
