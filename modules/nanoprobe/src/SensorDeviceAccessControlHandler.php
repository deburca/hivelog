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
 * - create: `add sensor device` OR `administer hivelog` — matches
 *   `HiveAccessControlHandler::checkCreateAccess()`'s own shape exactly
 *   (task 0135). The apiary/hive scoping this permission alone can't
 *   express lives at the route level instead, the same way `add hive`'s
 *   does: `nanoprobe.sensor_device.add_for_hive`/`_apiary` additionally
 *   require `hive.update`/`apiary.update` on the target entity in the
 *   URL. Task 0106 had removed this permission as dead config, matching
 *   `ApiClient`/`AiProviderConfig`'s "rare, high-trust,
 *   administrator-provisioned" reasoning — 0135 revisited that call for
 *   hardware specifically, which (unlike an API credential or AI
 *   provider integration) a beekeeper already manages day-to-day on
 *   their own hive/apiary.
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
    return AccessResult::allowedIfHasPermissions($account, [
      'administer hivelog',
      'add sensor device',
    ], 'OR');
  }

}
