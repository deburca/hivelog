<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Site\Settings;

/**
 * The path prefix HiveLog's pages are served under (task 0246).
 *
 * Every route in the module and its submodules is declared under `/hivelog`,
 * which reads as "hivelog.eu/hivelog" on a site named after it. A site may
 * serve them under another prefix instead with a setting in `settings.php`:
 *
 * @code
 * $settings['hivelog_path_prefix'] = 'app';
 * @endcode
 *
 * Unset, the pages stay at `/hivelog/...` as before. The routes keep their
 * names and parameters, and `HivelogPathPrefixRouteSubscriber` moves their
 * paths when the router is built, so after changing the setting the router
 * must be rebuilt (`drush cr`). The machine endpoints under `/hivelog/api/`
 * (sensor ingestion, the collective context API) are a documented contract
 * with devices and clients and never move; the field-app API is at `/api/v1`
 * whatever this says.
 */
final class HivelogPaths {

  /**
   * The prefix the routes are declared under, and the default one.
   */
  public const DEFAULT_PREFIX = 'hivelog';

  /**
   * Prefixes a site may not choose: they belong to core or to other modules.
   */
  private const RESERVED = [
    'admin', 'api', 'batch', 'contextual', 'core', 'entity', 'files', 'jsonapi', 'libraries', 'media', 'modules',
    'node', 'oauth', 'profiles', 'sites', 'system', 'taxonomy', 'themes', 'tfa', 'user', 'vendor', 'webform',
  ];

  /**
   * The configured prefix, without slashes (`hivelog` or a site's own).
   *
   * An unusable value (not lower-case letters, digits and hyphens, or one of
   * the reserved names) is ignored rather than allowed to break routing.
   */
  public static function prefix(): string {
    try {
      $value = Settings::get('hivelog_path_prefix', self::DEFAULT_PREFIX);
    }
    catch (\BadMethodCallException) {
      // Settings are not loaded yet (a unit test, or very early bootstrap).
      return self::DEFAULT_PREFIX;
    }
    $value = is_string($value) ? trim($value, '/') : '';
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $value) || in_array($value, self::RESERVED, TRUE)) {
      return self::DEFAULT_PREFIX;
    }
    return $value;
  }

  /**
   * Whether a route path (as the router holds it) is one of HiveLog's pages.
   */
  public static function matches(string $path): bool {
    $root = '/' . self::prefix();
    return $path === $root || str_starts_with($path, $root . '/');
  }

  /**
   * The path a route declared under `/hivelog` is served at.
   *
   * @return string|null
   *   NULL when the path is not one to move: it is not under `/hivelog`, or it
   *   is a machine endpoint under `/hivelog/api/`, or nothing is configured.
   */
  public static function moved(string $declared): ?string {
    $prefix = self::prefix();
    if ($prefix === self::DEFAULT_PREFIX) {
      return NULL;
    }
    $default = '/' . self::DEFAULT_PREFIX;
    if ($declared !== $default && !str_starts_with($declared, $default . '/')) {
      return NULL;
    }
    if (str_starts_with($declared, $default . '/api/')) {
      return NULL;
    }
    return '/' . $prefix . substr($declared, strlen($default));
  }

}
