<?php

declare(strict_types=1);

namespace Drupal\Tests\hivelog\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\ApiaryActionLog;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Entity\CalendarActionItemRequirement;
use Drupal\hivelog\Entity\CalendarActionProductYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\HiveActionLog;
use Drupal\hivelog\Entity\HiveInspection;
use Drupal\hivelog\Entity\InventoryItem;
use Drupal\hivelog\Entity\InventoryPurchase;
use Drupal\hivelog\Entity\Product;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Entity\QueenObservation;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests `HivelogEntityDeleteForm`'s cancel / post-delete redirect (task 0127).
 *
 * Covers every core hivelog content entity type with a data provider: the
 * normal case (parent present) and, for every type with a parent field,
 * the missing-parent fallback (a deleted apiary, an unassigned queen, …).
 * The three submodule types (`sensor_device`, `ai_provider_config`,
 * `api_client`) are collection-threaded — no parent field at all — and
 * are covered live on cms2 rather than here, to avoid pulling their own
 * dependency chains (key module, JWT, …) into this module's kernel suite.
 */
#[Group('hivelog')]
#[RunTestsInSeparateProcesses]
class HivelogEntityDeleteFormTest extends KernelTestBase {

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
   * A shared Queen, parented to `$hive`.
   */
  protected Queen $queen;

  /**
   * A shared Calendar Action, parented to `$apiary`.
   */
  protected CalendarAction $calendarAction;

  /**
   * A shared Inventory Item, parented to `$apiary` (for requirement/purchase).
   */
  protected InventoryItem $inventoryItem;

  /**
   * A shared Product, parented to `$apiary` (for yield).
   */
  protected Product $product;

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
    $this->installEntitySchema('hive_inspection');
    $this->installEntitySchema('queen');
    $this->installEntitySchema('queen_observation');
    $this->installEntitySchema('calendar_action');
    $this->installEntitySchema('calendar_action_item_requirement');
    $this->installEntitySchema('calendar_action_product_yield');
    $this->installEntitySchema('hive_action_log');
    $this->installEntitySchema('apiary_action_log');
    $this->installEntitySchema('inventory_item');
    $this->installEntitySchema('inventory_purchase');
    $this->installEntitySchema('inventory_usage');
    $this->installEntitySchema('product');
    $this->installEntitySchema('harvest_yield');
    $this->installSchema('file', ['file_usage']);

    $role = Role::create(['id' => 'admin', 'label' => 'Admin']);
    $role->grantPermission('administer hivelog');
    $role->save();
    $user = User::create(['name' => 'admin', 'mail' => 'admin@example.com']);
    $user->addRole('admin');
    $user->save();
    \Drupal::currentUser()->setAccount($user);

    $this->apiary = Apiary::create(['name' => 'Delete Form Apiary']);
    $this->apiary->save();

    $this->hive = Hive::create(['name' => 'Delete Form Hive', 'apiary' => $this->apiary->id(), 'status' => 'active']);
    $this->hive->save();

    $this->queen = Queen::create([
      'name' => 'Delete Form Queen',
      'hive' => $this->hive->id(),
      'queen_year' => 2025,
      'status' => 'active',
    ]);
    $this->queen->save();

    $this->calendarAction = CalendarAction::create([
      'apiary' => $this->apiary->id(),
      'title' => 'Delete Form Calendar Action',
      'description' => 'Desc.',
      'week_start' => 10,
    ]);
    $this->calendarAction->save();

    $this->inventoryItem = InventoryItem::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Delete Form Item',
      'unit' => 'kg',
      'item_type' => 'consumable',
    ]);
    $this->inventoryItem->save();

    $this->product = Product::create([
      'apiary' => $this->apiary->id(),
      'name' => 'Delete Form Product',
      'unit' => 'kg',
      'expected_unit_price' => 12,
    ]);
    $this->product->save();
  }

  /**
   * Entity type IDs covered by this test.
   */
  public static function entityTypeProvider(): array {
    return [
      'apiary' => ['apiary'],
      'hive' => ['hive'],
      'hive_inspection' => ['hive_inspection'],
      'queen' => ['queen'],
      'queen_observation' => ['queen_observation'],
      'calendar_action' => ['calendar_action'],
      'calendar_action_item_requirement' => ['calendar_action_item_requirement'],
      'calendar_action_product_yield' => ['calendar_action_product_yield'],
      'hive_action_log' => ['hive_action_log'],
      'apiary_action_log' => ['apiary_action_log'],
      'inventory_item' => ['inventory_item'],
      'inventory_purchase' => ['inventory_purchase'],
      'product' => ['product'],
    ];
  }

  /**
   * The expected shape of the rule for each entity type.
   *
   * `has_canonical`: whether the type has its own canonical page.
   * `parent_field` / `parent_type`: the parent reference field and the
   * parent's entity type ID — NULL for the two types with no parent
   * field at all.
   * `own_collection`: the type's own collection route name, or NULL if
   * it has none (falls through to the dashboard).
   */
  protected function hierarchyRules(): array {
    return [
      'apiary' => [
        'has_canonical' => TRUE,
        'parent_field' => NULL,
        'parent_type' => NULL,
        'own_collection' => 'entity.apiary.collection',
      ],
      'hive' => [
        'has_canonical' => TRUE,
        'parent_field' => 'apiary',
        'parent_type' => 'apiary',
        'own_collection' => NULL,
      ],
      'hive_inspection' => [
        'has_canonical' => TRUE,
        'parent_field' => 'hive',
        'parent_type' => 'hive',
        'own_collection' => NULL,
      ],
      'queen' => [
        'has_canonical' => TRUE,
        'parent_field' => 'hive',
        'parent_type' => 'hive',
        'own_collection' => 'entity.queen.collection',
      ],
      'queen_observation' => [
        'has_canonical' => TRUE,
        'parent_field' => 'queen',
        'parent_type' => 'queen',
        'own_collection' => 'entity.queen_observation.collection',
      ],
      'calendar_action' => [
        'has_canonical' => TRUE,
        'parent_field' => 'apiary',
        'parent_type' => 'apiary',
        'own_collection' => NULL,
      ],
      'calendar_action_item_requirement' => [
        'has_canonical' => FALSE,
        'parent_field' => 'calendar_action',
        'parent_type' => 'calendar_action',
        'own_collection' => NULL,
      ],
      'calendar_action_product_yield' => [
        'has_canonical' => FALSE,
        'parent_field' => 'calendar_action',
        'parent_type' => 'calendar_action',
        'own_collection' => NULL,
      ],
      'hive_action_log' => [
        'has_canonical' => TRUE,
        'parent_field' => 'hive',
        'parent_type' => 'hive',
        'own_collection' => NULL,
      ],
      'apiary_action_log' => [
        'has_canonical' => TRUE,
        'parent_field' => 'apiary',
        'parent_type' => 'apiary',
        'own_collection' => NULL,
      ],
      'inventory_item' => [
        'has_canonical' => TRUE,
        'parent_field' => 'apiary',
        'parent_type' => 'apiary',
        'own_collection' => 'entity.inventory_item.collection',
      ],
      'inventory_purchase' => [
        'has_canonical' => TRUE,
        'parent_field' => 'apiary',
        'parent_type' => 'apiary',
        'own_collection' => 'entity.inventory_purchase.collection',
      ],
      'product' => [
        'has_canonical' => TRUE,
        'parent_field' => 'apiary',
        'parent_type' => 'apiary',
        'own_collection' => 'entity.product.collection',
      ],
    ];
  }

  /**
   * Builds a fixture of `$type_id`, with or without its parent reference.
   */
  protected function buildEntity(string $type_id, bool $with_parent): FieldableEntityInterface {
    $entity = match ($type_id) {
      'apiary' => Apiary::create(['name' => 'Orphan Test Apiary']),
      'hive' => Hive::create([
        'name' => 'Orphan Test Hive',
        'status' => 'active',
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'hive_inspection' => HiveInspection::create([
        'inspection_date' => '2026-01-01',
      ] + ($with_parent ? ['hive' => $this->hive->id()] : [])),
      'queen' => Queen::create([
        'name' => 'Orphan Test Queen',
        'queen_year' => 2025,
        'status' => 'active',
      ] + ($with_parent ? ['hive' => $this->hive->id()] : [])),
      'queen_observation' => QueenObservation::create([
        'observation_date' => '2026-01-01',
        'health' => 'good',
      ] + ($with_parent ? ['queen' => $this->queen->id()] : [])),
      'calendar_action' => CalendarAction::create([
        'title' => 'Orphan Test Calendar Action',
        'description' => 'Desc.',
        'week_start' => 11,
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'calendar_action_item_requirement' => CalendarActionItemRequirement::create([
        'item' => $this->inventoryItem->id(),
        'quantity' => 1,
      ] + ($with_parent ? ['calendar_action' => $this->calendarAction->id()] : [])),
      'calendar_action_product_yield' => CalendarActionProductYield::create([
        'product' => $this->product->id(),
        'quantity' => 1,
      ] + ($with_parent ? ['calendar_action' => $this->calendarAction->id()] : [])),
      'hive_action_log' => HiveActionLog::create([
        'calendar_action' => $this->calendarAction->id(),
      ] + ($with_parent ? ['hive' => $this->hive->id()] : [])),
      'apiary_action_log' => ApiaryActionLog::create([
        'calendar_action' => $this->calendarAction->id(),
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'inventory_item' => InventoryItem::create([
        'name' => 'Orphan Test Item',
        'unit' => 'kg',
        'item_type' => 'consumable',
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'inventory_purchase' => InventoryPurchase::create([
        'item' => $this->inventoryItem->id(),
        'purchase_date' => '2026-01-01',
        'quantity' => 1,
        'unit_price' => 1,
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      'product' => Product::create([
        'name' => 'Orphan Test Product',
        'unit' => 'kg',
        'expected_unit_price' => 1,
      ] + ($with_parent ? ['apiary' => $this->apiary->id()] : [])),
      default => throw new \InvalidArgumentException("Unknown entity type $type_id"),
    };
    $entity->save();
    return $entity;
  }

  /**
   * Invokes the protected `getRedirectUrl()` on a delete form object.
   */
  protected function invokeGetRedirectUrl(object $form_object): Url {
    $method = new \ReflectionMethod($form_object, 'getRedirectUrl');
    $method->setAccessible(TRUE);
    return $method->invoke($form_object);
  }

  /**
   * Tests cancel URL and post-delete redirect, including the fallback.
   */
  #[DataProvider('entityTypeProvider')]
  public function testCancelAndRedirectFollowTheRule(string $type_id): void {
    $rule = $this->hierarchyRules()[$type_id];

    // Normal case: parent present (or, for `apiary`, the only case).
    $entity = $this->buildEntity($type_id, TRUE);
    /** @var \Drupal\hivelog\Form\HivelogEntityDeleteForm $form_object */
    $form_object = \Drupal::entityTypeManager()->getFormObject($type_id, 'delete');
    $form_object->setEntity($entity);

    $expected_cancel = $this->expectedParentOrFallbackUrl($rule, $entity, TRUE);
    $this->assertEquals($expected_cancel->toString(), $form_object->getCancelUrl()->toString(), "$type_id cancel URL (parent present)");

    $expected_redirect = $this->expectedParentOrFallbackUrl($rule, $entity, FALSE);
    $this->assertEquals($expected_redirect->toString(), $this->invokeGetRedirectUrl($form_object)->toString(), "$type_id post-delete redirect (parent present)");

    if (!$rule['parent_field']) {
      return;
    }

    // Missing-parent fallback: a deleted apiary, an unassigned queen.
    $orphan = $this->buildEntity($type_id, FALSE);
    /** @var \Drupal\hivelog\Form\HivelogEntityDeleteForm $orphan_form */
    $orphan_form = \Drupal::entityTypeManager()->getFormObject($type_id, 'delete');
    $orphan_form->setEntity($orphan);

    $expected_orphan_cancel = $this->expectedParentOrFallbackUrl($rule, $orphan, TRUE);
    $this->assertEquals($expected_orphan_cancel->toString(), $orphan_form->getCancelUrl()->toString(), "$type_id cancel URL (missing parent)");

    $expected_orphan_redirect = $this->expectedParentOrFallbackUrl($rule, $orphan, FALSE);
    $this->assertEquals($expected_orphan_redirect->toString(), $this->invokeGetRedirectUrl($orphan_form)->toString(), "$type_id post-delete redirect (missing parent)");
  }

  /**
   * The URL the rule predicts for `$entity`.
   *
   * @param array $rule
   *   One `hierarchyRules()` entry for `$entity`'s type.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being deleted.
   * @param bool $for_cancel
   *   TRUE to compute the cancel URL (own canonical wins first), FALSE
   *   for the post-delete redirect (parent's canonical wins first).
   */
  protected function expectedParentOrFallbackUrl(array $rule, FieldableEntityInterface $entity, bool $for_cancel): Url {
    if ($for_cancel && $rule['has_canonical']) {
      $type_id = $entity->getEntityTypeId();
      return Url::fromRoute("entity.$type_id.canonical", [$type_id => $entity->id()]);
    }
    if ($rule['parent_field']) {
      $parent_id = $entity->get($rule['parent_field'])->target_id;
      if ($parent_id) {
        return Url::fromRoute("entity.{$rule['parent_type']}.canonical", [$rule['parent_type'] => $parent_id]);
      }
    }
    if ($rule['own_collection']) {
      return Url::fromRoute($rule['own_collection']);
    }
    return Url::fromRoute('hivelog.dashboard');
  }

}
