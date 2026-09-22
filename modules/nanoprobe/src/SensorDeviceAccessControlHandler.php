<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\ApiaryAccessTrait;

/**
 * Access control handler for Sensor Device entities.
 *
 * Access is scoped to the parent apiary:
 * - view: site-wide "any" OR apiary member OR public apiary.
 * - update: site-wide "any" OR apiary member (owner + beekeepers).
 * - delete: site-wide "any" OR apiary owner only (registered hardware is
 *   foundational apiary structure, mirroring CalendarAction/Hive).
 * - create: `administer hivelog` only, matching `ApiClient`/
 *   `AiProviderConfig`'s own "rare, high-trust, administrator-provisioned"
 *   reasoning — task 0106's resolution of the `add sensor device`
 *   permission that had sat unused since task 0076: rather than wire it
 *   in, it's removed as dead config (see nanoprobe.permissions.yml).
 */
class SensorDeviceAccessControlHandler extends EntityAccessControlHandler {

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
        return $this->checkApiaryViewAccess($apiary, $account, 'view any sensor device', 'view own sensor device');

      case 'update':
        return $this->checkApiaryEditAccess($apiary, $account, 'edit any sensor device', 'edit own sensor device');

      case 'delete':
        return $this->checkApiaryOwnerDeleteAccess($apiary, $account, 'delete any sensor device', 'delete own sensor device');
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer hivelog');
  }

}
