<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Harvest Yield entities.
 *
 * Access is scoped to the parent apiary (via the yield's hive/apiary
 * action log):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner OR the record's own creator
 *   (mirrors InventoryUsage/HiveActionLog — a yield record is a
 *   per-transaction log line, not foundational apiary structure).
 */
class HarvestYieldAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any harvest yield', 'view own harvest yield');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any harvest yield', 'edit own harvest yield');

      case 'delete':
        return $this->checkApiaryMemberDeleteAccess($apiary, $entity, $account, 'delete any harvest yield', 'delete own harvest yield');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add harvest yield',
    ], 'OR');
  }

}
