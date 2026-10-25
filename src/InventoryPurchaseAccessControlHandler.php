<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\Entity\InventoryPurchase;

/**
 * Access control handler for Inventory Purchase entities.
 *
 * Access is scoped to the parent apiary:
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner OR the record's own creator
 *   (mirrors HiveInspection/QueenObservation — a purchase is a
 *   per-transaction log, not foundational apiary structure).
 */
class InventoryPurchaseAccessControlHandler extends EntityAccessControlHandler {

  use ApiaryAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // A purchase a hive move wrote (task 0231) is a transfer record: it stays
    // as written, for everyone, so the two apiaries' stock cannot drift apart.
    if (in_array($operation, ['update', 'delete'], TRUE) && $entity instanceof InventoryPurchase && $entity->get('transfer')->value) {
      return AccessResult::forbidden('A purchase recorded by a hive move cannot be changed or removed.')->addCacheableDependency($entity);
    }

    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $apiary = $this->resolveApiary($entity);

    switch ($operation) {
      case 'view':
        return $this->checkApiaryViewAccess($apiary, $account, 'view any inventory purchase', 'view own inventory purchase');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any inventory purchase', 'edit own inventory purchase');

      case 'delete':
        return $this->checkApiaryDeleteAccess($apiary, $account, 'delete any inventory purchase', 'delete own inventory purchase');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add inventory purchase',
    ], 'OR');
  }

}
