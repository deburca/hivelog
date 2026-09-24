<?php

declare(strict_types=1);

namespace Drupal\hivelog\Delete;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * The hivelog delete-dependency registry (task 0134).
 *
 * One declarative row per parent → child relationship named in
 * ADR-0103's inventory table, each with its `treatment` (BLOCK, WARN,
 * CASCADE or DETACH — the `*_TREATMENT` constants below) and, where one
 * exists, where the current user can go manage those children before
 * or instead of deleting the parent.
 *
 * This is a companion to `HivelogEntityHierarchy` (task 0116/0127), not
 * a replacement for it: that class tracks exactly one parent field per
 * entity type, for the breadcrumb trail and cancel/redirect targets.
 * This registry is deliberately richer — a single child type can have
 * more than one row (`hive_action_log` has three: BLOCK on `hive`,
 * BLOCK on `calendar_action`, and a separate DETACH on `inspection`),
 * and every row carries a treatment and a management link `resolveParent()`
 * has no use for.
 *
 * Submodule rows (ADR-0103 #7, #8, #12, #13, #27, #28) are not declared
 * here — `hivelog` doesn't depend on `nanoprobe`/`nexus`. They're
 * registered by those modules implementing
 * `hook_hivelog_delete_dependencies()` (see `hivelog.api.php`), and
 * `rows()` merges them in.
 */
final class HivelogDeleteDependencyRegistry {

  /**
   * Delete is refused until the children are gone.
   */
  public const BLOCK = 'block';

  /**
   * Delete stays allowed; the confirmation page warns with counts/links.
   */
  public const WARN = 'warn';

  /**
   * The children are deleted together with the parent.
   */
  public const CASCADE = 'cascade';

  /**
   * The children are kept; their reference to the parent is cleared.
   */
  public const DETACH = 'detach';

  /**
   * One row per parent → child relationship this module itself declares.
   *
   * Keys: `adr_row` (ADR-0103's inventory row number, for cross-
   * reference — `19a`/`19b`/`20a`/`20b` split the ADR's single #19/#20
   * rows, which name "Hive/ApiaryActionLog" as one combined parent,
   * into the two real reference fields involved), `parent` (entity
   * type ID), `child` (entity type ID), `field` (the child's reference
   * field naming the parent), `treatment`, and `manage` — either NULL
   * (no sensible single link; the count is shown alone), the literal
   * string `'parent-canonical'` (the parent entity's own canonical
   * page, which is where the established pattern embeds a management
   * table of exactly this child type — Apiary's hives/items/products,
   * Hive's inspections/action-logs, Queen's observations), or a route
   * name with no parameters (a global collection).
   */
  protected const ROWS = [
    [
      'adr_row' => '1',
      'parent' => 'apiary',
      'child' => 'hive',
      'field' => 'apiary',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '2',
      'parent' => 'apiary',
      'child' => 'calendar_action',
      'field' => 'apiary',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '3',
      'parent' => 'apiary',
      'child' => 'apiary_action_log',
      'field' => 'apiary',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '4',
      'parent' => 'apiary',
      'child' => 'inventory_item',
      'field' => 'apiary',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '5',
      'parent' => 'apiary',
      'child' => 'inventory_purchase',
      'field' => 'apiary',
      'treatment' => self::BLOCK,
      'manage' => 'entity.inventory_purchase.collection',
    ],
    [
      'adr_row' => '6',
      'parent' => 'apiary',
      'child' => 'product',
      'field' => 'apiary',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '9',
      'parent' => 'hive',
      'child' => 'hive_inspection',
      'field' => 'hive',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '10',
      'parent' => 'hive',
      'child' => 'hive_action_log',
      'field' => 'hive',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '11',
      'parent' => 'hive',
      'child' => 'queen',
      'field' => 'hive',
      'treatment' => self::DETACH,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '14',
      'parent' => 'queen',
      'child' => 'queen_observation',
      'field' => 'queen',
      'treatment' => self::BLOCK,
      'manage' => 'parent-canonical',
    ],
    [
      'adr_row' => '15',
      'parent' => 'calendar_action',
      'child' => 'calendar_action_item_requirement',
      'field' => 'calendar_action',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '16',
      'parent' => 'calendar_action',
      'child' => 'calendar_action_product_yield',
      'field' => 'calendar_action',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '17',
      'parent' => 'calendar_action',
      'child' => 'hive_action_log',
      'field' => 'calendar_action',
      'treatment' => self::BLOCK,
      'manage' => NULL,
    ],
    [
      'adr_row' => '18',
      'parent' => 'calendar_action',
      'child' => 'apiary_action_log',
      'field' => 'calendar_action',
      'treatment' => self::BLOCK,
      'manage' => NULL,
    ],
    [
      'adr_row' => '19a',
      'parent' => 'hive_action_log',
      'child' => 'inventory_usage',
      'field' => 'hive_action_log',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '19b',
      'parent' => 'apiary_action_log',
      'child' => 'inventory_usage',
      'field' => 'apiary_action_log',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '20a',
      'parent' => 'hive_action_log',
      'child' => 'harvest_yield',
      'field' => 'hive_action_log',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '20b',
      'parent' => 'apiary_action_log',
      'child' => 'harvest_yield',
      'field' => 'apiary_action_log',
      'treatment' => self::CASCADE,
      'manage' => NULL,
    ],
    [
      'adr_row' => '21',
      'parent' => 'hive_inspection',
      'child' => 'hive_action_log',
      'field' => 'inspection',
      'treatment' => self::DETACH,
      'manage' => NULL,
    ],
    [
      'adr_row' => '22',
      'parent' => 'inventory_item',
      'child' => 'inventory_purchase',
      'field' => 'item',
      'treatment' => self::WARN,
      'manage' => 'entity.inventory_purchase.collection',
    ],
    [
      'adr_row' => '23',
      'parent' => 'inventory_item',
      'child' => 'inventory_usage',
      'field' => 'item',
      'treatment' => self::WARN,
      'manage' => NULL,
    ],
    [
      'adr_row' => '24',
      'parent' => 'inventory_item',
      'child' => 'calendar_action_item_requirement',
      'field' => 'item',
      'treatment' => self::BLOCK,
      'manage' => NULL,
    ],
    [
      'adr_row' => '25',
      'parent' => 'product',
      'child' => 'calendar_action_product_yield',
      'field' => 'product',
      'treatment' => self::BLOCK,
      'manage' => NULL,
    ],
    [
      'adr_row' => '26',
      'parent' => 'product',
      'child' => 'harvest_yield',
      'field' => 'product',
      'treatment' => self::WARN,
      'manage' => NULL,
    ],
  ];

  /**
   * Every row this module and its submodules declare.
   *
   * @return array[]
   *   The rows, each keyed exactly as `ROWS`' own entries.
   */
  public static function rows(): array {
    $submodule_rows = \Drupal::moduleHandler()->invokeAll('hivelog_delete_dependencies');
    return array_merge(self::ROWS, $submodule_rows);
  }

  /**
   * The rows whose `parent` is `$parent_type`.
   *
   * @return array[]
   *   The matching rows, each keyed exactly as `ROWS`' own entries.
   */
  public static function rowsForParent(string $parent_type): array {
    return array_values(array_filter(
      self::rows(),
      static fn(array $row): bool => $row['parent'] === $parent_type,
    ));
  }

  /**
   * The URL a row's `manage` value resolves to for `$parent`, or NULL.
   */
  public static function manageUrl(array $row, EntityInterface $parent): ?Url {
    if (empty($row['manage'])) {
      return NULL;
    }
    if ($row['manage'] === 'parent-canonical') {
      return $parent->hasLinkTemplate('canonical') ? $parent->toUrl('canonical') : NULL;
    }
    return Url::fromRoute($row['manage']);
  }

}
