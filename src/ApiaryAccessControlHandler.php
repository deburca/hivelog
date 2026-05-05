<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Access control handler for Apiary entities.
 */
class ApiaryAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $is_owner = ($entity instanceof EntityOwnerInterface)
      && ((int) $entity->getOwnerId() === (int) $account->id());

    switch ($operation) {
      case 'view':
        return $this->checkOwnershipAccess($account, $is_owner, 'view any apiary', 'view own apiary');

      case 'update':
        return $this->checkOwnershipAccess($account, $is_owner, 'edit any apiary', 'edit own apiary');

      case 'delete':
        return $this->checkOwnershipAccess($account, $is_owner, 'delete any apiary', 'delete own apiary');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add apiary',
    ], 'OR');
  }

  /**
   * Checks access based on "any" and "own" permissions.
   */
  protected function checkOwnershipAccess(AccountInterface $account, bool $is_owner, string $any_permission, string $own_permission): AccessResult {
    if ($account->hasPermission($any_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ($is_owner && $account->hasPermission($own_permission)) {
      return AccessResult::allowed()->cachePerPermissions()->cachePerUser();
    }
    return AccessResult::neutral()->cachePerPermissions()->cachePerUser();
  }

}
