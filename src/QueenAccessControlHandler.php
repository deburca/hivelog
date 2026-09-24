<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Queen entities.
 *
 * Access is scoped to the parent apiary (via queen → hive → apiary).
 * Queens without a hive fall back to uid-based ownership check.
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member.
 * - delete: site-wide "any" OR apiary owner only, AND (task 0141) no
 *   BLOCK-treatment child still referencing the queen — not exempted by
 *   `administer hivelog` (see HivelogDeleteBlockingAccessTrait).
 * - delete_route: the same ownership check as `delete`, without the
 *   BLOCK check — see ApiaryAccessControlHandler's docblock for why.
 */
class QueenAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  use ApiaryAccessTrait;
  use HivelogDeleteBlockingAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $is_admin = $account->hasPermission('administer hivelog');
    $apiary = $this->resolveApiary($entity);

    switch ($operation) {
      case 'view':
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryViewAccess($apiary, $account, 'view any queen', 'view own queen');

      case 'update':
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryEditAccess($apiary, $account, 'edit any queen', 'edit own queen');

      case 'delete':
        $access = $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any queen', 'delete own queen');
        return $this->blockDelete($access, $entity);

      case 'delete_route':
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any queen', 'delete own queen');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add queen',
    ], 'OR');
  }

}
