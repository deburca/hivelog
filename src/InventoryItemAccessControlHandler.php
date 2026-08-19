<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Inventory Item entities.
 *
 * Access is scoped to the parent apiary:
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner only (mirrors Hive/
 *   CalendarAction — a catalog entry is foundational apiary structure,
 *   not a per-transaction log).
 */
class InventoryItemAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any inventory item', 'view own inventory item');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any inventory item', 'edit own inventory item');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any inventory item', 'delete own inventory item');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add inventory item',
    ], 'OR');
  }

}
