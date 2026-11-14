<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;

/**
 * Fills an apiary's seasonal calendar from a named source (task 0249).
 *
 * A source is a string: `default` (the built-in starter calendar) or
 * `apiary:<id>` (another apiary's calendar). Every source is first turned into
 * a list of plain *definitions* (action fields plus `requirements` and
 * `yields`), and one writer turns definitions into entities, so a new kind of
 * source — a regional or hemisphere preset, say — only has to add a prefix to
 * `definitions()` and `sourceOptions()`; nothing that calls this service
 * changes. Definitions carry no apiary, user or entity ids that belong to the
 * source, so they are the same shape whether they came from code or a record.
 *
 * Applying is additive and idempotent: an action the target already has
 * (same title, scope and start week) is skipped, nothing is ever deleted, and
 * a second run adds nothing. A skipped action is not altered, but it is given
 * a requirement or yield the source has and it lacks (task 0270). Access is the caller's to check; this service
 * only filters a source apiary's actions to those the account may view.
 */
class HivelogCalendarCopier {

  use StringTranslationTrait;

  /**
   * The source id of the built-in starter calendar.
   */
  const SOURCE_DEFAULT = 'default';

  /**
   * Prefix of a source that is another apiary's calendar.
   */
  const SOURCE_APIARY_PREFIX = 'apiary:';

  /**
   * Action fields a definition carries.
   */
  const ACTION_FIELDS = [
    'title',
    'description',
    'category',
    'week_start',
    'week_end',
    'recurring',
    'enabled',
    'scope',
  ];

  /**
   * Inventory item fields a copy carries.
   */
  const ITEM_FIELDS = [
    'name',
    'category',
    'unit',
    'item_type',
    'useful_life_years',
    'weight_kg',
    'status',
  ];

  /**
   * Product fields a copy carries.
   */
  const PRODUCT_FIELDS = [
    'name',
    'category',
    'unit',
    'expected_unit_price',
    'status',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Returns the sources an account may fill a calendar from.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Defaults to the current user.
   * @param \Drupal\hivelog\Entity\Apiary|null $target
   *   The apiary being filled, left out of the list (a calendar is not copied
   *   onto itself).
   *
   * @return array<string, string>
   *   Source id => label, the built-in calendar first.
   */
  public function sourceOptions(?AccountInterface $account = NULL, ?Apiary $target = NULL): array {
    $account ??= $this->currentUser;
    $options = [self::SOURCE_DEFAULT => (string) $this->t('Starter calendar (built in)')];

    $apiaries = [];
    foreach ($this->entityTypeManager->getStorage('apiary')->loadMultiple() as $apiary) {
      if ($target && $apiary->id() === $target->id()) {
        continue;
      }
      if ($apiary->access('view', $account) && $this->viewableActions($apiary, $account)) {
        $apiaries[(string) $apiary->label() . ' #' . $apiary->id()] = $apiary;
      }
    }
    ksort($apiaries, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($apiaries as $apiary) {
      $options[self::SOURCE_APIARY_PREFIX . $apiary->id()] = (string) $this->t('Copy from @apiary', ['@apiary' => $apiary->label()]);
    }
    return $options;
  }

  /**
   * Adds the source's actions to the apiary.
   *
   * @return array{created: int, skipped: int, requirements: int, yields: int}
   *   What was written; `skipped` counts actions the apiary already had.
   *
   * @throws \InvalidArgumentException
   *   For an unknown source.
   */
  public function apply(Apiary $target, string $source, ?AccountInterface $account = NULL): array {
    $result = ['created' => 0, 'skipped' => 0, 'requirements' => 0, 'yields' => 0];
    $existing = [];
    foreach ($this->actionsOf($target) as $action) {
      $existing[$this->identity($action->get('title')->value, $action->get('scope')->value, $action->get('week_start')->value)] = $action;
    }

    // Actions first, their requirements and yields afterwards: a recipe row
    // that cannot be written must not cost the apiary the rest of its
    // calendar.
    $recipes = [];
    foreach ($this->definitions($source, $account ?? $this->currentUser) as $definition) {
      $key = $this->identity($definition['title'], $definition['scope'], $definition['week_start']);
      if (isset($existing[$key])) {
        $result['skipped']++;
      }
      else {
        $values = ['apiary' => $target->id()];
        foreach (self::ACTION_FIELDS as $field) {
          if (isset($definition[$field])) {
            $values[$field] = $definition[$field];
          }
        }
        $existing[$key] = $this->entityTypeManager->getStorage('calendar_action')->create($values + [
          'recurring' => TRUE,
          'enabled' => TRUE,
        ]);
        $existing[$key]->save();
        $result['created']++;
      }
      if (!empty($definition['requirements']) || !empty($definition['yields'])) {
        $recipes[] = [$existing[$key], $definition];
      }
    }

    // An action the apiary already had is not altered, but it does get a
    // requirement or yield it lacks (task 0270): that is how an apiary made
    // before the starter calendar had yields is given them. A row it already
    // has, whatever its quantity, is left alone, so a second run adds
    // nothing.
    foreach ($recipes as [$action, $definition]) {
      $result['requirements'] += $this->addMissingRecipeRows($action, 'calendar_action_item_requirement', 'item', 'inventory_item', $definition['requirements'] ?? [], $target);
      $result['yields'] += $this->addMissingRecipeRows($action, 'calendar_action_product_yield', 'product', 'product', $definition['yields'] ?? [], $target);
    }
    return $result;
  }

  /**
   * Gives an action the requirement or yield rows it does not have yet.
   *
   * @param \Drupal\hivelog\Entity\CalendarAction $action
   *   The action in the target apiary.
   * @param string $row_type
   *   The row entity type: `calendar_action_item_requirement` or
   *   `calendar_action_product_yield`.
   * @param string $field
   *   The row's catalogue reference, `item` or `product`; also the key of
   *   the catalogue values in each definition row.
   * @param string $catalog_type
   *   The catalogue entity type, `inventory_item` or `product`.
   * @param array<int, array<string, mixed>> $rows
   *   The definition rows: catalogue values under `$field`, and `quantity`.
   * @param \Drupal\hivelog\Entity\Apiary $target
   *   The apiary whose catalogue is used.
   *
   * @return int
   *   How many rows were created.
   */
  protected function addMissingRecipeRows(CalendarAction $action, string $row_type, string $field, string $catalog_type, array $rows, Apiary $target): int {
    if (!$rows) {
      return 0;
    }
    $present = [];
    foreach ($this->childrenOf($row_type, $action) as $row) {
      $present[(int) $row->get($field)->target_id] = TRUE;
    }
    $created = 0;
    foreach ($rows as $row) {
      $entry = $this->matchingCatalogEntry($catalog_type, $row[$field], $target);
      if (isset($present[(int) $entry->id()])) {
        continue;
      }
      $this->entityTypeManager->getStorage($row_type)->create([
        'calendar_action' => $action->id(),
        $field => $entry->id(),
        'quantity' => $row['quantity'],
      ])->save();
      $present[(int) $entry->id()] = TRUE;
      $created++;
    }
    return $created;
  }

  /**
   * Turns a source id into definitions.
   *
   * New kinds of source (a regional preset: `preset:<key>`) are added here.
   *
   * @return array<int, array<string, mixed>>
   *   Each: the ACTION_FIELDS, plus optional `requirements` (`item` =>
   *   catalog values, `quantity`) and `yields` (`product`, `quantity`).
   */
  protected function definitions(string $source, AccountInterface $account): array {
    if ($source === self::SOURCE_DEFAULT) {
      return array_map(
        fn(array $definition) => $definition + ['scope' => 'hive'],
        CalendarAction::DEFAULT_STARTER_CALENDAR,
      );
    }
    if (str_starts_with($source, self::SOURCE_APIARY_PREFIX)) {
      $apiary = $this->entityTypeManager->getStorage('apiary')->load(substr($source, strlen(self::SOURCE_APIARY_PREFIX)));
      if ($apiary instanceof Apiary && $apiary->access('view', $account)) {
        return array_map([$this, 'describe'], $this->viewableActions($apiary, $account));
      }
    }
    throw new \InvalidArgumentException(sprintf('Unknown or unavailable calendar source "%s".', $source));
  }

  /**
   * Describes a calendar action as a definition.
   */
  protected function describe(CalendarAction $action): array {
    $definition = [];
    foreach (self::ACTION_FIELDS as $field) {
      $value = $action->get($field)->value;
      if ($value !== NULL && $value !== '') {
        $definition[$field] = $value;
      }
    }
    $definition['scope'] ??= 'hive';

    $definition['requirements'] = [];
    foreach ($this->childrenOf('calendar_action_item_requirement', $action) as $row) {
      if ($item = $row->get('item')->entity) {
        $definition['requirements'][] = [
          'item' => $this->catalogValues($item, static::ITEM_FIELDS),
          'quantity' => $row->get('quantity')->value,
        ];
      }
    }
    $definition['yields'] = [];
    foreach ($this->childrenOf('calendar_action_product_yield', $action) as $row) {
      if ($product = $row->get('product')->entity) {
        $definition['yields'][] = [
          'product' => $this->catalogValues($product, static::PRODUCT_FIELDS),
          'quantity' => $row->get('quantity')->value,
        ];
      }
    }
    return $definition;
  }

  /**
   * An apiary's calendar actions the account may view, in week order.
   *
   * @return \Drupal\hivelog\Entity\CalendarAction[]
   *   The actions.
   */
  protected function viewableActions(Apiary $apiary, AccountInterface $account): array {
    return array_values(array_filter(
      $this->actionsOf($apiary),
      fn(CalendarAction $action) => $action->access('view', $account),
    ));
  }

  /**
   * All of an apiary's calendar actions, in week order.
   *
   * @return \Drupal\hivelog\Entity\CalendarAction[]
   *   The actions.
   */
  protected function actionsOf(Apiary $apiary): array {
    $storage = $this->entityTypeManager->getStorage('calendar_action');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('apiary', $apiary->id())
      ->sort('week_start')
      ->sort('id')
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

  /**
   * Loads the rows of a child type that hang off an action.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The rows.
   */
  protected function childrenOf(string $type, CalendarAction $action): array {
    return array_values($this->entityTypeManager->getStorage($type)
      ->loadByProperties(['calendar_action' => $action->id()]));
  }

  /**
   * Reads the values of a catalog entry that a copy should carry.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The catalog entry.
   * @param string[] $fields
   *   Field names.
   *
   * @return array<string, mixed>
   *   The non-empty values.
   */
  protected function catalogValues(ContentEntityInterface $entity, array $fields): array {
    $values = [];
    foreach ($fields as $field) {
      if ($entity->hasField($field) && !$entity->get($field)->isEmpty()) {
        $values[$field] = $entity->get($field)->value;
      }
    }
    return $values;
  }

  /**
   * Finds the target's catalog entry for a source entry, or creates it.
   *
   * Matched on name and unit (and item type for an inventory item), as the
   * hive move does. A created entry is catalogue data only: no purchases, so
   * no stock and no cost.
   *
   * @param string $type
   *   The catalog entity type, `inventory_item` or `product`.
   * @param array<string, mixed> $values
   *   The source entry's values.
   * @param \Drupal\hivelog\Entity\Apiary $target
   *   The apiary whose catalogue is searched.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The existing or new entry.
   */
  protected function matchingCatalogEntry(string $type, array $values, Apiary $target) {
    $storage = $this->entityTypeManager->getStorage($type);
    $match = ['apiary' => $target->id()];
    foreach (['name', 'unit', 'item_type'] as $field) {
      if (isset($values[$field])) {
        $match[$field] = $values[$field];
      }
    }
    if ($found = $storage->loadByProperties($match)) {
      return reset($found);
    }
    $entry = $storage->create($values + ['apiary' => $target->id()]);
    $entry->save();
    return $entry;
  }

  /**
   * The key two actions share when they are the same duty.
   */
  protected function identity(?string $title, ?string $scope, $week_start): string {
    return mb_strtolower(trim((string) $title)) . '|' . ($scope ?: 'hive') . '|' . (int) $week_start;
  }

}
