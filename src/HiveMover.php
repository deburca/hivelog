<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\InventoryItem;

/**
 * Moves a hive from one apiary to another (task 0231).
 *
 * History is never rewritten: nothing already written is changed. Inspections,
 * queen observations and action logs carry the apiary they were written under,
 * so they stay where they are; records written after the move carry the new
 * apiary. A hive's queens go with it. What cannot follow unchanged is the
 * hive's current state, so for each part (its components, and whatever a module
 * contributes, such as nanoprobe's sensors) the user chooses to move it with the
 * hive or detach it before the move.
 *
 * Moving components carries their cost: the target apiary gets a matching item
 * (same name and unit, created if missing) and a purchase for the moved quantity
 * at the item's weighted-average unit price, and the source apiary gets a
 * matching negative purchase, both flagged `transfer`, so both apiaries' stock
 * and cost reports stay right.
 *
 * Modules add parts to the dialog with `hook_hivelog_hive_move_dependents()`
 * and act on the choice with `hook_hivelog_hive_move_apply()` (see
 * hivelog.api.php).
 */
class HiveMover {

  use StringTranslationTrait;

  /**
   * The choices every part offers, besides cancelling the whole move.
   */
  const CHOICES = ['move', 'detach'];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected Connection $database,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * The apiaries a user may move a hive to.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user moving it.
   *
   * @return \Drupal\hivelog\Entity\Apiary[]
   *   Every other apiary the user may write in (beekeeper or owner there),
   *   keyed by id.
   */
  public function targets(Hive $hive, AccountInterface $account): array {
    $storage = $this->entityTypeManager->getStorage('apiary');
    $targets = [];
    foreach ($storage->loadMultiple() as $apiary) {
      /** @var \Drupal\hivelog\Entity\Apiary $apiary */
      if ((int) $apiary->id() !== (int) $hive->get('apiary')->target_id && $apiary->access('write', $account)) {
        $targets[$apiary->id()] = $apiary;
      }
    }
    return $targets;
  }

  /**
   * The parts of a hive that need a decision before it moves.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   *
   * @return array
   *   Parts keyed by id, each `['label' => string, 'items' => string[],
   *   'choices' => ['move' => string, 'detach' => string]]`. Empty when the
   *   hive has nothing that needs deciding.
   */
  public function dependents(Hive $hive): array {
    $parts = [];
    $components = $this->components($hive);
    if ($components) {
      $items = [];
      foreach ($components as $component) {
        $item = $component->get('item')->entity;
        $items[] = $component->get('quantity')->value . ' × ' . ($item ? $item->label() : $this->t('Unknown item'));
      }
      $parts['components'] = [
        'label' => (string) $this->t('Components'),
        'items' => $items,
        'choices' => [
          'move' => (string) $this->t('Move them with the hive, with their cost (a matching item and purchase in the new apiary, and a matching deduction in this one)'),
          'detach' => (string) $this->t('Detach them from the hive first, then move it (they stay in this apiary)'),
        ],
      ];
    }
    foreach ($this->moduleHandler->invokeAll('hivelog_hive_move_dependents', [$hive]) as $id => $part) {
      $parts[$id] = $part;
    }
    return $parts;
  }

  /**
   * Moves a hive, carrying or detaching its parts as chosen.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   * @param \Drupal\hivelog\Entity\Apiary $target
   *   The apiary to move it to.
   * @param array $choices
   *   For each part from `dependents()`, `move` or `detach`.
   *
   * @throws \InvalidArgumentException
   *   If the target is the hive's own apiary or a part has no valid choice.
   */
  public function move(Hive $hive, Apiary $target, array $choices): void {
    $source = $hive->get('apiary')->entity;
    if (!$source || (int) $source->id() === (int) $target->id()) {
      throw new \InvalidArgumentException('A hive can only be moved to another apiary.');
    }
    foreach (array_keys($this->dependents($hive)) as $part) {
      if (!in_array($choices[$part] ?? NULL, self::CHOICES, TRUE)) {
        throw new \InvalidArgumentException("Choose to move or detach the hive's $part.");
      }
    }

    $transaction = $this->database->startTransaction();
    try {
      // Before: everything that cannot stay attached while the hive is in
      // another apiary is detached, and what is moving is remembered.
      /** @var array<int, array{0: \Drupal\hivelog\Entity\InventoryItem|null, 1: int}> $moving */
      $moving = [];
      foreach ($this->components($hive) as $component) {
        if (($choices['components'] ?? 'detach') === 'move') {
          $moving[] = [$component->get('item')->entity, (int) $component->get('quantity')->value];
        }
        $component->delete();
      }
      $this->moduleHandler->invokeAll('hivelog_hive_move_apply', [$hive, $source, $target, $choices, 'before']);

      // The move itself. Records written before keep the apiary they carry.
      $hive->set('apiary', $target->id());
      $hive->save();

      // After: what moves with the hive is attached to the new apiary.
      foreach ($moving as [$item, $quantity]) {
        $this->carryComponent($hive, $source, $target, $item, $quantity);
      }
      $this->moduleHandler->invokeAll('hivelog_hive_move_apply', [$hive, $source, $target, $choices, 'after']);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * The hive's component rows.
   *
   * @return \Drupal\hivelog\Entity\HiveComponent[]
   *   The components.
   */
  protected function components(Hive $hive): array {
    if ($hive->isNew()) {
      return [];
    }
    return $this->entityTypeManager->getStorage('hive_component')
      ->loadByProperties(['hive' => $hive->id()]);
  }

  /**
   * Moves one component and its cost into the target apiary.
   */
  protected function carryComponent(Hive $hive, Apiary $source, Apiary $target, ?InventoryItem $item, int $quantity): void {
    if (!$item) {
      return;
    }
    $target_item = $this->matchingItem($item, $target);
    $unit_price = $item->getWeightedAverageUnitCost();
    $date = date('Y-m-d');
    $note = (string) $this->t('Moved with hive @hive from @from to @to.', [
      '@hive' => $hive->label(),
      '@from' => $source->label(),
      '@to' => $target->label(),
    ]);
    $purchases = $this->entityTypeManager->getStorage('inventory_purchase');
    // The source apiary gives the stock and its cost up...
    $purchases->create([
      'apiary' => $source->id(),
      'item' => $item->id(),
      'purchase_date' => $date,
      'quantity' => -$quantity,
      'unit_price' => $unit_price,
      'supplier' => (string) $this->t('Transfer out'),
      'notes' => $note,
      'transfer' => TRUE,
    ])->save();
    // ...and the target apiary receives it at the same price.
    $purchases->create([
      'apiary' => $target->id(),
      'item' => $target_item->id(),
      'purchase_date' => $date,
      'quantity' => $quantity,
      'unit_price' => $unit_price,
      'supplier' => (string) $this->t('Transfer in'),
      'notes' => $note,
      'transfer' => TRUE,
    ])->save();
    $this->entityTypeManager->getStorage('hive_component')->create([
      'hive' => $hive->id(),
      'item' => $target_item->id(),
      'quantity' => $quantity,
    ])->save();
  }

  /**
   * Finds the target apiary's item that matches a source item, or creates it.
   */
  protected function matchingItem(InventoryItem $item, Apiary $target): InventoryItem {
    $storage = $this->entityTypeManager->getStorage('inventory_item');
    $found = $storage->loadByProperties([
      'apiary' => $target->id(),
      'name' => $item->get('name')->value,
      'unit' => $item->get('unit')->value,
      'item_type' => $item->get('item_type')->value,
    ]);
    if ($found) {
      return reset($found);
    }
    $values = ['apiary' => $target->id()];
    foreach (['name', 'category', 'unit', 'item_type', 'useful_life_years', 'weight_kg', 'status'] as $field) {
      if (!$item->get($field)->isEmpty()) {
        $values[$field] = $item->get($field)->value;
      }
    }
    $copy = $storage->create($values);
    $copy->save();
    return $copy;
  }

}
