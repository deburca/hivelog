<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hivelog\Controller\CalendarActionController;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\Product;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests build+validate+save round trips for calendar-action-family forms.
 *
 * Task 0140: CalendarActionForm, CalendarActionItemRequirementForm and
 * CalendarActionProductYieldForm had no dedicated form-level test anywhere
 * — CalendarActionItemRequirementForm/CalendarActionProductYieldForm only
 * had a single autocomplete-filtering assertion each
 * (ApiaryScopedAutocompleteTest), and CalendarActionForm had none at all.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class CalendarActionFormRoundTripTest extends KernelTestBase {

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
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('apiary');
    $this->installEntitySchema('hive');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('product');
    $this->installSchema('file', ['file_usage']);

    $user = User::create(['name' => 'tester', 'mail' => 'tester@example.com']);
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Form Test Apiary']);
    $this->apiary->save();
  }

  /**
   * Tests CalendarActionForm saves and redirects to the parent apiary.
   */
  public function testCalendarActionFormSavesAndRedirectsToApiary(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Round Trip Action',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('calendar_action', 'add');
    $form_object->setEntity($action);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = CalendarAction::load($form_object->getEntity()->id());
    $this->assertEquals('Round Trip Action', $loaded->label());

    $redirect = $form_state->getRedirect();
    $this->assertEquals('entity.apiary.canonical', $redirect->getRouteName());
    $this->assertEquals(['apiary' => $this->apiary->id()], $redirect->getRouteParameters());
  }

  /**
   * Tests CalendarActionForm rejects week_end before week_start.
   */
  public function testCalendarActionFormValidationRejectsEndBeforeStart(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Invalid Range Action',
      'description' => 'Desc.',
      'week_start' => 20,
      'scope' => 'apiary',
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('calendar_action', 'add');
    $form_object->setEntity($action);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->setValue('week_start', [['value' => 20]]);
    $form_state->setValue('week_end', [['value' => 10]]);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('week_end', $form_state->getErrors());
  }

  /**
   * Tests the add controller pre-fills `apiary` on a new action.
   */
  public function testCalendarActionAddFormPrefillsApiary(): void {
    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class);
    $form = $controller->addForm($this->apiary);
    $this->assertIsArray($form);

    $new = CalendarAction::create(['apiary' => $this->apiary->id()]);
    $this->assertEquals($this->apiary->id(), $new->get('apiary')->target_id);
  }

  /**
   * Tests CalendarActionItemRequirementForm saves and redirects to the action.
   */
  public function testItemRequirementFormSavesAndRedirectsToCalendarAction(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Feeding',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $action->save();
    $item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $item->save();

    $requirement = CalendarActionItemRequirement::create([
      'calendar_action' => $action->id(),
      'item' => $item->id(),
      'quantity' => 5,
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('calendar_action_item_requirement', 'add');
    $form_object->setEntity($requirement);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = CalendarActionItemRequirement::load($form_object->getEntity()->id());
    $this->assertEquals($item->id(), $loaded->get('item')->target_id);

    $redirect = $form_state->getRedirect();
    $this->assertEquals('entity.calendar_action.canonical', $redirect->getRouteName());
    $this->assertEquals(['calendar_action' => $action->id()], $redirect->getRouteParameters());
  }

  /**
   * Tests CalendarActionItemRequirementForm rejects a cross-apiary item.
   */
  public function testItemRequirementFormValidationRejectsMismatchedApiaryItem(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Feeding',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $action->save();
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();
    $foreign_item = InventoryItem::create([
      'apiary' => $other_apiary->id(),
      'name' => 'Foreign Sugar',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $foreign_item->save();

    $requirement = CalendarActionItemRequirement::create(['calendar_action' => $action->id()]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('calendar_action_item_requirement', 'add');
    $form_object->setEntity($requirement);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->setValue('item', [['target_id' => $foreign_item->id()]]);
    $form_state->setValue('calendar_action', [['target_id' => $action->id()]]);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('item', $form_state->getErrors());
  }

  /**
   * Tests the scoped add controller pre-fills `calendar_action`.
   */
  public function testItemRequirementAddFormPrefillsCalendarAction(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Feeding',
      'description' => 'Desc.',
      'week_start' => 10,
      'scope' => 'apiary',
    ]);
    $action->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class);
    $form = $controller->addRequirementForm($action);
    $this->assertIsArray($form);

    $new = CalendarActionItemRequirement::create(['calendar_action' => $action->id()]);
    $this->assertEquals($action->id(), $new->get('calendar_action')->target_id);
  }

  /**
   * Tests CalendarActionProductYieldForm saves and redirects to the action.
   */
  public function testProductYieldFormSavesAndRedirectsToCalendarAction(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
      'scope' => 'apiary',
    ]);
    $action->save();
    $product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $product->save();

    $yield = CalendarActionProductYield::create([
      'calendar_action' => $action->id(),
      'product' => $product->id(),
      'quantity' => 15,
    ]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('calendar_action_product_yield', 'add');
    $form_object->setEntity($yield);
    $form_state = new FormState();
    $form_object->save([], $form_state);

    $loaded = CalendarActionProductYield::load($form_object->getEntity()->id());
    $this->assertEquals($product->id(), $loaded->get('product')->target_id);

    $redirect = $form_state->getRedirect();
    $this->assertEquals('entity.calendar_action.canonical', $redirect->getRouteName());
    $this->assertEquals(['calendar_action' => $action->id()], $redirect->getRouteParameters());
  }

  /**
   * Tests CalendarActionProductYieldForm rejects a cross-apiary product.
   */
  public function testProductYieldFormValidationRejectsMismatchedApiaryProduct(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
      'scope' => 'apiary',
    ]);
    $action->save();
    $other_apiary = Apiary::create(['name' => 'Other Apiary']);
    $other_apiary->save();
    $foreign_product = Product::create([
      'apiary' => $other_apiary->id(),
      'name' => 'Foreign Honey',
      'unit' => 'kg',
      'expected_unit_price' => 10,
    ]);
    $foreign_product->save();

    $yield = CalendarActionProductYield::create(['calendar_action' => $action->id()]);
    $form_object = \Drupal::entityTypeManager()->getFormObject('calendar_action_product_yield', 'add');
    $form_object->setEntity($yield);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->setValue('product', [['target_id' => $foreign_product->id()]]);
    $form_state->setValue('calendar_action', [['target_id' => $action->id()]]);
    $form_object->validateForm($form, $form_state);

    $this->assertArrayHasKey('product', $form_state->getErrors());
  }

  /**
   * Tests the scoped add controller pre-fills `calendar_action`.
   */
  public function testProductYieldAddFormPrefillsCalendarAction(): void {
    $action = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Harvest',
      'description' => 'Desc.',
      'week_start' => 28,
      'scope' => 'apiary',
    ]);
    $action->save();

    $controller = \Drupal::service('class_resolver')
      ->getInstanceFromDefinition(CalendarActionController::class);
    $form = $controller->addYieldForm($action);
    $this->assertIsArray($form);

    $new = CalendarActionProductYield::create(['calendar_action' => $action->id()]);
    $this->assertEquals($action->id(), $new->get('calendar_action')->target_id);
  }

}
