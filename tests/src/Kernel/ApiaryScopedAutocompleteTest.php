<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests apiary-scoped autocomplete on item/product reference widgets.
 *
 * See docs/project-management/tasks/0041-scope-item-and-product-autocomplete-to-current-apiary.md
 * and \Drupal\hivelog\Plugin\EntityReferenceSelection\ApiaryScopedSelection.
 * Builds each form and drives its widget's own `#selection_handler`/
 * `#selection_settings` through the selection plugin manager — the exact
 * mechanism `EntityAutocompleteMatcher` uses in a real request — rather
 * than asserting on form structure alone, so this genuinely proves the
 * autocomplete only offers same-apiary entities.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ApiaryScopedAutocompleteTest extends KernelTestBase {

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
   * The apiary the form under test is scoped to.
   */
  protected Apiary $apiaryA;

  /**
   * A second apiary, whose entities must never be offered.
   */
  protected Apiary $apiaryB;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $this->apiaryA = Apiary::create(['name' => 'Apiary A']);
    $this->apiaryA->save();
    $this->apiaryB = Apiary::create(['name' => 'Apiary B']);
    $this->apiaryB->save();
  }

  /**
   * Resolves the entities a rendered `entity_autocomplete` widget would offer.
   *
   * Mirrors exactly how `EntityAutocompleteMatcher::getMatches()` builds
   * its selection handler, so this proves what a real autocomplete
   * request would actually return.
   *
   * @return array<string, array<int|string, string>>
   *   Referenceable entities, keyed by entity type then id, as returned
   *   by `SelectionInterface::getReferenceableEntities()`.
   */
  protected function referenceableEntities(array $target_id_element, string $target_type): array {
    $options = $target_id_element['#selection_settings'] + [
      'target_type' => $target_type,
      'handler' => $target_id_element['#selection_handler'],
    ];
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
    return $handler->getReferenceableEntities();
  }

  /**
   * Tests that InventoryPurchase.item is scoped to the purchase's own apiary.
   */
  public function testInventoryPurchaseItemScopedToApiary(): void {
    $item_a = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Sugar A',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item_a->save();
    $item_b = InventoryItem::create([
      'apiary' => $this->apiaryB->id(),
      'name' => 'Sugar B',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item_b->save();

    $purchase = \Drupal::entityTypeManager()->getStorage('inventory_purchase')->create(['apiary' => $this->apiaryA->id()]);
    $build = \Drupal::service('entity.form_builder')->getForm($purchase, 'add');
    $element = $build['item']['widget'][0]['target_id'];

    $this->assertEquals('default:hivelog_apiary_scoped', $element['#selection_handler']);
    $this->assertEquals($this->apiaryA->id(), $element['#selection_settings']['apiary_id']);

    $referenceable = $this->referenceableEntities($element, 'inventory_item');
    $this->assertArrayHasKey($item_a->id(), $referenceable['inventory_item']);
    $this->assertArrayNotHasKey($item_b->id(), $referenceable['inventory_item']);
  }

  /**
   * Tests that CalendarActionItemRequirement.item is scoped via calendar_action's apiary.
   */
  public function testCalendarActionItemRequirementItemScopedToApiary(): void {
    $item_a = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Jars A',
      'unit' => 'jar',
      'item_type' => 'consumable',
    ]);
    $item_a->save();
    $item_b = InventoryItem::create([
      'apiary' => $this->apiaryB->id(),
      'name' => 'Jars B',
      'unit' => 'jar',
      'item_type' => 'consumable',
    ]);
    $item_b->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiaryA->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $calendar_action->save();

    $requirement = \Drupal::entityTypeManager()->getStorage('calendar_action_item_requirement')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($requirement, 'add');
    $element = $build['item']['widget'][0]['target_id'];

    $referenceable = $this->referenceableEntities($element, 'inventory_item');
    $this->assertArrayHasKey($item_a->id(), $referenceable['inventory_item']);
    $this->assertArrayNotHasKey($item_b->id(), $referenceable['inventory_item']);
  }

  /**
   * Tests that CalendarActionProductYield.product is scoped via calendar_action's apiary.
   */
  public function testCalendarActionProductYieldProductScopedToApiary(): void {
    $product_a = Product::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Honey A',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $product_a->save();
    $product_b = Product::create([
      'apiary' => $this->apiaryB->id(),
      'name' => 'Honey B',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $product_b->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiaryA->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $calendar_action->save();

    $yield = \Drupal::entityTypeManager()->getStorage('calendar_action_product_yield')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($yield, 'add');
    $element = $build['product']['widget'][0]['target_id'];

    $referenceable = $this->referenceableEntities($element, 'product');
    $this->assertArrayHasKey($product_a->id(), $referenceable['product']);
    $this->assertArrayNotHasKey($product_b->id(), $referenceable['product']);
  }

  /**
   * Tests that a standalone add form with no known apiary stays apiary-unfiltered.
   *
   * The global `entity.inventory_purchase.add_form` route (linked from
   * `InventoryPurchaseListBuilder`'s heading) has no apiary pre-filled —
   * there's nothing to scope to yet, so cross-apiary items must still be
   * offered. The widget still uses the custom selection handler (not
   * falling back to plain `default`), since that handler also carries
   * the always-on discontinued-status filter from task 0043.
   */
  public function testStandaloneAddFormWithNoApiaryStaysApiaryUnfiltered(): void {
    $item_a = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Sugar A',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item_a->save();
    $item_b = InventoryItem::create([
      'apiary' => $this->apiaryB->id(),
      'name' => 'Sugar B',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item_b->save();

    $purchase = \Drupal::entityTypeManager()->getStorage('inventory_purchase')->create([]);
    $build = \Drupal::service('entity.form_builder')->getForm($purchase, 'add');
    $element = $build['item']['widget'][0]['target_id'];

    $this->assertEquals('default:hivelog_apiary_scoped', $element['#selection_handler']);

    $referenceable = $this->referenceableEntities($element, 'inventory_item');
    $this->assertArrayHasKey($item_a->id(), $referenceable['inventory_item']);
    $this->assertArrayHasKey($item_b->id(), $referenceable['inventory_item']);
  }

  /**
   * Tests that a discontinued item is hidden from a fresh InventoryPurchase form.
   *
   * See docs/project-management/tasks/0043-hide-discontinued-items-and-products-from-selection.md.
   */
  public function testDiscontinuedItemHiddenFromFreshInventoryPurchaseForm(): void {
    $active = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Active Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $active->save();
    $discontinued = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Discontinued Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
      'status' => 'discontinued',
    ]);
    $discontinued->save();

    $purchase = \Drupal::entityTypeManager()->getStorage('inventory_purchase')->create(['apiary' => $this->apiaryA->id()]);
    $build = \Drupal::service('entity.form_builder')->getForm($purchase, 'add');
    $element = $build['item']['widget'][0]['target_id'];

    $referenceable = $this->referenceableEntities($element, 'inventory_item');
    $this->assertArrayHasKey($active->id(), $referenceable['inventory_item']);
    $this->assertArrayNotHasKey($discontinued->id(), $referenceable['inventory_item']);
  }

  /**
   * Tests that a discontinued product is hidden from a fresh yield-recipe form.
   *
   * See docs/project-management/tasks/0043-hide-discontinued-items-and-products-from-selection.md.
   */
  public function testDiscontinuedProductHiddenFromFreshCalendarActionProductYieldForm(): void {
    $active = Product::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Active Honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $active->save();
    $discontinued = Product::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Discontinued Honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'status' => 'discontinued',
    ]);
    $discontinued->save();

    $calendar_action = CalendarAction::create([
      'apiary' => $this->apiaryA->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
    ]);
    $calendar_action->save();

    $yield = \Drupal::entityTypeManager()->getStorage('calendar_action_product_yield')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    $build = \Drupal::service('entity.form_builder')->getForm($yield, 'add');
    $element = $build['product']['widget'][0]['target_id'];

    $referenceable = $this->referenceableEntities($element, 'product');
    $this->assertArrayHasKey($active->id(), $referenceable['product']);
    $this->assertArrayNotHasKey($discontinued->id(), $referenceable['product']);
  }

  /**
   * Tests that an existing purchase keeps its since-discontinued item.
   *
   * A since-discontinued item is hidden from new choices, but an existing
   * purchase that already references it still shows the current value
   * and still saves cleanly on edit. See
   * docs/project-management/tasks/0043-hide-discontinued-items-and-products-from-selection.md's
   * "existing references untouched" criterion.
   */
  public function testExistingInventoryPurchaseKeepsDiscontinuedItemOnEditAndSave(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Soon Discontinued Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    $purchase = InventoryPurchase::create([
      'apiary' => $this->apiaryA->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 10,
      'unit_price' => 1.5,
    ]);
    $purchase->save();

    $item->set('status', 'discontinued');
    $item->save();

    // A fresh add form no longer offers it — checked against another,
    // still-active item in the same apiary so the referenceable set
    // itself isn't empty (an empty result has no `inventory_item` key
    // at all, which would make the assertion below vacuous).
    $still_active = InventoryItem::create([
      'apiary' => $this->apiaryA->id(),
      'name' => 'Still Active Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $still_active->save();

    $new_purchase = \Drupal::entityTypeManager()->getStorage('inventory_purchase')->create(['apiary' => $this->apiaryA->id()]);
    $add_build = \Drupal::service('entity.form_builder')->getForm($new_purchase, 'add');
    $add_element = $add_build['item']['widget'][0]['target_id'];
    $referenceable = $this->referenceableEntities($add_element, 'inventory_item');
    $this->assertArrayHasKey($still_active->id(), $referenceable['inventory_item']);
    $this->assertArrayNotHasKey($item->id(), $referenceable['inventory_item']);

    // The existing purchase's edit form still shows its current value.
    $edit_build = \Drupal::service('entity.form_builder')->getForm($purchase, 'edit');
    $edit_element = $edit_build['item']['widget'][0]['target_id'];
    $this->assertNotEmpty($edit_element['#default_value']);
    $this->assertEquals($item->id(), reset($edit_element['#default_value'])->id());

    // Re-saving the existing entity (unchanged) still succeeds.
    $this->assertCount(0, $purchase->validate());
    $purchase->save();
    $this->assertEquals($item->id(), InventoryPurchase::load($purchase->id())->get('item')->target_id);
  }

}
