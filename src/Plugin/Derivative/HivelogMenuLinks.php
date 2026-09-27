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
 *
 * Task 0150, ADR-0104: an item's `parent` key (task 0147) is mirrored
 * here too — a derivative whose item declares `parent` gets
 * `'hivelog.nav_item:' . $parent_key` as ITS OWN `parent`, nesting the
 * derived menu link under its hub's derived link instead of flatly
 * under `hivelog.admin`. Only if that parent key names another
 * *derivable* item (present, routed) — an unresolvable `parent` (a
 * typo, or naming an item with no route) falls back to
 * `hivelog.links.menu.yml`'s own default `parent: hivelog.admin`
 * exactly as every item did before this task, rather than producing a
 * menu link parented on a plugin ID that doesn't exist.
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
    $items = $this->appNavBuilder->getAllItems();

    // Which keys will actually get a derivative below — the only valid
    // `parent` targets (task 0150). Computed once so an item order in
    // `getAllItems()` never matters (a child could iterate before its
    // parent, or after).
    $derivable_keys = [];
    foreach ($items as $key => $item) {
      if ($item['url']->isRouted()) {
        $derivable_keys[$key] = TRUE;
      }
    }

    foreach ($items as $key => $item) {
      if (!$item['url']->isRouted()) {
        continue;
      }

      $definition = [
        'title' => $item['title'],
        'route_name' => $item['url']->getRouteName(),
        'route_parameters' => $item['url']->getRouteParameters(),
        'weight' => $item['weight'] ?? 0,
      ];

      $parent_key = $item['parent'] ?? NULL;
      if ($parent_key !== NULL && isset($derivable_keys[$parent_key])) {
        $definition['parent'] = 'hivelog.nav_item:' . $parent_key;
      }

      $this->derivatives[$key] = $definition + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
