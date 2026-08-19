<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Calendar Action Item Requirement entities.
 *
 * Access is scoped to the parent apiary (via the requirement's calendar
 * action):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner only (mirrors CalendarAction —
 *   a requirement is part of the calendar action's plan, not a
 *   per-transaction log).
 */
class CalendarActionItemRequirementAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any calendar action item requirement', 'view own calendar action item requirement');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any calendar action item requirement', 'edit own calendar action item requirement');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any calendar action item requirement', 'delete own calendar action item requirement');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add calendar action item requirement',
    ], 'OR');
  }

}
