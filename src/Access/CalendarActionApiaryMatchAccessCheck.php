<?php

declare(strict_types=1);

namespace Drupal\hivelog\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\ApiaryAccessTrait;

/**
 * Confirms a route's {calendar_action} belongs to its {apiary}/{hive}.
 *
 * Used as `_custom_access` on `hivelog.hive_action_log.add` and
 * `hivelog.apiary_action_log.add` (task 0133). Without it, `{apiary}` /
 * `{hive}` and `{calendar_action}` upcast independently — a user with
 * update access on their own hive/apiary could still log against another
 * apiary's calendar action by editing the URL, since `_entity_access` on
 * the route only checks each parameter on its own.
 */
class CalendarActionApiaryMatchAccessCheck implements AccessInterface {

  use ApiaryAccessTrait;

  /**
   * Checks access.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $calendar_action = $route_match->getParameter('calendar_action');
    if (!$calendar_action) {
      return AccessResult::neutral();
    }

    // Whichever of {apiary} / {hive} the route carries.
    $context_entity = $route_match->getParameter('apiary') ?? $route_match->getParameter('hive');
    if (!$context_entity) {
      return AccessResult::neutral();
    }

    $context_apiary = $this->resolveApiary($context_entity);
    $action_apiary = $this->resolveApiary($calendar_action);

    if (!$context_apiary || !$action_apiary || (string) $context_apiary->id() !== (string) $action_apiary->id()) {
      return AccessResult::forbidden('The calendar action does not belong to this apiary.')
        ->addCacheableDependency($calendar_action)
        ->addCacheableDependency($context_entity);
    }

    return AccessResult::allowed()
      ->addCacheableDependency($calendar_action)
      ->addCacheableDependency($context_entity);
  }

}
