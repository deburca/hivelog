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
   * - InventoryUsage: inventory_usage → hive_action_log → hive → apiary,
   *   or → apiary_action_log → apiary directly (exactly one is set).
   * - Product: product → apiary directly.
   * - CalendarActionProductYield: calendar_action_product_yield →
   *   calendar_action → apiary.
   * - HarvestYield: harvest_yield → hive_action_log → hive → apiary, or
   *   → apiary_action_log → apiary directly (exactly one is set).
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

    // Apiary member with the "own" permission.
    if ($apiary->isApiaryMember($account) && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($apiary);
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
      ->addCacheableDependency($apiary);
  }

  /**
   * Checks edit access considering apiary membership.
   *
   * Grants access to the apiary owner and approved beekeepers.
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
    if ($account->hasPermission($any_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (!$apiary) {
      return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    }

    if ($apiary->isApiaryMember($account) && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($apiary);
    }

    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($apiary);
  }

  /**
   * Checks delete access — apiary owner only (plus site-wide "any").
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission (site-wide override).
   * @param string $own_permission
   *   The "own" permission (owner-scoped).
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkApiaryOwnerDeleteAccess(?Apiary $apiary, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    if ($account->hasPermission($any_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (!$apiary) {
      return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    }

    // Only the apiary owner can delete.
    $is_owner = (int) $apiary->getOwnerId() === (int) $account->id();
    if ($is_owner && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($apiary);
    }

    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($apiary);
  }

  /**
   * Checks delete access — apiary owner OR the entity creator.
   *
   * Used for inspections and observations where the beekeeper who created
   * the record may also delete it.
   *
   * @param \Drupal\hivelog\Entity\Apiary|null $apiary
   *   The parent apiary.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $any_permission
   *   The "any" permission.
   * @param string $own_permission
   *   The "own" permission.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkApiaryMemberDeleteAccess(?Apiary $apiary, EntityInterface $entity, AccountInterface $account, string $any_permission, string $own_permission): AccessResult {
    if ($account->hasPermission($any_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if (!$apiary) {
      return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
    }

    $is_apiary_owner = (int) $apiary->getOwnerId() === (int) $account->id();
    $is_entity_creator = method_exists($entity, 'getOwnerId')
      && (int) $entity->getOwnerId() === (int) $account->id();

    if (($is_apiary_owner || ($apiary->isApiaryMember($account) && $is_entity_creator))
      && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($apiary);
    }

    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($apiary);
  }

}
