<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\hivelog\HivelogAppNavBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives one main-menu link per in-app nav item (task 0119).
 *
 * `HivelogAppNavBuilder::getAllItems()` — core's own 8 built-ins plus
 * every `hook_hivelog_app_nav_items()` contribution — is now the single
 * source of truth for both the nav strip and these menu links, so a
 * submodule that wants a main-menu entry implements the nav hook only;
 * it no longer ships its own `<module>.links.menu.yml`.
 *
 * Item descriptors carry a `Url`, not a route name/parameters pair, but
 * every implementation in this codebase builds it via `Url::fromRoute()`
 * with no parameters (a plain collection route) — `getRouteName()` /
 * `getRouteParameters()` read back exactly that, so the hook contract in
 * `hivelog.api.php` didn't need to change to make items derivable. A
 * contribution built from an external URI instead (`Url::fromUri()`) has
 * no route to link a menu item to, so it's skipped rather than fatal.
 */
class HivelogMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected HivelogAppNavBuilder $appNavBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    // `new self()`, not `new static()` — this class is not expected to
    // be subclassed, so there's no late-static-binding need to accept
    // phpstan's "Unsafe usage of new static()" finding for.
    return new self($container->get('hivelog.app_nav_builder'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->appNavBuilder->getAllItems() as $key => $item) {
      if (!$item['url']->isRouted()) {
        continue;
      }
      $this->derivatives[$key] = [
        'title' => $item['title'],
        'route_name' => $item['url']->getRouteName(),
        'route_parameters' => $item['url']->getRouteParameters(),
        'weight' => $item['weight'] ?? 0,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
