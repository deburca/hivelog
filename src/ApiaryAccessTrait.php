<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\Entity\Apiary;

/**
 * Shared helpers for apiary-scoped access control.
 *
 * Used by all hivelog entity access control handlers to resolve the parent
 * apiary from a child entity and check membership / visibility.
 */
trait ApiaryAccessTrait {

  /**
   * Resolves the parent Apiary from a child entity.
   *
   * Traverses the entity reference chain:
   * - Apiary: returns itself.
   * - ApiaryMembership: apiary_membership → apiary directly.
   * - Hive: hive → apiary.
   * - HiveInspection: inspection → hive → apiary.
   * - Queen: queen → hive → apiary (hive may be NULL).
   * - QueenObservation: observation → queen → hive → apiary.
   * - CalendarAction: calendar_action → apiary directly.
   * - HiveActionLog: hive_action_log → hive → apiary.
   * - ApiaryActionLog: apiary_action_log → apiary directly.
   * - InventoryItem: inventory_item → apiary directly.
   * - InventoryPurchase: inventory_purchase → apiary directly.
   * - CalendarActionItemRequirement: calendar_action_item_requirement →
   *   calendar_action → apiary.
   * - HiveComponent: hive_component → hive → apiary.
   * - InventoryUsage: inventory_usage → item → apiary (the item's apiary,
   *   which a hive move leaves alone),
   *   or → apiary_action_log → apiary directly (exactly one is set).
   * - Product: product → apiary directly.
   * - CalendarActionProductYield: calendar_action_product_yield →
   *   calendar_action → apiary.
   * - HarvestYield: harvest_yield → hive_action_log → hive → apiary, or
   *   → apiary_action_log → apiary directly (exactly one is set).
   * - SensorDevice: sensor_device → apiary directly (scope = apiary), or
   *   → hive → apiary (scope = hive).
   * - SensorReading: sensor_reading → sensor_device → (recursively
   *   resolved as above).
   * - SensorReadingDaily: sensor_reading_daily → sensor_device →
   *   (recursively resolved as above).
   * - HiveInsight: hive_insight → apiary directly (scope = apiary), or
   *   → hive → apiary (scope = hive). No branch exists for ApiClient or
   *   AiProviderConfig — neither has an apiary/hive to resolve to.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to resolve the apiary from.
   *
   * @return \Drupal\hivelog\Entity\Apiary|null
   *   The parent apiary, or NULL if the chain is broken.
   */
  protected function resolveApiary(EntityInterface $entity): ?Apiary {
    // A history record carries the apiary it was written under (task 0231),
    // which wins over the apiary its hive is in now: moving a hive does not
    // rewrite history, and writing to a record stays with that apiary.
    $stamped = $this->stampedApiary($entity);
    return $stamped ?? $this->resolveCurrentApiary($entity);
  }

  /**
   * The apiary a history record was written under, if it carries one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\hivelog\Entity\Apiary|null
   *   The stamped apiary, or NULL for a type that is not stamped or a record
   *   with no stamp.
   */
  protected function stampedApiary(EntityInterface $entity): ?Apiary {
    $stamped_types = ['hive_inspection', 'queen_observation', 'hive_action_log', 'hive_insight'];
    if (!in_array($entity->getEntityTypeId(), $stamped_types, TRUE)) {
      return NULL;
    }
    // @phpstan-ignore-next-line
    $apiary = $entity->hasField('apiary') ? $entity->get('apiary')->entity : NULL;
    return $apiary instanceof Apiary ? $apiary : NULL;
  }

  /**
   * Resolves the apiary a record belongs to NOW, through its hive.
   *
   * For a record that was written under another apiary before its hive moved,
   * this is the hive's apiary today, whose members may read the record.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to resolve the apiary from.
   *
   * @return \Drupal\hivelog\Entity\Apiary|null
   *   The apiary, or NULL if the chain is broken.
   */
  protected function resolveCurrentApiary(EntityInterface $entity): ?Apiary {
    if ($entity instanceof Apiary) {
      return $entity;
    }

    $entity_type = $entity->getEntityTypeId();

    // ApiaryMembership → apiary directly.
    if ($entity_type === 'apiary_membership') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // Hive → apiary.
    // @phpstan-ignore-next-line
    if ($entity_type === 'hive') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // HiveInspection → hive → apiary.
    if ($entity_type === 'hive_inspection') {
      // @phpstan-ignore-next-line
      $hive = $entity->get('hive')->entity;
      // @phpstan-ignore-next-line
      return $hive ? $hive->get('apiary')->entity : NULL;
    }

    // Queen → hive → apiary (hive is optional on queens).
    if ($entity_type === 'queen') {
      // @phpstan-ignore-next-line
      $hive = $entity->get('hive')->entity;
      // @phpstan-ignore-next-line
      return $hive ? $hive->get('apiary')->entity : NULL;
    }

    // QueenObservation → queen → hive → apiary.
    if ($entity_type === 'queen_observation') {
      // @phpstan-ignore-next-line
      $queen = $entity->get('queen')->entity;
      if ($queen) {
        // @phpstan-ignore-next-line
        $hive = $queen->get('hive')->entity;
        // @phpstan-ignore-next-line
        return $hive ? $hive->get('apiary')->entity : NULL;
      }
      return NULL;
    }

    // CalendarAction → apiary directly.
    if ($entity_type === 'calendar_action') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // HiveActionLog → hive → apiary.
    if ($entity_type === 'hive_action_log') {
      // @phpstan-ignore-next-line
      $hive = $entity->get('hive')->entity;
      // @phpstan-ignore-next-line
      return $hive ? $hive->get('apiary')->entity : NULL;
    }

    // ApiaryActionLog → apiary directly.
    if ($entity_type === 'apiary_action_log') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // InventoryItem → apiary directly.
    if ($entity_type === 'inventory_item') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // InventoryPurchase → apiary directly.
    if ($entity_type === 'inventory_purchase') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // CalendarActionItemRequirement → calendar_action → apiary.
    if ($entity_type === 'calendar_action_item_requirement') {
      // @phpstan-ignore-next-line
      $calendar_action = $entity->get('calendar_action')->entity;
      // @phpstan-ignore-next-line
      return $calendar_action ? $calendar_action->get('apiary')->entity : NULL;
    }

    // HiveComponent → hive → apiary.
    if ($entity_type === 'hive_component') {
      // @phpstan-ignore-next-line
      $hive = $entity->get('hive')->entity;
      // @phpstan-ignore-next-line
      return $hive ? $hive->get('apiary')->entity : NULL;
    }

    // InventoryUsage → its item → apiary. The item's apiary never changes,
    // unlike the hive of the log that caused the usage (task 0231), so usage
    // stays with the apiary whose stock it used.
    if ($entity_type === 'inventory_usage') {
      // @phpstan-ignore-next-line
      $item = $entity->get('item')->entity;
      return $item ? $item->get('apiary')->entity : NULL;
    }

    // Product → apiary directly.
    if ($entity_type === 'product') {
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // CalendarActionProductYield → calendar_action → apiary.
    if ($entity_type === 'calendar_action_product_yield') {
      // @phpstan-ignore-next-line
      $calendar_action = $entity->get('calendar_action')->entity;
      // @phpstan-ignore-next-line
      return $calendar_action ? $calendar_action->get('apiary')->entity : NULL;
    }

    // HarvestYield → its product → apiary (the product's apiary never changes
    // when a hive moves, task 0231).
    if ($entity_type === 'harvest_yield') {
      // @phpstan-ignore-next-line
      $product = $entity->get('product')->entity;
      return $product ? $product->get('apiary')->entity : NULL;
    }

    // SensorDevice → apiary directly (scope = apiary), or → hive →
    // apiary (scope = hive).
    if ($entity_type === 'sensor_device') {
      // @phpstan-ignore-next-line
      $hive = $entity->get('hive')->entity;
      if ($hive) {
        return $hive->get('apiary')->entity;
      }
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    // SensorReading → sensor_device → (recursively resolved).
    if ($entity_type === 'sensor_reading') {
      // @phpstan-ignore-next-line
      $sensor_device = $entity->get('sensor_device')->entity;
      return $sensor_device ? $this->resolveApiary($sensor_device) : NULL;
    }

    // SensorReadingDaily → sensor_device → (recursively resolved).
    if ($entity_type === 'sensor_reading_daily') {
      // @phpstan-ignore-next-line
      $sensor_device = $entity->get('sensor_device')->entity;
      return $sensor_device ? $this->resolveApiary($sensor_device) : NULL;
    }

    // HiveInsight → apiary directly (scope = apiary), or → hive →
    // apiary (scope = hive).
    if ($entity_type === 'hive_insight') {
      // @phpstan-ignore-next-line
      $hive = $entity->get('hive')->entity;
      if ($hive) {
        return $hive->get('apiary')->entity;
      }
      // @phpstan-ignore-next-line
      return $entity->get('apiary')->entity;
    }

    return NULL;
  }

  /**
   * Checks view access considering apiary membership and visibility.
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary (NULL if the chain is broken).
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission (apiary-member scoped).
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkApiaryViewAccess(?Apiary $apiary, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    // Site-wide "any" permission always grants access.
    if ($account->hasPermission($any_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (!$apiary) {
      return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    }

    // Apiary member (any role) with the "own" permission.
    if ($apiary->isApiaryMember($account) && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($apiary)
        ->addCacheTags(['apiary_membership_list']);
    }

    // Public apiary: any user with the "own" permission can view.
    if ($apiary->isPublic() && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($apiary);
    }

    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($apiary)
      ->addCacheTags(['apiary_membership_list']);
  }

  /**
   * Checks view access to a history record, as its writer or its hive's apiary.
   *
   * A record keeps the apiary it was written under (task 0231), so after its
   * hive moves, members of the old apiary still read what was written under
   * theirs, and members of the hive's apiary now read the hive's whole history.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The record.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkRecordViewAccess(EntityInterface $entity, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    $written_under = $this->resolveApiary($entity);
    $access = $this->checkApiaryViewAccess($written_under, $account, $any_permission, $own_permission);
    $current = $this->resolveCurrentApiary($entity);
    if ($current && (!$written_under || (int) $current->id() !== (int) $written_under->id())) {
      $access = $access->orIf($this->checkApiaryViewAccess($current, $account, $any_permission, $own_permission));
    }
    return $access;
  }

  /**
   * Checks edit access: a beekeeper or an owner of the apiary.
   *
   * The apiary role decides what a user may do in this apiary, and the site
   * permission must also be present, so an administrator can still switch an
   * area off site-wide (task 0224). A novice reads only.
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission (apiary-member scoped).
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkApiaryEditAccess(?Apiary $apiary, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    return $this->checkApiaryRoleAccess($apiary, $account, $any_permission, $own_permission, 'keeper');
  }

  /**
   * Checks delete access: a beekeeper or an owner of the apiary.
   *
   * Replaces the old owner-only and creator-based delete rules (task 0224):
   * what a user may delete depends on their current role, not on having
   * created the record.
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkApiaryDeleteAccess(?Apiary $apiary, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    return $this->checkApiaryRoleAccess($apiary, $account, $any_permission, $own_permission, 'keeper');
  }

  /**
   * Checks access that only an owner of the apiary has.
   *
   * Managing members and owners, editing the apiary's own settings and
   * deleting the apiary (plus the site-wide "any" permission).
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkApiaryOwnerAccess(?Apiary $apiary, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    return $this->checkApiaryRoleAccess($apiary, $account, $any_permission, $own_permission, 'owner');
  }

  /**
   * Allows the account if it holds at least a role and the site permission.
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission.
   * @param string $minimum_role
   *   The lowest role allowed: `keeper` or `owner`.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Allowed, or neutral, cacheable per permissions, user, the apiary and
   *   the membership list (so a role change is never served stale).
   */
  protected function checkApiaryRoleAccess(?Apiary $apiary, AccountInterface $account, string $any_permission, string $own_permission, string $minimum_role): AccessResult {
    if ($account->hasPermission($any_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (!$apiary) {
      return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    }

    $allowed = $apiary->hasRoleAtLeast($account, $minimum_role)
      && $account->hasPermission($own_permission);

    return ($allowed ? AccessResult::allowed() : AccessResult::neutral())
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($apiary)
      ->addCacheTags(['apiary_membership_list']);
  }

}
