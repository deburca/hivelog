<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\Queen;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the Cancel action `HivelogEntityFormTrait` adds (task 0129).
 *
 * Covers the three shapes an add/edit form's Cancel link can take (an
 * edit form's own canonical page, a scoped add form's parent, a
 * site-wide add form's collection), the fallback for a type with no
 * canonical page of its own, and the `?destination=` override — via the
 * real render array `entity.form_builder` produces, not a mocked form.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogEntityFormCancelActionTest extends KernelTestBase {

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
   * A shared Apiary, the root of every fixture built below.
   */
  protected Apiary $apiary;

  /**
   * A shared Hive, parented to `$apiary`.
   */
  protected Hive $hive;

  /**
   * A shared Calendar Action, parented to `$apiary`.
   */
  protected CalendarAction $calendarAction;

  /**
   * A shared Inventory Item, for the requirement fixture's `item` reference.
   */
  protected InventoryItem $item;

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
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('inventory_item');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Cancel Action Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create(['name' => 'Cancel Action Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Cancel Action Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $this->calendarAction->save();

    $this->item = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Cancel Action Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $this->item->save();
  }

  /**
   * Builds the entity for one scenario and returns its Cancel action props.
   */
  protected function cancelProps(string $type_id, string $operation, bool $with_parent): array {
    $entity = match ($type_id) {
      'apiary' => Apiary::create(['name' => 'Another Apiary']),
      'hive' => $operation === 'edit'
        ? $this->hive
        : Hive::create([
          'name' => 'New Hive',
          'status' => 'active',
        ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'queen' => Queen::create([
        'name' => 'New Queen',
        'queen_year' => 2025,
        'status' => 'active',
      ] + ($with_parent ? ['hive' => $this->hive->id()] : [])),
      'inventory_item' => InventoryItem::create([
        'name' => 'New Item',
        'unit' => 'kg',
        'item_type' => 'consumable',
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'calendar_action_item_requirement' => $operation === 'edit'
        ? CalendarActionItemRequirement::create([
          'calendar_action' => $this->calendarAction->id(),
          'item' => $this->item->id(),
          'quantity' => 1,
        ])
        : CalendarActionItemRequirement::create([
          'item' => $this->item->id(),
          'quantity' => 1,
        ] + ($with_parent ? ['calendar_action' => $this->calendarAction->id()] : [])),
      default => throw new \InvalidArgumentException("Unknown entity type $type_id"),
    };
    if ($operation === 'edit') {
      $entity->save();
    }

    $build = \Drupal::service('entity.form_builder')->getForm($entity, $operation);
    return $build['actions']['cancel']['#props'];
  }

  /**
   * Every Cancel link is a default-variant `hivelog:button`.
   */
  public function testCancelButtonIsDefaultVariant(): void {
    $props = $this->cancelProps('hive', 'edit', TRUE);
    $this->assertEquals('Cancel', $props['label']);
    $this->assertEquals('default', $props['variant']);
  }

  /**
   * Tests the Cancel URL for each scenario the acceptance criteria name.
   */
  #[DataProvider('cancelScenarioProvider')]
  public function testCancelUrl(string $type_id, string $operation, bool $with_parent, string $expected_route): void {
    $props = $this->cancelProps($type_id, $operation, $with_parent);
    $this->assertStringContainsString($expected_route, $props['url']);
  }

  /**
   * Data provider for `testCancelUrl()`.
   *
   * Entity type, operation, whether the parent is set, and a fragment of
   * the expected Cancel URL's route path.
   */
  public static function cancelScenarioProvider(): array {
    return [
      // Edit form: the entity's own canonical page.
      'hive edit' => ['hive', 'edit', TRUE, '/hivelog/hive/'],
      // Scoped add form: the parent's canonical page.
      'hive add (apiary-scoped)' => ['hive', 'add', TRUE, '/hivelog/apiary/'],
      'queen add (hive-scoped)' => ['queen', 'add', TRUE, '/hivelog/hive/'],
      // Site-wide add form: the entity type's own collection.
      'apiary add (site-wide, root type)' => ['apiary', 'add', FALSE, '/hivelog/apiaries'],
      'queen add (site-wide, no hive set)' => ['queen', 'add', FALSE, '/hivelog/queens'],
      'inventory item add (site-wide, no apiary set)' => [
        'inventory_item', 'add', FALSE, '/hivelog/inventory-items',
      ],
      // No canonical page of its own: falls through to the parent.
      'requirement edit (no canonical, has parent)' => [
        'calendar_action_item_requirement', 'edit', TRUE, '/hivelog/calendar-action/',
      ],
      'requirement add (no canonical, scoped)' => [
        'calendar_action_item_requirement', 'add', TRUE, '/hivelog/calendar-action/',
      ],
    ];
  }

  /**
   * A `?destination=` query wins over the route-based Cancel URL.
   */
  public function testDestinationQueryWinsOverCancelUrl(): void {
    $request = Request::create('/hivelog/hive/' . $this->hive->id() . '/edit', 'GET', [
      'destination' => '/hivelog/apiaries',
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    \Drupal::service('request_stack')->push($request);

    $build = \Drupal::service('entity.form_builder')->getForm($this->hive, 'edit');
    $this->assertEquals('/hivelog/apiaries', $build['actions']['cancel']['#props']['url']);
  }

}
