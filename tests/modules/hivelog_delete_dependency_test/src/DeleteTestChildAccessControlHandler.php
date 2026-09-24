<?php

declare(strict_types=1);

namespace Drupal\hivelog_delete_dependency_test;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * A minimal `delete own` / `delete any` handler for `DeleteTestChild`.
 *
 * Just enough to give `HivelogDeleteDependencyCounter`'s BLOCK-row
 * "deletable" split something real to compute against.
 */
class DeleteTestChildAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ($operation !== 'delete') {
      return AccessResult::neutral();
    }
    if ($account->hasPermission('delete any hivelog_delete_test_child')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if ($entity instanceof EntityOwnerInterface
      && $account->hasPermission('delete own hivelog_delete_test_child')
      && $entity->getOwnerId() === $account->id()) {
      return AccessResult::allowed()->cachePerPermissions()->cachePerUser();
    }
    return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
  }

}
