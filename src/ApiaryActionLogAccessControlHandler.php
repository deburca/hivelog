<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Apiary Action Log entities.
 *
 * Access is scoped to the parent apiary (via apiary_action_log → apiary
 * directly):
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member.
 * - delete: site-wide "any" OR apiary owner OR beekeeper who created it
 *   (mirrors HiveActionLog — a log is a per-report record).
 */
class ApiaryActionLogAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any apiary action log', 'view own apiary action log');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any apiary action log', 'edit own apiary action log');

      case 'delete':
        return $this->checkApiaryMemberDeleteAccess($apiary, $entity, $account, 'delete any apiary action log', 'delete own apiary action log');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add apiary action log',
    ], 'OR');
  }

}
