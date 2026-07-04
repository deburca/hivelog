<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Hive Action Log entities.
 *
 * Access is scoped to the parent apiary (via hive_action_log → hive →
 * apiary):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member.
 * - delete: site-wide "any" OR apiary owner OR beekeeper who created it
 *   (mirrors HiveInspection — a log is a per-visit record).
 */
class HiveActionLogAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any hive action log', 'view own hive action log');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any hive action log', 'edit own hive action log');

      case 'delete':
        return $this->checkApiaryMemberDeleteAccess($apiary, $entity, $account, 'delete any hive action log', 'delete own hive action log');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add hive action log',
    ], 'OR');
  }

}
