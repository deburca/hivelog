<?php

declare(strict_types=1);

namespace Drupal\collective;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Access control handler for Insight Agent entities.
 *
 * Deliberately **not** resolved through `ApiaryAccessTrait` — an
 * `InsightAgent` has no apiary/hive to resolve to (per
 * docs/project-management/decisions/0088-ai-insights-implementation.md
 * §1, it's a site-level credential, normally exactly one row). Access is
 * a plain ownership check instead: "own" means the current user is the
 * entity's owner, mirroring every other hivelog entity's own/any
 * permission pattern but without any apiary-membership dimension.
 *
 * - view/update/delete: site-wide "any" OR ("own" permission AND the
 *   current user is the owner).
 * - create: `administer hivelog` only — no dedicated "add" permission
 *   exists at all; provisioning this rare, high-trust credential is an
 *   administrator action, not something every beekeeper self-serves.
 */
class InsightAgentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $permission_map = [
      'view' => ['any' => 'view any insight agent', 'own' => 'view own insight agent'],
      'update' => ['any' => 'edit any insight agent', 'own' => 'edit own insight agent'],
      'delete' => ['any' => 'delete any insight agent', 'own' => 'delete own insight agent'],
    ];
    if (!isset($permission_map[$operation])) {
      return AccessResult::neutral();
    }

    if ($account->hasPermission($permission_map[$operation]['any'])) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = $entity instanceof EntityOwnerInterface
      && (int) $entity->getOwnerId() === (int) $account->id();
    if ($is_owner && $account->hasPermission($permission_map[$operation]['own'])) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer hivelog');
  }

}
