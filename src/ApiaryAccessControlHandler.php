<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Apiary entities.
 *
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary owner only.
 * - delete: site-wide "any" OR apiary owner only, AND (task 0141) no
 *   BLOCK-treatment child still referencing the apiary — an
 *   `administer hivelog` user is not exempt from this: the ADR treats
 *   BLOCK as a data-integrity rule, not a permission gate, and expects
 *   even an admin to delete the children first (see
 *   HivelogDeleteBlockingAccessTrait).
 * - delete_route: the same ownership check as `delete`, without the
 *   BLOCK check — used only by `entity.apiary.delete_form`'s route
 *   access, so a blocked delete reaches the form (which renders the
 *   "Can't delete yet" state) instead of a bare 403. See
 *   HivelogDeleteBlockingAccessTrait's docblock and
 *   docs/project-management/tasks/0141-delete-block-relationships.md.
 */
class ApiaryAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

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
          : $this->checkApiaryViewAccess($apiary, $account, 'view any apiary', 'view own apiary');

      case 'update':
        // Only apiary owner can edit the apiary itself.
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'edit any apiary', 'edit own apiary');

      case 'delete':
        $access = $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any apiary', 'delete own apiary');
        return $this->blockDelete($access, $entity);

      case 'delete_route':
        return $is_admin
          ? AccessResult::allowed()->cachePerPermissions()
          : $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any apiary', 'delete own apiary');
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
