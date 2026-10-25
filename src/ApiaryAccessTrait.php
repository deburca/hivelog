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
   * - InventoryUsage: inventory_usage → hive_action_log → hive → apiary,
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

    // InventoryUsage → hive_action_log → hive → apiary, or →
    // apiary_action_log → apiary directly.
    if ($entity_type === 'inventory_usage') {
      // @phpstan-ignore-next-line
      $hive_action_log = $entity->get('hive_action_log')->entity;
      if ($hive_action_log) {
        $hive = $hive_action_log->get('hive')->entity;
        return $hive ? $hive->get('apiary')->entity : NULL;
      }
      // @phpstan-ignore-next-line
      $apiary_action_log = $entity->get('apiary_action_log')->entity;
      return $apiary_action_log ? $apiary_action_log->get('apiary')->entity : NULL;
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

    // HarvestYield → hive_action_log → hive → apiary, or →
    // apiary_action_log → apiary directly.
    if ($entity_type === 'harvest_yield') {
      // @phpstan-ignore-next-line
      $hive_action_log = $entity->get('hive_action_log')->entity;
      if ($hive_action_log) {
        $hive = $hive_action_log->get('hive')->entity;
        return $hive ? $hive->get('apiary')->entity : NULL;
      }
      // @phpstan-ignore-next-line
      $apiary_action_log = $entity->get('apiary_action_log')->entity;
      return $apiary_action_log ? $apiary_action_log->get('apiary')->entity : NULL;
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
