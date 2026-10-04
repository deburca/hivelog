<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hivelog_api\HivelogApiResources;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The API's unauthenticated discovery document.
 *
 * A client reads this before it signs in, to confirm the server speaks an API
 * version it supports and to find the OAuth endpoints. It contains only
 * static facts about the site and the API, never user data.
 */
class DiscoveryController extends ControllerBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected HivelogApiResources $resources,
  ) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('config.factory'),
      $container->get('hivelog_api.resources'),
    );
  }

  /**
   * Returns the discovery document.
   */
  public function index(): CacheableJsonResponse {
    $site = $this->configFactory->get('system.site');
    $url = fn(string $route): string => Url::fromRoute($route)->setAbsolute()->toString();

    $self = Url::fromRoute('hivelog_api.discovery')->setAbsolute()->toString();
    $links = ['self' => ['href' => $self]];
    foreach (HivelogApiResources::RESOURCES as $resource) {
      [$entity_type_id, $bundle] = explode('/', $resource);
      $links[$entity_type_id . '--' . $bundle] = ['href' => $self . '/' . $resource];
    }

    $document = [
      'jsonapi' => ['version' => '1.0'],
      'meta' => [
        'hivelog_api' => [
          'api_version' => HivelogApiResources::API_VERSION,
          'module_version' => $this->resources->moduleVersion(),
          'site_name' => (string) $site->get('name'),
          'oauth' => [
            'client_id' => HivelogApiResources::CLIENT_ID,
            'scope' => HivelogApiResources::SCOPE,
            'authorize' => $url('oauth2_token.authorize'),
            'token' => $url('oauth2_token.token'),
          ],
        ],
      ],
      'links' => $links,
    ];

    $response = new CacheableJsonResponse($document, 200, ['Content-Type' => 'application/vnd.api+json']);
    $response->addCacheableDependency((new CacheableMetadata())
      ->addCacheContexts(['url.site'])
      ->addCacheTags(['config:system.site']));
    return $response;
  }

}
