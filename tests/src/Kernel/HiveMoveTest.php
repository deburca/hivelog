<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryMembership;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\hivelog\Form\HiveMoveForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests history stamping and moving a hive between apiaries (task 0231).
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HiveMoveTest extends KernelTestBase {

  use UserCreationTrait;

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
   * The site permissions a beekeeper's role carries in these tests.
   */
  protected const PERMISSIONS = [
    'view own apiary', 'edit own apiary', 'delete own apiary',
    'view own hive', 'edit own hive', 'delete own hive', 'add hive',
    'view own hive inspection', 'edit own hive inspection', 'delete own hive inspection',
    'view own queen', 'edit own queen', 'view own queen observation', 'edit own queen observation',
    'view own hive action log', 'edit own hive action log',
    'view own inventory item', 'edit own inventory item',
    'view own inventory purchase', 'edit own inventory purchase', 'delete own inventory purchase',
    'view own hive component', 'edit own hive component', 'add hive component',
  ];

  /**
   * The apiary the hive starts in.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiaryA;

  /**
   * The apiary it moves to.
   *
   * @var \Drupal\hivelog\Entity\Apiary
   */
  protected Apiary $apiaryB;

  /**
   * The hive.
   *
   * @var \Drupal\hivelog\Entity\Hive
   */
  protected Hive $hive;

  /**
   * A beekeeper in both apiaries.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $mover;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach ([
      'user', 'file', 'apiary', 'apiary_membership', 'hive', 'hive_inspection', 'queen',
      'queen_observation', 'calendar_action', 'hive_action_log', 'apiary_action_log',
      'inventory_item', 'inventory_purchase', 'inventory_usage', 'product', 'hive_component',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);
    // Skip uid 1, which bypasses every access check.
    $this->createUser();

    $this->mover = $this->createUser(self::PERMISSIONS);
    $this->apiaryA = $this->apiary('Apiary A', $this->mover, 'keeper');
    $this->apiaryB = $this->apiary('Apiary B', $this->mover, 'keeper');
    $this->hive = Hive::create(['name' => 'H-1', 'apiary' => $this->apiaryA->id(), 'status' => 'active']);
    $this->hive->save();
    $this->setCurrentUser($this->mover);
  }

  /**
   * Creates an apiary owned by someone else, with $member in the given role.
   */
  protected function apiary(string $name, $member, string $role): Apiary {
    $owner = $this->createUser(self::PERMISSIONS);
    $apiary = Apiary::create(['name' => $name, 'uid' => $owner->id()]);
    $apiary->save();
    ApiaryMembership::create(['apiary' => $apiary->id(), 'account' => $member->id(), 'role' => $role])->save();
    return $apiary;
  }

  /**
   * Adds a user with a role to an apiary.
   */
  protected function member(Apiary $apiary, string $role) {
    $user = $this->createUser(self::PERMISSIONS);
    ApiaryMembership::create(['apiary' => $apiary->id(), 'account' => $user->id(), 'role' => $role])->save();
    return $user;
  }

  /**
   * Creates a durable item in an apiary with one purchase at a given price.
   */
  protected function item(Apiary $apiary, string $name, int $bought, float $price): InventoryItem {
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => $name,
      'unit' => 'pcs',
      'item_type' => 'durable',
      'useful_life_years' => 10,
      'status' => 'active',
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-10',
      'quantity' => $bought,
      'unit_price' => $price,
    ])->save();
    return $item;
  }

  /**
   * Tests a record keeps the apiary it was written under after a move.
   */
  public function testHistoryKeepsApiaryAfterMove(): void {
    $inspection = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => '2026-06-01']);
    $inspection->save();
    $queen = Queen::create(['name' => 'Q', 'hive' => $this->hive->id(), 'queen_year' => 2025, 'status' => 'active']);
    $queen->save();
    $observation = QueenObservation::create(['queen' => $queen->id(), 'observation_date' => '2026-06-02']);
    $observation->save();
    $action = CalendarAction::create([
      'apiary' => $this->apiaryA->id(),
      'title' => 'Job',
      'week_start' => 20,
      'scope' => 'hive',
    ]);
    $action->save();
    $log = HiveActionLog::create([
      'hive' => $this->hive->id(),
      'calendar_action' => $action->id(),
      'year' => 2026,
      'status' => 'done',
    ]);
    $log->save();

    foreach ([$inspection, $observation, $log] as $record) {
      $this->assertSame((string) $this->apiaryA->id(), (string) $record->get('apiary')->target_id, 'Stamped on creation.');
    }

    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->apiaryB, []);

    $storage = \Drupal::entityTypeManager();
    foreach (['hive_inspection' => $inspection, 'queen_observation' => $observation, 'hive_action_log' => $log] as $type => $record) {
      $reloaded = $storage->getStorage($type)->loadUnchanged($record->id());
      assert($reloaded instanceof ContentEntityInterface);
      $this->assertSame((string) $this->apiaryA->id(), (string) $reloaded->get('apiary')->target_id, "$type keeps apiary A after the move.");
      $reloaded->save();
      $again = $storage->getStorage($type)->loadUnchanged($record->id());
      assert($again instanceof ContentEntityInterface);
      $this->assertSame((string) $this->apiaryA->id(), (string) $again->get('apiary')->target_id, "$type is not re-stamped when saved again.");
    }

    $after = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => '2026-07-01']);
    $after->save();
    $this->assertSame((string) $this->apiaryB->id(), (string) $after->get('apiary')->target_id, 'A new record carries the new apiary.');
  }

  /**
   * Tests who may read and who may change records after their hive moved.
   */
  public function testAccessFollowsTheApiaryTheRecordWasWrittenUnder(): void {
    $a_keeper = $this->member($this->apiaryA, 'keeper');
    $b_keeper = $this->member($this->apiaryB, 'keeper');
    $b_novice = $this->member($this->apiaryB, 'novice');
    $old = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => '2026-06-01']);
    $old->save();

    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->apiaryB, []);
    $new = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => '2026-07-01']);
    $new->save();
    // A later request loads the record and its hive afresh.
    \Drupal::entityTypeManager()->getStorage('hive')->resetCache();
    \Drupal::entityTypeManager()->getStorage('hive_inspection')->resetCache();
    $old = HiveInspection::load($old->id());
    $new = HiveInspection::load($new->id());

    // The old apiary keeps reading, and changing, what it wrote; nothing new.
    $this->assertTrue($old->access('view', $a_keeper));
    $this->assertTrue($old->access('update', $a_keeper));
    $this->assertFalse($new->access('view', $a_keeper));

    // The new apiary reads the hive's whole history but changes only its own.
    $this->assertTrue($old->access('view', $b_keeper));
    $this->assertTrue($old->access('view', $b_novice));
    $this->assertFalse($old->access('update', $b_keeper), 'History written under apiary A is not edited from apiary B.');
    $this->assertFalse($old->access('delete', $b_keeper));
    $this->assertTrue($new->access('update', $b_keeper));
    $this->assertFalse($new->access('update', $b_novice));
  }

  /**
   * Tests a hive without parts moves, and a move to its own apiary is refused.
   */
  public function testMoveValidation(): void {
    $mover = \Drupal::service('hivelog.hive_mover');
    $this->assertSame([], $mover->dependents($this->hive));

    $mover->move($this->hive, $this->apiaryB, []);
    $this->assertSame((string) $this->apiaryB->id(), (string) Hive::load($this->hive->id())->get('apiary')->target_id);

    $this->expectException(\InvalidArgumentException::class);
    $mover->move(Hive::load($this->hive->id()), $this->apiaryB, []);
  }

  /**
   * Tests every part needs a choice.
   */
  public function testPartWithoutChoiceIsRefused(): void {
    $item = $this->item($this->apiaryA, 'Brood box', 5, 10.0);
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();

    $this->expectException(\InvalidArgumentException::class);
    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->apiaryB, []);
  }

  /**
   * Tests detaching components removes them and records nothing.
   */
  public function testDetachingComponents(): void {
    $item = $this->item($this->apiaryA, 'Brood box', 5, 10.0);
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();

    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->apiaryB, ['components' => 'detach']);

    $this->assertSame([], \Drupal::entityTypeManager()->getStorage('hive_component')->loadByProperties(['hive' => $this->hive->id()]));
    $this->assertSame(5.0, $item->getTotalPurchasedQuantity(), 'The source keeps its stock.');
    $this->assertSame([], \Drupal::entityTypeManager()->getStorage('inventory_purchase')->loadByProperties(['apiary' => $this->apiaryB->id()]));
  }

  /**
   * Tests moving components carries their quantity and cost to the new apiary.
   */
  public function testMovingComponentsCarriesStockAndCost(): void {
    $item = $this->item($this->apiaryA, 'Brood box', 5, 10.0);
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();

    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->apiaryB, ['components' => 'move']);

    // The target gets a matching item, bought at the same price.
    $target_items = \Drupal::entityTypeManager()->getStorage('inventory_item')
      ->loadByProperties(['apiary' => $this->apiaryB->id(), 'name' => 'Brood box']);
    $this->assertCount(1, $target_items);
    $target_item = reset($target_items);
    assert($target_item instanceof InventoryItem);
    $this->assertSame(2.0, $target_item->getTotalPurchasedQuantity());
    $this->assertEqualsWithDelta(10.0, $target_item->getWeightedAverageUnitCost(), 0.001);
    $this->assertSame(2.0, $target_item->getAssignedToHivesQuantity());
    $this->assertSame(0.0, $target_item->getAvailableForHiveAssignmentQuantity());

    // The source gives the stock and its cost up, and has nothing assigned.
    $this->assertSame(3.0, $item->getTotalPurchasedQuantity());
    $this->assertSame(0.0, $item->getAssignedToHivesQuantity());
    $this->assertEqualsWithDelta(10.0, $item->getWeightedAverageUnitCost(), 0.001);

    // The component now uses the target item.
    $components = \Drupal::entityTypeManager()->getStorage('hive_component')->loadByProperties(['hive' => $this->hive->id()]);
    $this->assertCount(1, $components);
    $component = reset($components);
    assert($component instanceof HiveComponent);
    $this->assertSame((string) $target_item->id(), (string) $component->get('item')->target_id);

    // Moving a second hive's components reuses the matching item.
    $second = Hive::create(['name' => 'H-2', 'apiary' => $this->apiaryA->id(), 'status' => 'active']);
    $second->save();
    HiveComponent::create(['hive' => $second->id(), 'item' => $item->id(), 'quantity' => 1])->save();
    \Drupal::service('hivelog.hive_mover')->move($second, $this->apiaryB, ['components' => 'move']);
    $matching = \Drupal::entityTypeManager()->getStorage('inventory_item')
      ->loadByProperties(['apiary' => $this->apiaryB->id(), 'name' => 'Brood box']);
    $this->assertCount(1, $matching);
  }

  /**
   * Tests the transfer purchases cannot be edited or removed by anyone.
   */
  public function testTransferPurchasesAreFrozen(): void {
    $item = $this->item($this->apiaryA, 'Brood box', 5, 10.0);
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();
    \Drupal::service('hivelog.hive_mover')->move($this->hive, $this->apiaryB, ['components' => 'move']);

    $transfers = array_values(\Drupal::entityTypeManager()->getStorage('inventory_purchase')->loadByProperties(['transfer' => TRUE]));
    $this->assertCount(2, $transfers);
    $quantities = array_map(fn(InventoryPurchase $purchase): float => (float) $purchase->get('quantity')->value, $transfers);
    sort($quantities);
    $this->assertSame([-2.0, 2.0], $quantities);
    foreach ($transfers as $purchase) {
      $this->assertFalse($purchase->access('update', $this->mover));
      $this->assertFalse($purchase->access('delete', $this->mover));
    }
    // An ordinary purchase is still changeable by a beekeeper.
    $ordinary = array_values(\Drupal::entityTypeManager()->getStorage('inventory_purchase')->loadByProperties(['transfer' => FALSE]));
    $this->assertTrue($ordinary[0]->access('update', $this->mover));
  }

  /**
   * Tests the targets offered are the other apiaries the user may write in.
   */
  public function testTargetsAreTheOtherApiariesTheUserMayWriteIn(): void {
    $novice_apiary = $this->apiary('Apiary C', $this->mover, 'novice');
    $this->apiary('Apiary D', $this->createUser(), 'novice');

    $targets = \Drupal::service('hivelog.hive_mover')->targets($this->hive, $this->mover);

    $this->assertSame([(string) $this->apiaryB->id()], array_map('strval', array_keys($targets)));
    $this->assertArrayNotHasKey($novice_apiary->id(), $targets);
  }

  /**
   * Tests a hive with parts cannot have its apiary changed directly.
   */
  public function testApiaryCannotBeChangedDirectlyWhenPartsAreAttached(): void {
    $item = $this->item($this->apiaryA, 'Brood box', 5, 10.0);
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();

    $hive = Hive::load($this->hive->id());
    $hive->set('apiary', $this->apiaryB->id());
    $messages = array_map('strval', iterator_to_array($hive->validate()->getByField('apiary')));
    $this->assertCount(1, $messages);
    $this->assertStringContainsString('move dialog', $messages[0]);

    // A hive with nothing attached can be changed (it still needs the right to
    // write in the target apiary, which the mover has).
    $bare = Hive::create(['name' => 'Bare', 'apiary' => $this->apiaryA->id(), 'status' => 'active']);
    $bare->save();
    $bare->set('apiary', $this->apiaryB->id());
    $this->assertCount(0, $bare->validate()->getByField('apiary'));
  }

  /**
   * Tests the move form moves the hive and applies the chosen outcome.
   */
  public function testMoveFormMovesTheHive(): void {
    $item = $this->item($this->apiaryA, 'Brood box', 5, 10.0);
    HiveComponent::create(['hive' => $this->hive->id(), 'item' => $item->id(), 'quantity' => 2])->save();

    $form_state = (new FormState())->setValues([
      'target' => $this->apiaryB->id(),
      'parts' => ['components' => 'move'],
    ]);
    \Drupal::formBuilder()->submitForm(HiveMoveForm::class, $form_state, $this->hive);

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame((string) $this->apiaryB->id(), (string) Hive::load($this->hive->id())->get('apiary')->target_id);
    $this->assertTrue($form_state->getRedirect() !== NULL || $form_state->isRedirectDisabled() === FALSE);
  }

  /**
   * Tests the move form refuses an apiary the user cannot write in.
   */
  public function testMoveFormRefusesAnApiaryTheUserCannotWriteIn(): void {
    $other = $this->apiary('Apiary E', $this->createUser(), 'novice');

    $form_state = (new FormState())->setValues(['target' => $other->id(), 'parts' => []]);
    \Drupal::formBuilder()->submitForm(HiveMoveForm::class, $form_state, $this->hive);

    $this->assertArrayHasKey('target', $form_state->getErrors());
    $this->assertSame((string) $this->apiaryA->id(), (string) Hive::load($this->hive->id())->get('apiary')->target_id);
  }

  /**
   * Tests the update hook stamps existing records and is safe to run twice.
   */
  public function testUpdateHookBackfillsTheStamp(): void {
    \Drupal::moduleHandler()->loadInclude('hivelog', 'install');
    $inspection = HiveInspection::create(['hive' => $this->hive->id(), 'inspection_date' => '2026-06-01']);
    $inspection->save();
    \Drupal::database()->update('hivelog_hive_inspection')->fields(['apiary' => NULL])->execute();
    \Drupal::entityTypeManager()->getStorage('hive_inspection')->resetCache();
    $this->assertNull(HiveInspection::load($inspection->id())->get('apiary')->target_id);

    $sandbox = [];
    $update = 'hivelog_update_' . $this->updateNumber;
    $update($sandbox);
    $update($sandbox);

    $this->assertSame((string) $this->apiaryA->id(), (string) HiveInspection::load($inspection->id())->get('apiary')->target_id);
  }

  /**
   * The update hook number the backfill test runs.
   */
  protected int $updateNumber = 10036;

}
