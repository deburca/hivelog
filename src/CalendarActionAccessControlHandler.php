<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Calendar Action entities.
 *
 * Access is scoped to the parent apiary:
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner only (mirrors Hive — a
 *   calendar action is foundational apiary structure, not a per-visit
 *   log), AND (task 0141) no BLOCK-treatment child still referencing
 *   the calendar action — not exempted by `administer hivelog` (see
 *   HivelogDeleteBlockingAccessTrait).
 * - delete_route: the same ownership check as `delete`, without the
 *   BLOCK check — see ApiaryAccessControlHandler's docblock for why.
 */
class CalendarActionAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

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
          : $this->checkApiaryViewAccess($apiary, $account, 'view any calendar action', 'view own calendar action');

      case 'update':
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryEditAccess($apiary, $account, 'edit any calendar action', 'edit own calendar action');

      case 'delete':
        $access = $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any calendar action', 'delete own calendar action');
        return $this->blockDelete($access, $entity);

      case 'delete_route':
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any calendar action', 'delete own calendar action');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add calendar action',
    ], 'OR');
  }

}
