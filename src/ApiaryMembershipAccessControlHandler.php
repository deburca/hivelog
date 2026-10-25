<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\Entity\ApiaryMembership;

/**
 * Access control handler for ApiaryMembership entities.
 *
 * Only an owner of the apiary (the `owner` role) can manage memberships (add,
 * edit, remove members); beekeepers and novices cannot.
 * This is not a public-facing entity — it's managed through the inline member
 * list on the Apiary edit form.
 */
class ApiaryMembershipAccessControlHandler extends EntityAccessControlHandler {

  use ApiaryAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // An apiary always keeps an owner: the last owner cannot be removed, by
    // anyone, an administrator included (add another owner first). Deleting
    // the apiary itself removes its memberships directly, not through here.
    if ($operation === 'delete' && $entity instanceof ApiaryMembership && $entity->isLastOwner()) {
      return AccessResult::forbidden('The last owner of an apiary cannot be removed.')
        ->addCacheTags(['apiary_membership_list']);
    }

    if ($account->hasPermission('administer hivelog')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $apiary = $this->resolveApiary($entity);

    switch ($operation) {
      case 'view':
        // Memberships are only visible to the apiary owner (via the edit form).
        return $this->checkApiaryOwnerAccess(
          $apiary,
          $account,
          'administer hivelog',
          'edit own apiary'
        );

      case 'update':
        // Only the apiary owner can add/change/remove members.
        return $this->checkApiaryOwnerAccess(
          $apiary,
          $account,
          'administer hivelog',
          'edit own apiary'
        );

      case 'delete':
        // Only the apiary owner can remove a member.
        return $this->checkApiaryOwnerAccess(
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
