<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Routing;

use Drupal\Core\Routing\FilterInterface;
use Drupal\hivelog_api\HivelogApiResources;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Allows write methods on the versioned prefix despite JSON:API read-only.
 *
 * JSON:API's read-only mode is one site-wide setting that drops every write
 * route. Enabling this module should not flip it for the whole site, so a
 * request that arrived on `/hivelog/api/v1` is filtered by the plain core
 * method filter instead, and every other request still meets whatever the
 * site configured. Access checks and the allow-list are unaffected.
 */
class HivelogApiMethodFilter implements FilterInterface {

  public function __construct(
    protected FilterInterface $inner,
    protected FilterInterface $coreMethodFilter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function filter(RouteCollection $collection, Request $request) {
    if (HivelogApiResources::isVersionedRequest($request)) {
      return $this->coreMethodFilter->filter($collection, $request);
    }
    return $this->inner->filter($collection, $request);
  }

}
