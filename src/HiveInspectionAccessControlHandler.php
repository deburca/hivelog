<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Hive Inspection entities.
 *
 * Access is scoped to the parent apiary (via hive → apiary):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member.
 * - delete: site-wide "any" OR apiary owner OR beekeeper who created it.
 */
class HiveInspectionAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any hive inspection', 'view own hive inspection');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any hive inspection', 'edit own hive inspection');

      case 'delete':
        return $this->checkApiaryMemberDeleteAccess($apiary, $entity, $account, 'delete any hive inspection', 'delete own hive inspection');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add hive inspection',
    ], 'OR');
  }

}
