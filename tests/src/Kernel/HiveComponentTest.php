<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Hive Component entity (task 0163, ADR-0106).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveComponentTest extends KernelTestBase {

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
   * A test hive, belonging to `$apiary`.
   */
  protected Hive $hive;

  /**
   * A durable item with a weight and 5 units purchased.
   */
  protected InventoryItem $itemWithWeight;

  /**
   * A durable item with no weight set, 5 units purchased.
   */
  protected InventoryItem $itemNoWeight;

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
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('hive_component');
    $this->installSchema('file', ['file_usage']);

    // The first user is uid 1 (superuser), so entity access passes.
    User::create(['name' => 'root', 'mail' => 'root@example.com'])->save();
    \Drupal::currentUser()->setAccount(User::load(1));

    $this->apiary = Apiary::create(['name' => 'Test Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create(['name' => 'Test Hive', 'apiary' => $this->apiary->id()]);
    $this->hive->save();

    $this->itemWithWeight = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => '10x12 Brood Chamber (Wood, empty)',
      'category' => 'equipment',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 10,
      'weight_kg' => 4.25,
    ]);
    $this->itemWithWeight->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->itemWithWeight->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 5,
      'unit_price' => 20,
    ])->save();

    $this->itemNoWeight = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Base Plate',
      'category' => 'equipment',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 15,
    ]);
    $this->itemNoWeight->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $this->itemNoWeight->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 5,
      'unit_price' => 10,
    ])->save();
  }

  /**
   * Tests creating, updating and deleting a component.
   */
  public function testCrud(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 2,
    ]);
    $component->save();

    $loaded = HiveComponent::load($component->id());
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);
    $this->assertEquals($this->itemWithWeight->id(), $loaded->get('item')->target_id);
    $this->assertEquals(2, $loaded->get('quantity')->value);
    $this->assertStringContainsString('2', (string) $loaded->label());
    $this->assertStringContainsString('Brood Chamber', (string) $loaded->label());

    // Update (within the 5-available ceiling).
    $component->set('quantity', 3);
    $component->save();
    $reloaded = HiveComponent::load($component->id());
    $this->assertEquals(3, $reloaded->get('quantity')->value);

    // Delete.
    $id = $component->id();
    $component->delete();
    $this->assertNull(HiveComponent::load($id));
  }

  /**
   * Tests that quantity defaults to 1.
   */
  public function testQuantityDefaultsToOne(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
    ]);
    $this->assertEquals(1, $component->get('quantity')->value);
  }

  /**
   * Tests that a component's item must belong to its hive's apiary.
   */
  public function testItemMustBelongToSameApiaryAsHive(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();
    $other_hive = Hive::create(['name' => 'Other Hive', 'apiary' => $other_apiary->id()]);
    $other_hive->save();

    $component = HiveComponent::create([
      'hive' => $other_hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 1,
    ]);
    // save() wraps the preSave() InvalidArgumentException in an
    // EntityStorageException, matching CalendarActionItemRequirementTest's
    // own expectation style.
    $this->expectException(\Exception::class);
    $component->save();
  }

  /**
   * Tests that `hive`, `item` and `quantity` are required.
   */
  public function testRequiredFields(): void {
    $missing_hive = HiveComponent::create([
      'item' => $this->itemWithWeight->id(),
      'quantity' => 1,
    ]);
    $this->assertViolationOnProperty($missing_hive->validate(), 'hive');

    $missing_item = HiveComponent::create([
      'hive' => $this->hive->id(),
      'quantity' => 1,
    ]);
    $this->assertViolationOnProperty($missing_item->validate(), 'item');
  }

  /**
   * Tests that `quantity` must be at least 1 (no zero/fractional components).
   */
  public function testQuantityMustBeAtLeastOne(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 0,
    ]);
    $this->assertViolationOnProperty($component->validate(), 'quantity');
  }

  /**
   * Tests that a fully valid entity has zero violations.
   */
  public function testValidEntityHasNoViolations(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 2,
    ]);
    $this->assertCount(0, $component->validate());
  }

  /**
   * Tests that assigning exactly the available quantity succeeds.
   */
  public function testCanAssignExactlyAvailableQuantity(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 5,
    ]);
    $component->save();
    $this->assertNotNull($component->id());
  }

  /**
   * Tests that assigning more than the available quantity is rejected.
   */
  public function testCannotExceedAvailableQuantity(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 6,
    ]);
    $this->expectException(\Exception::class);
    $component->save();
  }

  /**
   * Tests that editing a component's own quantity doesn't count against itself.
   */
  public function testEditingOwnQuantityDoesNotCountAgainstItself(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 3,
    ]);
    $component->save();

    // 5 available total; this row already accounts for 3 of them. Raising
    // to 5 must succeed — the row's own prior 3 must not be double-counted
    // against the new value.
    $component->set('quantity', 5);
    $component->save();
    $this->assertEquals(5, HiveComponent::load($component->id())->get('quantity')->value);
  }

  /**
   * Tests that availability is shared across every hive in the apiary.
   */
  public function testAssigningAcrossMultipleHivesSharesAvailability(): void {
    $other_hive = Hive::create(['name' => 'Other Hive', 'apiary' => $this->apiary->id()]);
    $other_hive->save();

    HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 3,
    ])->save();

    // Only 2 remain (5 total - 3 assigned to the first hive) — 3 on the
    // second hive must fail.
    $second = HiveComponent::create([
      'hive' => $other_hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 3,
    ]);
    $this->expectException(\Exception::class);
    $second->save();
  }

  /**
   * Tests that an item with no purchases has zero availability.
   */
  public function testAvailabilityZeroWhenNeverPurchased(): void {
    $never_purchased = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Never Purchased',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 10,
    ]);
    $never_purchased->save();

    $this->assertEquals(0.0, $never_purchased->getAvailableForHiveAssignmentQuantity());

    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $never_purchased->id(),
      'quantity' => 1,
    ]);
    $this->expectException(\Exception::class);
    $component->save();
  }

  /**
   * Tests that getTotalPurchasedQuantity() works for durable items.
   *
   * Unlike getStockOnHand(), which is consumable-only and NULL for durable.
   */
  public function testGetTotalPurchasedQuantityWorksForDurable(): void {
    $this->assertNull($this->itemWithWeight->getStockOnHand());
    $this->assertEquals(5.0, $this->itemWithWeight->getTotalPurchasedQuantity());
  }

  /**
   * Tests that Hive::getEmptyWeightKg() is NULL with no components.
   */
  public function testEmptyWeightNullWithNoComponents(): void {
    $this->assertNull($this->hive->getEmptyWeightKg());
  }

  /**
   * Tests that Hive::getEmptyWeightKg() sums quantity × item weight.
   */
  public function testEmptyWeightSumsComponents(): void {
    HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 2,
    ])->save();

    $other_weighted = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Base Plate (Wood)',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 15,
      'weight_kg' => 1.5,
    ]);
    $other_weighted->save();
    InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $other_weighted->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 1,
      'unit_price' => 10,
    ])->save();
    HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $other_weighted->id(),
      'quantity' => 1,
    ])->save();

    // (2 x 4.25) + (1 x 1.5) = 10.0.
    $this->assertEquals(10.0, Hive::load($this->hive->id())->getEmptyWeightKg());
  }

  /**
   * Tests that getEmptyWeightKg() is NULL if any component lacks a weight.
   *
   * Not a partial sum — see ADR-0106 §3.
   */
  public function testEmptyWeightNullWhenAnyComponentMissingWeight(): void {
    HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 1,
    ])->save();
    HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemNoWeight->id(),
      'quantity' => 1,
    ])->save();

    $this->assertNull(Hive::load($this->hive->id())->getEmptyWeightKg());
  }

  /**
   * Row 0106-1: deleting a hive cascades its components.
   */
  public function testDeletingHiveCascadesComponents(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 1,
    ]);
    $component->save();
    $component_id = $component->id();

    $this->hive->delete();

    $this->assertNull(HiveComponent::load($component_id));
  }

  /**
   * Row 0106-2: an item's delete is blocked while a component uses it.
   *
   * Not exempted by `administer hivelog`, matching every other BLOCK
   * row's own `testAdminIsNotExemptFromBlock()`-style precedent.
   */
  public function testDeletingReferencedItemIsBlocked(): void {
    $admin_role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();
    $admin = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $admin->addRole('admin');
    $admin->save();

    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemWithWeight->id(),
      'quantity' => 1,
    ]);
    $component->save();

    $result = $this->itemWithWeight->access('delete', $admin, TRUE);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
    $this->assertStringContainsString('hive component', (string) $result->getReason());
  }

  /**
   * Resolves the entities a rendered `item` widget would actually offer.
   *
   * Mirrors `ApiaryScopedAutocompleteTest::referenceableEntities()` —
   * drives the selection plugin manager the same way
   * `EntityAutocompleteMatcher::getMatches()` does in a real request, so
   * this proves what the add/edit form's own autocomplete would return,
   * not just what the plugin class does in isolation.
   */
  protected function referenceableEntities(array $target_id_element): array {
    $options = $target_id_element['#selection_settings'] + [
      'target_type' => 'inventory_item',
      'handler' => $target_id_element['#selection_handler'],
    ];
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
    return $handler->getReferenceableEntities();
  }

  /**
   * Tests the add form's item picker offers only available items.
   *
   * A remaining item gets an "(N available)" suffix; a fully-assigned
   * item is excluded entirely.
   */
  public function testAddFormItemPickerOffersOnlyAvailableItems(): void {
    // Assign all 5 of itemNoWeight's units, leaving zero available.
    HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemNoWeight->id(),
      'quantity' => 5,
    ])->save();

    $new_component = \Drupal::entityTypeManager()->getStorage('hive_component')->create([
      'hive' => $this->hive->id(),
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($new_component, 'add');
    $element = $build['item']['widget'][0]['target_id'];

    $this->assertEquals('default:hivelog_hive_component_item', $element['#selection_handler']);
    $this->assertEquals($this->apiary->id(), $element['#selection_settings']['apiary_id']);
    $this->assertNull($element['#selection_settings']['exclude_hive_component_id']);

    $referenceable = $this->referenceableEntities($element);

    // itemWithWeight has all 5 units still available.
    $this->assertArrayHasKey($this->itemWithWeight->id(), $referenceable['inventory_item']);
    $this->assertStringContainsString('5 available', $referenceable['inventory_item'][$this->itemWithWeight->id()]);

    // itemNoWeight is fully assigned — excluded entirely.
    $this->assertArrayNotHasKey($this->itemNoWeight->id(), $referenceable['inventory_item'] ?? []);
  }

  /**
   * Tests that editing a component still offers its own item.
   *
   * Its own prior quantity must be excluded from the availability count
   * (`exclude_hive_component_id`), so a fully-assigned item still shows
   * up with its real remaining availability for the row already using
   * it — not zero, and not silently missing from the picker.
   */
  public function testEditFormItemPickerExcludesOwnQuantityFromAvailability(): void {
    $component = HiveComponent::create([
      'hive' => $this->hive->id(),
      'item' => $this->itemNoWeight->id(),
      'quantity' => 5,
    ]);
    $component->save();

    $build = \Drupal::service('entity.form_builder')->getForm($component, 'edit');
    $element = $build['item']['widget'][0]['target_id'];

    $this->assertEquals((int) $component->id(), $element['#selection_settings']['exclude_hive_component_id']);

    $referenceable = $this->referenceableEntities($element);
    $this->assertArrayHasKey($this->itemNoWeight->id(), $referenceable['inventory_item']);
    $this->assertStringContainsString('5 available', $referenceable['inventory_item'][$this->itemNoWeight->id()]);
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
