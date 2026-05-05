<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Apiary entities.
 *
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary owner only.
 * - delete: site-wide "any" OR apiary owner only.
 */
class ApiaryAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any apiary', 'view own apiary');

      case 'update':
        // Only apiary owner can edit the apiary itself.
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'edit any apiary', 'edit own apiary');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any apiary', 'delete own apiary');
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

}
