<?php

declare(strict_types=1);

namespace Drupal\hivelog\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\hivelog\HivelogAppNavBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Grants access to the Setup landing page iff it has something to show.
 *
 * Used as `_custom_access` on `hivelog.setup` (task 0146, ADR-0104).
 * Deliberately not a `_permission` string: "Setup" has no permission of
 * its own, and hard-coding one of `collective`/`nanoprobe`/`nexus`'s
 * permissions here would make `hivelog` core depend on a submodule it
 * doesn't otherwise know about. Access is derived from the same
 * registry `HivelogAppNavBuilder::build()` already reads: allowed iff
 * at least one `parent: 'setup'` item is accessible to this account,
 * denied (not neutral — this is the route's only access check) if none
 * are, e.g. no submodule contributing one is installed.
 */
class SetupPageAccessCheck implements AccessInterface, ContainerInjectionInterface {

  public function __construct(
    protected HivelogAppNavBuilder $appNavBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    // `new self()`, not `new static()` — this class is not expected to
    // be subclassed (see `HivelogMenuLinks::create()`'s own precedent).
    return new self($container->get('hivelog.app_nav_builder'));
  }

  /**
   * Checks access.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $children = $this->appNavBuilder->getAccessibleChildren('setup', $account);

    if ($children) {
      return AccessResult::allowed()->addCacheContexts(['user.permissions']);
    }
    return AccessResult::forbidden('No Setup destinations are available to this account.')
      ->addCacheContexts(['user.permissions']);
  }

}
