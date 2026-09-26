<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Controller\InventoryItemController;
use Drupal\hivelog\Controller\InventoryPurchaseController;
use Drupal\hivelog\Controller\ProductController;
use Drupal\hivelog\Controller\QueenController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests build+validate+save round trips for four scoped entity forms.
 *
 * Task 0140: QueenForm, ProductForm, InventoryItemForm and
 * InventoryPurchaseForm had no dedicated form-level test anywhere — only
 * entity-level CRUD (bypassing the form entirely) or, for
 * InventoryPurchaseForm, a single autocomplete-filtering assertion. Each
 * covers the real `\Drupal::entityTypeManager()->getFormObject()` +
 * `save()` path used by the actual add/edit routes, the scoped-add
 * controller's parent pre-population, and the form's own redirect target.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class ScopedEntityFormRoundTripTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Form Test Apiary']);
    $this->apiary->save();
    $this->hive = Hive::create(['name' => 'Form Test Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();
  }

  /**
   * Tests QueenForm saves and redirects to the hive when one is set.
   */
  public function testQueenFormSavesAndRedirectsToHive(): void {
    $queen = Queen::create([
      'name' => 'Round Trip Queen',
      'hive' => $this->hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('queen', 'add');
    $form_object->setEntity($queen);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = Queen::load($form_object->getEntity()->id());
    $this->assertEquals('Round Trip Queen', $loaded->label());
    $this->assertEquals($this->hive->id(), $loaded->get('hive')->target_id);

    $redirect = $form_state->getRedirect();
    $this->assertEquals('entity.hive.canonical', $redirect->getRouteName());
    $this->assertEquals(['hive' => $this->hive->id()], $redirect->getRouteParameters());

    $messages = \Drupal::messenger()->all();
    $status_text = implode(' ', array_map('strval', $messages['status'] ?? []));
    $this->assertStringContainsString('has been created', $status_text);
  }

  /**
   * Tests QueenForm redirects to the queen collection with no hive set.
   */
  public function testQueenFormRedirectsToCollectionWithoutHive(): void {
    $queen = Queen::create([
      'name' => 'Hiveless Queen',
      'queen_year' => 2025,
      'status' => 'active',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('queen', 'add');
    $form_object->setEntity($queen);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $this->assertEquals('entity.queen.collection', $form_state->getRedirect()->getRouteName());
  }

  /**
   * Tests the hive-scoped add controller pre-fills `hive` on a new queen.
   */
  public function testQueenAddFormPrefillsHive(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(QueenController::class);
    $form = $controller->addForm($this->hive);
    $this->assertIsArray($form);

    $new = Queen::create(['hive' => $this->hive->id(), 'status' => 'active']);
    $this->assertEquals($this->hive->id(), $new->get('hive')->target_id);
  }

  /**
   * Tests ProductForm saves and redirects to the parent apiary.
   */
  public function testProductFormSavesAndRedirectsToApiary(): void {
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Round Trip Honey',
      'unit' => 'kg',
      'expected_unit_price' => 12.5,
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('product', 'add');
    $form_object->setEntity($product);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = Product::load($form_object->getEntity()->id());
    $this->assertEquals('Round Trip Honey', $loaded->label());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);

    $redirect_url = $form_state->getRedirect();
    $this->assertEquals($this->apiary->toUrl()->toString(), $redirect_url->toString());
  }

  /**
   * Tests the add controller pre-fills `apiary` on a new product.
   */
  public function testProductAddFormPrefillsApiary(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(ProductController::class);
    $form = $controller->addForm($this->apiary);
    $this->assertIsArray($form);

    $new = Product::create(['apiary' => $this->apiary->id()]);
    $this->assertEquals($this->apiary->id(), $new->get('apiary')->target_id);
  }

  /**
   * Tests InventoryItemForm saves and redirects to the item collection.
   */
  public function testInventoryItemFormSavesAndRedirectsToCollection(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Round Trip Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('inventory_item', 'add');
    $form_object->setEntity($item);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = InventoryItem::load($form_object->getEntity()->id());
    $this->assertEquals('Round Trip Sugar', $loaded->label());
    $this->assertEquals($this->apiary->id(), $loaded->get('apiary')->target_id);
    $this->assertEquals('entity.inventory_item.collection', $form_state->getRedirect()->getRouteName());
  }

  /**
   * Tests InventoryItemForm rejects a durable item with no useful life.
   */
  public function testInventoryItemFormValidationRejectsDurableWithoutUsefulLife(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('inventory_item', 'add');
    $form_object->setEntity($item);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->setValue('item_type', [['value' => 'durable']]);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('useful_life_years', $form_state->getErrors());
  }

  /**
   * Tests the add controller pre-fills `apiary` on a new item.
   */
  public function testInventoryItemAddFormPrefillsApiary(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InventoryItemController::class);
    $form = $controller->addForm($this->apiary);
    $this->assertIsArray($form);

    $new = InventoryItem::create(['apiary' => $this->apiary->id()]);
    $this->assertEquals($this->apiary->id(), $new->get('apiary')->target_id);
  }

  /**
   * Tests InventoryPurchaseForm saves and redirects to the collection.
   */
  public function testInventoryPurchaseFormSavesAndRedirectsToCollection(): void {
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Purchasable Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    $purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 25,
      'unit_price' => 1.5,
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('inventory_purchase', 'add');
    $form_object->setEntity($purchase);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = InventoryPurchase::load($form_object->getEntity()->id());
    $this->assertEquals($item->id(), $loaded->get('item')->target_id);
    $this->assertEquals('entity.inventory_purchase.collection', $form_state->getRedirect()->getRouteName());
  }

  /**
   * Tests InventoryPurchaseForm rejects an item from another apiary.
   */
  public function testInventoryPurchaseFormValidationRejectsMismatchedApiaryItem(): void {
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();
    $foreign_item = InventoryItem::create([
      'apiary' => $other_apiary->id(),
      'name' => 'Foreign Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $foreign_item->save();

    $purchase = InventoryPurchase::create(['apiary' => $this->apiary->id()]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('inventory_purchase', 'add');
    $form_object->setEntity($purchase);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->setValue('item', [['target_id' => $foreign_item->id()]]);
    $form_state->setValue('apiary', [['target_id' => $this->apiary->id()]]);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('item', $form_state->getErrors());
  }

  /**
   * Tests InventoryPurchaseForm rejects a disposal date before purchase.
   */
  public function testInventoryPurchaseFormValidationRejectsDisposalBeforePurchase(): void {
    $durable = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Frames',
      'unit' => 'frame',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $durable->save();

    $purchase = InventoryPurchase::create([
      'apiary' => $this->apiary->id(),
      'item' => $durable->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 20,
      'unit_price' => 3,
      'disposal_date' => '2026-01-01',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('inventory_purchase', 'add');
    $form_object->setEntity($purchase);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->setValue('item', [['target_id' => $durable->id()]]);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('disposal_date', $form_state->getErrors());
  }

  /**
   * Tests the add controller pre-fills `apiary` on a new purchase.
   */
  public function testInventoryPurchaseAddFormPrefillsApiary(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(InventoryPurchaseController::class);
    $form = $controller->addForm($this->apiary);
    $this->assertIsArray($form);

    $new = InventoryPurchase::create(['apiary' => $this->apiary->id()]);
    $this->assertEquals($this->apiary->id(), $new->get('apiary')->target_id);
  }

}
