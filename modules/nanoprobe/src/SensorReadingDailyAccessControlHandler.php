<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\ApiaryAccessTrait;

/**
 * Access control handler for Sensor Reading Daily Rollup entities.
 *
 * Deliberately reuses `SensorReading`'s own `view own/any sensor
 * reading` and `delete own/any sensor reading` permissions rather than
 * minting new ones — see the entity class's own docblock. Same
 * "machine-written only" shape as `SensorReading`: no add/edit form
 * exists, so only `view` and `delete` (admin cleanup) are meaningful.
 *
 * Access is scoped to the parent apiary:
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - delete: site-wide "any" OR apiary owner only.
 */
class SensorReadingDailyAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any sensor reading', 'view own sensor reading');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any sensor reading', 'delete own sensor reading');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // No add form is ever built for this entity type — only
    // SensorReadingRetentionService's own trusted programmatic save
    // creates rows. Admins retain create access for parity with every
    // other hivelog entity type.
    return AccessResult::allowedIfHasPermission($account, 'administer hivelog');
  }

}
