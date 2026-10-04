<?php

declare(strict_types=1);

namespace Drupal\hivelog_api;

use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\HttpFoundation\Request;

/**
 * The versioned resource allow-list of the HiveLog API.
 *
 * Only the field-workflow record types are reachable under the versioned
 * prefix; everything else (inventory, products, sensors, API clients, AI
 * providers, users, config) is simply not routed there (ADR-0107 §1).
 */
class HivelogApiResources {

  /**
   * The API contract version. Bump on a breaking change to any exposed type.
   *
   * Pinned by the contract fixture test, which fails when an exposed
   * resource or field is renamed or removed.
   */
  public const API_VERSION = 1;

  /**
   * The versioned path prefix.
   */
  public const PATH_PREFIX = '/hivelog/api/v1';

  /**
   * The OAuth client id, scope and redirect the app uses.
   */
  public const CLIENT_ID = 'hivelog-ios';

  public const SCOPE = 'hivelog_field_app';

  public const REDIRECT_URI = 'hivelog://oauth/callback';

  /**
   * Allow-listed JSON:API resources, as "entity_type_id/bundle".
   *
   * @var string[]
   */
  public const RESOURCES = [
    'apiary/apiary',
    'hive/hive',
    'hive_inspection/hive_inspection',
    'queen/queen',
    'queen_observation/queen_observation',
    'calendar_action/calendar_action',
    'hive_action_log/hive_action_log',
    'apiary_action_log/apiary_action_log',
    'file/file',
  ];

  public function __construct(
    protected ModuleExtensionList $moduleList,
    protected string $jsonapiBasePath,
  ) {}

  /**
   * Whether a resource type is exposed under the versioned prefix.
   */
  public function isAllowed(string $entity_type_id, string $bundle): bool {
    return in_array($entity_type_id . '/' . $bundle, self::RESOURCES, TRUE);
  }

  /**
   * Whether a request arrived on the versioned prefix.
   *
   * Read from the request's own path, not from a flag set while routing: the
   * router caches an inbound path's processed result, so a path processor's
   * side effects only happen on the first request for a path.
   */
  public static function isVersionedRequest(Request $request): bool {
    return str_starts_with($request->getPathInfo(), self::PATH_PREFIX . '/');
  }

  /**
   * Gets the installed module version, for the discovery document.
   */
  public function moduleVersion(): string {
    return (string) ($this->moduleList->getExtensionInfo('hivelog_api')['version'] ?? '');
  }

}
