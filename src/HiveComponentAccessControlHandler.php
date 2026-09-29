<?php

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Hive Component entities.
 *
 * Mirrors CalendarActionItemRequirementAccessControlHandler's shape
 * exactly (ADR-0106 §2) — access is scoped to the parent apiary (via the
 * component's hive):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner only (mirrors
 *   CalendarActionItemRequirement — a component is part of the hive's
 *   own build, not a per-transaction log).
 */
class HiveComponentAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any hive component', 'view own hive component');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any hive component', 'edit own hive component');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any hive component', 'delete own hive component');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add hive component',
    ], 'OR');
  }

}
