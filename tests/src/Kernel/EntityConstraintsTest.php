<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveComponent;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the data-integrity and parent-access entity constraints (task 0200).
 *
 * Every rule here used to live only in a form's `validateForm()` (with a
 * `preSave()` throw behind it), so a non-form caller got a 500 instead of a
 * validation error. Each is now a constraint on the entity, which a form, a
 * JSON:API request and `$entity->validate()` all share. The parent-access
 * cases are the security half of it: spike 0198 showed a user could create
 * records in, or move records into, another beekeeper's apiary or hive
 * through a generic API, because create access cannot see the target parent.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class EntityConstraintsTest extends KernelTestBase {

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
   * The beekeeper whose records are "mine" in the parent-access tests.
   */
  protected User $me;

  /**
   * Another beekeeper, with the same permissions and no relation to mine.
   */
  protected User $them;

  /**
   * An `administer hivelog` user.
   */
  protected User $admin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach ([
      'user', 'file', 'apiary', 'hive', 'hive_inspection', 'queen', 'queen_observation', 'calendar_action',
      'hive_action_log', 'apiary_action_log', 'inventory_item', 'inventory_purchase', 'product',
      'calendar_action_item_requirement', 'calendar_action_product_yield', 'hive_component',
    ] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('file', ['file_usage']);

    // The first user is uid 1 and bypasses every permission check.
    User::create(['name' => 'uid1_throwaway', 'mail' => 'uid1@example.com'])->save();

    $role = Role::create(['id' => 'beekeeper', 'label' => 'Beekeeper']);
    foreach ([
      'apiary', 'hive', 'hive inspection', 'queen', 'queen observation',
      'calendar action', 'hive action log', 'apiary action log',
      'inventory item', 'inventory purchase', 'product',
      'calendar action item requirement', 'calendar action product yield', 'hive component',
    ] as $phrase) {
      foreach (['view own ', 'edit own ', 'delete own ', 'add '] as $prefix) {
        $role->grantPermission($prefix . $phrase);
      }
    }
    $role->save();
    $admin_role = Role::create(['id' => 'hivelog_admin', 'label' => 'Hivelog Admin']);
    $admin_role->grantPermission('administer hivelog');
    $admin_role->save();

    $this->me = $this->createUser('me', 'beekeeper');
    $this->them = $this->createUser('them', 'beekeeper');
    $this->admin = $this->createUser('admin', 'hivelog_admin');
  }

  /**
   * Creates a user with one role.
   */
  protected function createUser(string $name, string $role): User {
    $user = User::create(['name' => $name, 'mail' => $name . '@example.com']);
    $user->addRole($role);
    $user->save();
    return $user;
  }

  /**
   * Builds one apiary's worth of parent records owned by a user.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Keyed by entity type ID.
   */
  protected function buildSide(User $owner, string $label): array {
    $uid = $owner->id();
    $apiary = Apiary::create(['name' => $label . ' Apiary', 'uid' => $uid, 'visibility' => 'private']);
    $apiary->save();
    $hive = Hive::create(['name' => $label . ' Hive', 'apiary' => $apiary->id(), 'status' => 'active', 'uid' => $uid]);
    $hive->save();
    $queen = Queen::create(['name' => $label . ' Queen', 'hive' => $hive->id(), 'queen_year' => 2025, 'uid' => $uid]);
    $queen->save();
    $action = CalendarAction::create([
      'apiary' => $apiary->id(),
      'title' => $label . ' Action',
      'description' => 'Desc.',
      'week_start' => 15,
      'uid' => $uid,
    ]);
    $action->save();
    $item = InventoryItem::create([
      'apiary' => $apiary->id(),
      'name' => $label . ' Item',
      'unit' => 'each',
      'item_type' => 'consumable',
      'uid' => $uid,
    ]);
    $item->save();
    InventoryPurchase::create([
      'apiary' => $apiary->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-01-01',
      'quantity' => 5,
      'unit_price' => 2,
      'uid' => $uid,
    ])->save();
    $product = Product::create([
      'apiary' => $apiary->id(),
      'name' => $label . ' Product',
      'unit' => 'kg',
      'expected_unit_price' => 10,
      'uid' => $uid,
    ]);
    $product->save();
    return [
      'apiary' => $apiary,
      'hive' => $hive,
      'queen' => $queen,
      'calendar_action' => $action,
      'inventory_item' => $item,
      'product' => $product,
    ];
  }

  /**
   * Gets the violation messages reported on one field.
   *
   * @return string[]
   *   The messages whose property path is the field or below it.
   */
  protected function messagesOn(FieldableEntityInterface $entity, string $field): array {
    $messages = [];
    foreach ($entity->validate() as $violation) {
      $path = $violation->getPropertyPath();
      if ($path === $field || str_starts_with($path, $field . '.')) {
        $messages[] = (string) $violation->getMessage();
      }
    }
    return $messages;
  }

  /**
   * Builds, for each child type, an unsaved entity pointing at a side.
   *
   * @return array<string, array{0: callable, 1: string}>
   *   Per case: a builder taking a side (see buildSide()), and the parent
   *   field the parent-access constraint sits on.
   */
  protected function childCases(): array {
    return [
      'hive' => [fn(array $s) => Hive::create([
        'name' => 'New',
        'apiary' => $s['apiary']->id(),
        'status' => 'active',
      ]), 'apiary',
      ],
      'hive_inspection' => [fn(array $s) => HiveInspection::create([
        'hive' => $s['hive']->id(),
        'inspection_date' => '2026-06-01',
      ]), 'hive',
      ],
      'queen' => [fn(array $s) => Queen::create([
        'name' => 'New',
        'hive' => $s['hive']->id(),
        'queen_year' => 2025,
      ]), 'hive',
      ],
      'queen_observation' => [fn(array $s) => QueenObservation::create([
        'queen' => $s['queen']->id(),
        'observation_date' => '2026-06-01',
        'health' => 'good',
      ]), 'queen',
      ],
      'calendar_action' => [fn(array $s) => CalendarAction::create([
        'apiary' => $s['apiary']->id(),
        'title' => 'New',
        'description' => 'D',
        'week_start' => 10,
      ]), 'apiary',
      ],
      'hive_action_log' => [fn(array $s) => HiveActionLog::create([
        'hive' => $s['hive']->id(),
        'calendar_action' => $s['calendar_action']->id(),
      ]), 'hive',
      ],
      'apiary_action_log' => [fn(array $s) => ApiaryActionLog::create([
        'apiary' => $s['apiary']->id(),
        'calendar_action' => $s['calendar_action']->id(),
      ]), 'apiary',
      ],
      'inventory_item' => [fn(array $s) => InventoryItem::create([
        'apiary' => $s['apiary']->id(),
        'name' => 'New',
        'unit' => 'kg',
        'item_type' => 'consumable',
      ]), 'apiary',
      ],
      'inventory_purchase' => [fn(array $s) => InventoryPurchase::create([
        'apiary' => $s['apiary']->id(),
        'item' => $s['inventory_item']->id(),
        'purchase_date' => '2026-02-01',
        'quantity' => 1,
        'unit_price' => 1,
      ]), 'apiary',
      ],
      'product' => [fn(array $s) => Product::create([
        'apiary' => $s['apiary']->id(),
        'name' => 'New',
        'unit' => 'kg',
        'expected_unit_price' => 1,
      ]), 'apiary',
      ],
      'calendar_action_item_requirement' => [fn(array $s) => CalendarActionItemRequirement::create([
        'calendar_action' => $s['calendar_action']->id(),
        'item' => $s['inventory_item']->id(),
        'quantity' => 1,
      ]), 'calendar_action',
      ],
      'calendar_action_product_yield' => [fn(array $s) => CalendarActionProductYield::create([
        'calendar_action' => $s['calendar_action']->id(),
        'product' => $s['product']->id(),
        'quantity' => 1,
      ]), 'calendar_action',
      ],
      'hive_component' => [fn(array $s) => HiveComponent::create([
        'hive' => $s['hive']->id(),
        'item' => $s['inventory_item']->id(),
        'quantity' => 1,
      ]), 'hive',
      ],
    ];
  }

  /**
   * Tests that a record cannot be created under someone else's parent.
   */
  public function testParentAccessBlocksCreatingUnderForeignParent(): void {
    $mine = $this->buildSide($this->me, 'Mine');
    $theirs = $this->buildSide($this->them, 'Theirs');
    \Drupal::currentUser()->setAccount($this->me);

    foreach ($this->childCases() as $type => [$build, $field]) {
      $this->assertSame([], $this->messagesOn($build($mine), $field), "$type under my own parent is allowed");

      $messages = $this->messagesOn($build($theirs), $field);
      $this->assertCount(1, $messages, "$type under another beekeeper's parent is refused");
      $this->assertStringContainsString('You do not have permission', $messages[0], $type);
    }
  }

  /**
   * Tests the refusal names the kind of record, not the one it refused.
   */
  public function testParentAccessMessageDoesNotRevealTheParent(): void {
    $theirs = $this->buildSide($this->them, 'Secret Name');
    \Drupal::currentUser()->setAccount($this->me);

    $messages = $this->messagesOn(HiveInspection::create([
      'hive' => $theirs['hive']->id(),
      'inspection_date' => '2026-06-01',
    ]), 'hive');
    $this->assertSame(
      'You do not have permission to add records to, or move records into, the selected hive.',
      $messages[0]
    );
    $this->assertStringNotContainsString('Secret Name', $messages[0]);
  }

  /**
   * Tests an administrator is not blocked by the parent-access constraint.
   */
  public function testParentAccessAllowsAdministrators(): void {
    $theirs = $this->buildSide($this->them, 'Theirs');
    \Drupal::currentUser()->setAccount($this->admin);

    foreach ($this->childCases() as $type => [$build, $field]) {
      $this->assertSame([], $this->messagesOn($build($theirs), $field), $type);
    }
  }

  /**
   * Tests an existing record cannot be moved into someone else's parent.
   */
  public function testParentAccessBlocksReparenting(): void {
    $mine = $this->buildSide($this->me, 'Mine');
    $theirs = $this->buildSide($this->them, 'Theirs');
    \Drupal::currentUser()->setAccount($this->me);

    $hive = $mine['hive'];
    $hive->set('apiary', $theirs['apiary']->id());
    $this->assertCount(1, $this->messagesOn($hive, 'apiary'), 'A hive cannot be moved into their apiary');

    $inspection = HiveInspection::create(['hive' => $mine['hive']->id(), 'inspection_date' => '2026-06-01']);
    $inspection->save();
    $inspection->set('hive', $theirs['hive']->id());
    $this->assertCount(1, $this->messagesOn($inspection, 'hive'), 'An inspection cannot be moved to their hive');
  }

  /**
   * Tests an unchanged parent is not re-checked on an unrelated edit.
   */
  public function testParentAccessIgnoresAnUnchangedParent(): void {
    $mine = $this->buildSide($this->me, 'Mine');
    \Drupal::currentUser()->setAccount($this->me);
    $inspection = HiveInspection::create(['hive' => $mine['hive']->id(), 'inspection_date' => '2026-06-01']);
    $inspection->save();

    // The hive now belongs to someone else; I can no longer update it.
    $mine['apiary']->set('uid', $this->them->id())->save();
    $this->assertFalse(Hive::load($mine['hive']->id())->access('update'));

    $inspection = HiveInspection::load($inspection->id());
    $inspection->set('notes', 'Edited later.');
    $this->assertSame([], $this->messagesOn($inspection, 'hive'));
  }

  /**
   * Tests the same-apiary rules, for every child that has one.
   */
  public function testSameApiaryRules(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $a = $this->buildSide($this->me, 'A');
    $b = $this->buildSide($this->me, 'B');

    $cases = [
      'hive component item' => [HiveComponent::create([
        'hive' => $a['hive']->id(),
        'item' => $b['inventory_item']->id(),
        'quantity' => 1,
      ]), 'item', 'The selected item must belong to the same apiary as this hive.',
      ],
      'requirement item' => [CalendarActionItemRequirement::create([
        'calendar_action' => $a['calendar_action']->id(),
        'item' => $b['inventory_item']->id(),
        'quantity' => 1,
      ]), 'item', 'The selected item must belong to the same apiary as this calendar action.',
      ],
      'yield product' => [CalendarActionProductYield::create([
        'calendar_action' => $a['calendar_action']->id(),
        'product' => $b['product']->id(),
        'quantity' => 1,
      ]), 'product', 'The selected product must belong to the same apiary as this calendar action.',
      ],
      'purchase item' => [InventoryPurchase::create([
        'apiary' => $a['apiary']->id(),
        'item' => $b['inventory_item']->id(),
        'purchase_date' => '2026-02-01',
        'quantity' => 1,
        'unit_price' => 1,
      ]), 'item', 'The selected item must belong to the same apiary as this purchase.',
      ],
      'hive log calendar action' => [HiveActionLog::create([
        'hive' => $a['hive']->id(),
        'calendar_action' => $b['calendar_action']->id(),
      ]), 'calendar_action', 'The selected calendar action must belong to the same apiary as this hive.',
      ],
      'apiary log calendar action' => [ApiaryActionLog::create([
        'apiary' => $a['apiary']->id(),
        'calendar_action' => $b['calendar_action']->id(),
      ]), 'calendar_action', 'The selected calendar action must belong to the same apiary as this log.',
      ],
    ];
    foreach ($cases as $name => [$entity, $field, $message]) {
      $this->assertSame([$message], $this->messagesOn($entity, $field), $name);
    }
  }

  /**
   * Tests a hive component cannot be assigned past what is available.
   */
  public function testHiveComponentQuantityCannotExceedAvailable(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $side = $this->buildSide($this->me, 'A');
    $component = fn(int $quantity) => HiveComponent::create([
      'hive' => $side['hive']->id(),
      'item' => $side['inventory_item']->id(),
      'quantity' => $quantity,
    ]);

    $this->assertSame([], $this->messagesOn($component(5), 'quantity'), 'Exactly the 5 purchased is fine');
    $this->assertSame(
      ['Only 5 of "A Item" are available, not 6.'],
      $this->messagesOn($component(6), 'quantity')
    );

    // An edit does not count its own earlier quantity against itself.
    $saved = $component(3);
    $saved->save();
    $saved->set('quantity', 5);
    $this->assertSame([], $this->messagesOn($saved, 'quantity'));
    $saved->set('quantity', 6);
    $this->assertCount(1, $this->messagesOn($saved, 'quantity'));
  }

  /**
   * Tests a cross-apiary component reports one error, not also a stock one.
   */
  public function testCrossApiaryComponentDoesNotAlsoReportStock(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $a = $this->buildSide($this->me, 'A');
    $b = $this->buildSide($this->me, 'B');
    $component = HiveComponent::create([
      'hive' => $a['hive']->id(),
      'item' => $b['inventory_item']->id(),
      'quantity' => 99,
    ]);
    $this->assertCount(1, $this->messagesOn($component, 'item'));
    $this->assertSame([], $this->messagesOn($component, 'quantity'));
  }

  /**
   * Tests a durable item must have a useful life.
   */
  public function testDurableItemNeedsUsefulLife(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $side = $this->buildSide($this->me, 'A');
    $item = fn(string $type, $life = NULL) => InventoryItem::create([
      'apiary' => $side['apiary']->id(),
      'name' => 'X',
      'unit' => 'each',
      'item_type' => $type,
      'useful_life_years' => $life,
    ]);

    $this->assertSame(
      ['Durable items must have a useful life (in years) set.'],
      $this->messagesOn($item('durable'), 'useful_life_years')
    );
    $this->assertSame([], $this->messagesOn($item('durable', 5), 'useful_life_years'));
    $this->assertSame([], $this->messagesOn($item('consumable'), 'useful_life_years'));
  }

  /**
   * Tests the disposal-date rules on a purchase.
   */
  public function testPurchaseDisposalRules(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $side = $this->buildSide($this->me, 'A');
    $durable = InventoryItem::create([
      'apiary' => $side['apiary']->id(),
      'name' => 'Frames',
      'unit' => 'each',
      'item_type' => 'durable',
      'useful_life_years' => 5,
    ]);
    $durable->save();
    $purchase = fn($item, string $disposal) => InventoryPurchase::create([
      'apiary' => $side['apiary']->id(),
      'item' => $item->id(),
      'purchase_date' => '2026-03-01',
      'quantity' => 1,
      'unit_price' => 1,
      'disposal_date' => $disposal,
    ]);

    $this->assertSame([], $this->messagesOn($purchase($durable, '2026-04-01'), 'disposal_date'));
    $this->assertSame([], $this->messagesOn($purchase($durable, '2026-03-01'), 'disposal_date'), 'Same day is fine');
    $this->assertSame(
      ['The disposal date cannot be before the purchase date.'],
      $this->messagesOn($purchase($durable, '2026-01-01'), 'disposal_date')
    );
    $this->assertSame(
      ['A disposal date can only be recorded for a purchase of a durable item.'],
      $this->messagesOn($purchase($side['inventory_item'], '2026-04-01'), 'disposal_date')
    );
  }

  /**
   * Tests a calendar action's end week cannot precede its start week.
   */
  public function testCalendarActionWeekRange(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $side = $this->buildSide($this->me, 'A');
    $action = fn(int $start, int $end) => CalendarAction::create([
      'apiary' => $side['apiary']->id(),
      'title' => 'X',
      'description' => 'D',
      'week_start' => $start,
      'week_end' => $end,
    ]);

    $this->assertSame(
      ['The end week must be the same as or later than the start week.'],
      $this->messagesOn($action(20, 9), 'week_end')
    );
    $this->assertSame([], $this->messagesOn($action(9, 9), 'week_end'));
    // Numeric, not string, comparison: 9 < 10.
    $this->assertSame([], $this->messagesOn($action(9, 10), 'week_end'));
  }

  /**
   * Tests the inspection "required when" rules.
   */
  public function testInspectionDependentFields(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $side = $this->buildSide($this->me, 'A');
    $inspection = fn(array $values) => HiveInspection::create([
      'hive' => $side['hive']->id(),
      'inspection_date' => '2026-06-01',
    ] + $values);

    $this->assertSame(
      ['Feed type is required when the colony was fed.'],
      $this->messagesOn($inspection(['fed' => TRUE]), 'feed_type')
    );
    $this->assertSame([], $this->messagesOn($inspection(['fed' => TRUE, 'feed_type' => 'syrup']), 'feed_type'));
    $this->assertSame([], $this->messagesOn($inspection(['fed' => FALSE]), 'feed_type'));

    $this->assertSame(
      ['Varroa count is required when a varroa check was performed.'],
      $this->messagesOn($inspection(['varroa_check' => TRUE]), 'varroa_count')
    );
    $this->assertSame(
      [],
      $this->messagesOn($inspection(['varroa_check' => TRUE, 'varroa_count' => 0]), 'varroa_count'),
      'A count of 0 is a real count'
    );
    $this->assertSame([], $this->messagesOn($inspection(['varroa_check' => FALSE]), 'varroa_count'));
  }

  /**
   * Tests preSave() still throws for a caller that never validates.
   */
  public function testPreSaveBackstopStillThrows(): void {
    \Drupal::currentUser()->setAccount($this->admin);
    $a = $this->buildSide($this->me, 'A');
    $b = $this->buildSide($this->me, 'B');
    $component = HiveComponent::create([
      'hive' => $a['hive']->id(),
      'item' => $b['inventory_item']->id(),
      'quantity' => 1,
    ]);
    $this->expectException(\Exception::class);
    $component->save();
  }

}
