<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Inventory Usage entities.
 *
 * Access is scoped to the parent apiary (via the usage's hive/apiary
 * action log):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner OR the record's own creator
 *   (mirrors HiveActionLog — a usage record is a per-transaction log
 *   line, not foundational apiary structure).
 */
class InventoryUsageAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any inventory usage', 'view own inventory usage');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any inventory usage', 'edit own inventory usage');

      case 'delete':
        return $this->checkApiaryMemberDeleteAccess($apiary, $entity, $account, 'delete any inventory usage', 'delete own inventory usage');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add inventory usage',
    ], 'OR');
  }

}
