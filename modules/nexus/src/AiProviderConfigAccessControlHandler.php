<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Access control handler for AI Provider Config entities.
 *
 * Deliberately **not** resolved through `ApiaryAccessTrait` — an
 * `AiProviderConfig` has no apiary/hive to resolve to (it's a site-level
 * credential reference, normally exactly one row). Access is a plain
 * ownership check instead, exactly mirroring
 * `\Drupal\collective\ApiClientAccessControlHandler` (including its
 * task 0135 update/delete narrowing — see that class's own docblock for
 * the full reasoning):
 *
 * - view: site-wide "any" OR ("own" permission AND the current user is
 *   the owner).
 * - update/delete: site-wide "any" only.
 * - create: `administer hivelog` only — no dedicated "add" permission
 *   exists at all; provisioning this rare, high-trust,
 *   billing-relevant credential reference is an administrator action,
 *   not something every beekeeper self-serves.
 */
class AiProviderConfigAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $permission_map = [
      'view' => ['any' => 'view any ai provider config', 'own' => 'view own ai provider config'],
      'update' => ['any' => 'edit any ai provider config'],
      'delete' => ['any' => 'delete any ai provider config'],
    ];
    if (!isset($permission_map[$operation])) {
      return AccessResult::neutral();
    }

    if ($account->hasPermission($permission_map[$operation]['any'])) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = $entity instanceof EntityOwnerInterface
      && (int) $entity->getOwnerId() === (int) $account->id();
    if ($is_owner && isset($permission_map[$operation]['own']) && $account->hasPermission($permission_map[$operation]['own'])) {
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
