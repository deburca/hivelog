<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\ApiaryAccessTrait;

/**
 * Access control handler for Hive Insight entities.
 *
 * Rows are machine-written only — no add or edit form is ever built for
 * this entity type, so only `view` and `delete` (admin cleanup) are
 * meaningful operations. Non-admin create access is always denied.
 * Mirrors `\Drupal\nanoprobe\SensorReadingAccessControlHandler` exactly.
 * Relocated here, unchanged, from `collective` by task 0102.
 *
 * Access is scoped to the parent apiary:
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - delete: site-wide "any" OR apiary owner only.
 */
class HiveInsightAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any hive insight', 'view own hive insight');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any hive insight', 'delete own hive insight');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // No add form is ever built for this entity type — only
    // nexus_cron()'s own trusted programmatic write creates rows. Admins
    // retain create access for parity with every other hivelog entity
    // type.
    return AccessResult::allowedIfHasPermission($account, 'administer hivelog');
  }

}
