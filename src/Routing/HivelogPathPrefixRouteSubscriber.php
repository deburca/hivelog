<?php

declare(strict_types=1);

namespace Drupal\hivelog\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\hivelog\HivelogPaths;
use Symfony\Component\Routing\RouteCollection;

/**
 * Serves HiveLog's pages under the configured path prefix (task 0246).
 *
 * Moves every route declared under `/hivelog` (the module's own, the
 * submodules' and the entity link templates' alike) to the prefix in
 * `$settings['hivelog_path_prefix']`, when one is set. Route names, parameters
 * and access are untouched, so nothing that links by route name notices.
 */
final class HivelogPathPrefixRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection as $route) {
      $moved = HivelogPaths::moved($route->getPath());
      if ($moved !== NULL) {
        $route->setPath($moved);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // After every other module has added or altered its routes.
    $events['routing.route_alter'] = ['onAlterRoutes', -1000];
    return $events;
  }

}
