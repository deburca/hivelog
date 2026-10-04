<?php

declare(strict_types=1);

namespace Drupal\hivelog_api_test\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Lets Basic Auth stand in for the OAuth bearer token on the API's routes.
 *
 * In production the OAuth provider is global, so a bearer token needs no route
 * option. Basic Auth is not global, so the kernel tests opt these routes in.
 */
class TestRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $name => $route) {
      if (str_starts_with($name, 'hivelog_api.computed_')) {
        $route->setOption('_auth', ['basic_auth', 'cookie']);
      }
    }
  }

}
