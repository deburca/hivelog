<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\hivelog_api\HivelogApiResources;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Serves core JSON:API under the versioned /hivelog/api/v1 prefix.
 *
 * Inbound: `/hivelog/api/v1/hive/hive/…` becomes `/jsonapi/hive/hive/…` so
 * the stock routes, controllers, access and normalisation all apply, but only
 * for an allow-listed resource type; any other path is left alone and so
 * finds no route (404). It must stay free of side effects: the router caches
 * an inbound path's result, so this only runs on the first request for a path.
 *
 * Outbound: on a request that came in on the prefix, JSON:API links to an allow-listed resource
 * are written back with the prefix, so a client follows links without ever
 * leaving it. A response that depends on that carries the `url.path` cache
 * context, since the same route is reachable under both prefixes.
 */
class HivelogApiPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  public function __construct(
    protected HivelogApiResources $resources,
    protected RequestStack $requestStack,
    protected string $jsonapiBasePath,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $prefix = HivelogApiResources::PATH_PREFIX . '/';
    if (!str_starts_with($path, $prefix)) {
      return $path;
    }
    $rest = substr($path, strlen($prefix));
    if (!$this->restIsAllowed($rest)) {
      return $path;
    }
    return $this->jsonapiBasePath . '/' . $rest;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $base = $this->jsonapiBasePath . '/';
    if (!str_starts_with($path, $base)) {
      return $path;
    }
    $rest = substr($path, strlen($base));
    if (!$this->restIsAllowed($rest)) {
      return $path;
    }
    // The generated link now depends on which prefix the request used.
    $bubbleable_metadata?->addCacheContexts(['url.path']);

    $current = $request ?? $this->requestStack->getCurrentRequest();
    if (!$current || !HivelogApiResources::isVersionedRequest($current)) {
      return $path;
    }
    return HivelogApiResources::PATH_PREFIX . '/' . $rest;
  }

  /**
   * Whether the path after the prefix starts with an allow-listed resource.
   */
  protected function restIsAllowed(string $rest): bool {
    $segments = explode('/', $rest);
    if (count($segments) < 2 || !$this->resources->isAllowed($segments[0], $segments[1])) {
      return FALSE;
    }
    // A file is only ever fetched by id or uploaded onto a field. Its
    // collection would list every file on the site, and the app has no use
    // for it.
    return $segments[0] !== 'file' || count($segments) >= 3;
  }

}
