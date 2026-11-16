<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for HiveReminder (task 0274).
 */
class HiveReminderAccessControlHandler extends EntityAccessControlHandler {

  use ApiaryAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $apiary = $this->resolveApiary($entity);

    switch ($operation) {
      case 'view':
        return $this->checkRecordViewAccess($entity, $account, 'view any hive reminder', 'view own hive reminder');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any hive reminder', 'edit own hive reminder');

      case 'delete':
        return $this->checkApiaryDeleteAccess($apiary, $account, 'delete any hive reminder', 'delete own hive reminder');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add hive reminder',
    ], 'OR');
  }

}
