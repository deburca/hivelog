<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for ApiaryMembership entities.
 *
 * Only the apiary owner can manage memberships (add, edit, remove members).
 * This is not a public-facing entity — it's managed through the inline member
 * list on the Apiary edit form.
 */
class ApiaryMembershipAccessControlHandler extends EntityAccessControlHandler {

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
        // Memberships are only visible to the apiary owner (via the edit form).
        return $this->checkApiaryOwnerDeleteAccess(
          $apiary,
          $account,
          'administer hivelog',
          'edit own apiary'
        );

      case 'update':
        // Only the apiary owner can add/change/remove members.
        return $this->checkApiaryOwnerDeleteAccess(
          $apiary,
          $account,
          'administer hivelog',
          'edit own apiary'
        );

      case 'delete':
        // Only the apiary owner can remove a member.
        return $this->checkApiaryOwnerDeleteAccess(
          $apiary,
          $account,
          'administer hivelog',
          'edit own apiary'
        );
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'edit own apiary',
    ], 'OR');
  }

}
