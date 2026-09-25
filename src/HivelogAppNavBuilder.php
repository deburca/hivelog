<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Builds `hivelog`'s theme-independent in-app secondary navigation.
 *
 * Rendered via `hook_preprocess_page()` (task 0105), not a placed
 * block or `hook_page_top()` — see that hook's own docblock in
 * `hivelog.module` for why. Combines `hivelog` core's own 8 built-in
 * destinations with every `hook_hivelog_app_nav_items()` contribution
 * (see `hivelog.api.php`), so `nanoprobe`/`collective`/`nexus` can add
 * their own admin pages without `hivelog` depending on them.
 *
 * Task 0119: this item set is now the single source of truth
 * `hivelog.links.menu.yml`'s menu-link deriver
 * (`Drupal\hivelog\Plugin\Derivative\HivelogMenuLinks`) also reads, via
 * `getAllItems()` — the main-menu links are a second rendering of the
 * exact same registry, not a hand-maintained duplicate list.
 *
 * Task 0120 adds two things `getAllItems()` does NOT carry, both
 * strip-only: the Dashboard entry (prepended directly in `build()` —
 * deliberately not a `getAllItems()` member, since a "Dashboard" child
 * menu link under `hivelog.admin`, which already points at the same
 * route, would be a redundant menu entry even though it's a genuinely
 * useful strip shortcut) and active-state marking (`is-active` /
 * `aria-current`, resolved from the current route — meaningless for a
 * menu link, whose active state the menu system already marks itself).
 */
class HivelogAppNavBuilder {

  use StringTranslationTrait;

  /**
   * Routes with a resolvable subject entity that are not its nav section.
   *
   * Both are per-apiary *reports* — a different feature from "manage
   * apiaries" — carrying `{apiary}` only to scope the aggregation, not
   * because the page is about that apiary record the way a canonical/
   * edit/Insights page is. `hivelog.apiaries.financial_report` (the
   * combined report) needs no entry here: it carries no `{apiary}` at
   * all, so `resolveSubject()` never resolves anything on it to begin
   * with.
   */
  protected const NAV_EXCLUDED_SUBJECT_ROUTES = [
    'hivelog.apiary.inventory_cost_report',
    'hivelog.apiary.calendar_action.collection',
  ];

  /**
   * Display order for item `group` values; a group not listed sorts last.
   *
   * `dashboard` and `default` are never declared by a real item's
   * `group` key today (the Dashboard entry is handled outside
   * `getAllItems()` entirely; every current built-in/hook item declares
   * `records`, `inventory` or `setup` explicitly) — both exist purely
   * as safe fallback positions: `default` for a future item that omits
   * `group`, `dashboard` reserved for symmetry with the Dashboard
   * entry's own synthetic group key in `build()`.
   */
  protected const GROUP_ORDER = ['dashboard', 'records', 'inventory', 'default', 'setup'];

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected CurrentRouteMatch $currentRouteMatch,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds the nav render array.
   *
   * @return array
   *   A render array keyed `hivelog_app_nav`'s own contents, or an
   *   empty array if not a single item's route is accessible to the
   *   current user (e.g. an anonymous visitor).
   */
  public function build(): array {
    $items = ['dashboard' => $this->dashboardItem()] + $this->getAllItems();
    $accessible = array_filter($items, fn(array $item) => $item['url']->access());
    if (!$accessible) {
      return [];
    }

    uasort($accessible, function (array $a, array $b) {
      $group_position = $this->groupPosition($a['group'] ?? 'default') <=> $this->groupPosition($b['group'] ?? 'default');
      return $group_position !== 0 ? $group_position : ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    [$active_key, $aria_current] = $this->resolveActiveItem($accessible);

    $links = [];
    $previous_group = NULL;
    $separator_index = 0;
    foreach ($accessible as $key => $item) {
      $group = $item['group'] ?? 'default';
      if ($previous_group !== NULL && $group !== $previous_group) {
        // A decorative divider between groups, not a nav item of its
        // own — `aria-hidden` keeps it out of a screen reader's list of
        // links, matching how a purely visual separator should behave.
        $links['hivelog_app_nav_separator_' . $separator_index++] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => ['hivelog-app-nav__separator'],
            'aria-hidden' => 'true',
          ],
        ];
      }
      $previous_group = $group;

      $classes = ['hivelog-app-nav__link'];
      $attributes = [];
      if ($key === $active_key) {
        $classes[] = 'is-active';
        $attributes['aria-current'] = $aria_current;
      }
      $links[$key] = [
        '#type' => 'link',
        '#title' => $item['title'],
        '#url' => $item['url'],
        '#attributes' => ['class' => $classes] + $attributes,
      ];
    }

    return [
      // A very low weight, not the usual small integers this codebase
      // uses for page-section ordering elsewhere (e.g.
      // DashboardController's own -30..20 range) — page.content already
      // holds the routed page's own render array (keyed 'system_main'
      // by Drupal's own block-page-variant convention) with an unset,
      // effectively-zero weight by the time this gets merged in
      // alongside it, so this needs to sort ahead of literally
      // everything else already in that region, not just ahead of
      // hivelog's own page-internal sections.
      '#weight' => -1000,
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-app-nav']],
      '#attached' => ['library' => ['hivelog/app_nav']],
      '#cache' => [
        // `url.path` did nothing before task 0120 — output never
        // actually varied by it, only by `user.permissions` — but is
        // exactly right now that active-state marking makes the output
        // genuinely path-dependent (task 0120's own context notes this
        // as the fix: "this only becomes right once [active state]
        // makes the output path-dependent").
        'contexts' => ['url.path', 'user.permissions'],
      ],
    ] + $links;
  }

  /**
   * The given group's position in `GROUP_ORDER`, or last if not listed.
   *
   * `array_search()` alone would sort an unlisted group *first*: its
   * `FALSE` "not found" result compares as `0` against the found
   * integer positions, not as "bigger than everything" the way
   * `GROUP_ORDER`'s own docblock promises. This maps that case to one
   * past the end of the list instead.
   */
  protected function groupPosition(string $group): int {
    $position = array_search($group, self::GROUP_ORDER, TRUE);
    return $position === FALSE ? count(self::GROUP_ORDER) : $position;
  }

  /**
   * The Dashboard nav-strip entry.
   *
   * Strip-only (see class docblock) — not part of `getAllItems()`, so
   * it never becomes a redundant `hivelog.admin`-child menu link
   * pointing at the same route `hivelog.admin` itself already does.
   *
   * @return array
   *   A nav item descriptor in its own synthetic `dashboard` group,
   *   first in `GROUP_ORDER`.
   */
  protected function dashboardItem(): array {
    return [
      'title' => $this->t('Dashboard'),
      'url' => Url::fromRoute('hivelog.dashboard'),
      'weight' => 0,
      'group' => 'dashboard',
    ];
  }

  /**
   * Resolves which item, if any, is the current page's active section.
   *
   * Two rules, in order:
   * - **Exact match** (`aria-current="page"`): an item whose own route is
   *   the current route — the Dashboard item on the dashboard, or e.g.
   *   "Apiaries" on the apiary collection page itself.
   * - **Section match** (`aria-current="true"`): the current route's
   *   subject entity (`HivelogEntityHierarchy::resolveSubject()` — the
   *   same resolution the breadcrumb trail uses) belongs to a type an
   *   item declares as its `section`. Covers every non-collection page
   *   for that type: canonical, edit, delete, add, and named sub-pages
   *   like Hive Insights — all "about" that section without being its
   *   collection route specifically. `NAV_EXCLUDED_SUBJECT_ROUTES`
   *   carves out the two apiary-scoped report routes, which resolve a
   *   subject but aren't conceptually part of "manage apiaries".
   *
   * @param array $items
   *   Accessible nav items, keyed.
   *
   * @return array{0: string|null, 1: string|null}
   *   `[item key, aria-current value]`, or `[NULL, NULL]` if nothing
   *   matches (a report page, a non-hivelog page, or any route with no
   *   resolvable subject and no exact item match).
   */
  protected function resolveActiveItem(array $items): array {
    $route_name = $this->currentRouteMatch->getRouteName();
    if (!$route_name) {
      return [NULL, NULL];
    }

    foreach ($items as $key => $item) {
      if ($item['url']->isRouted() && $item['url']->getRouteName() === $route_name) {
        return [$key, 'page'];
      }
    }

    if (in_array($route_name, self::NAV_EXCLUDED_SUBJECT_ROUTES, TRUE)) {
      return [NULL, NULL];
    }

    $subject = HivelogEntityHierarchy::resolveSubject($this->currentRouteMatch, $route_name);
    if (!$subject) {
      return [NULL, NULL];
    }

    $type_id = $subject->getEntityTypeId();
    foreach ($items as $key => $item) {
      if (($item['section'] ?? NULL) === $type_id) {
        return [$key, 'true'];
      }
    }

    return [NULL, NULL];
  }

  /**
   * The full nav item registry: core built-ins plus every hook contribution.
   *
   * Unfiltered by the current user's access — `build()` filters for the
   * nav strip; the menu-link deriver (task 0119) wants every item
   * regardless of who's currently logged in, since a menu link's own
   * route requirements do that access check when the menu is rendered.
   *
   * @return array[]
   *   `['title' => TranslatableMarkup, 'url' => Url, 'weight' => int,
   *   'group' => string, 'section' => string (optional)]` descriptors,
   *   keyed uniquely.
   */
  public function getAllItems(): array {
    $items = $this->builtInItems();
    foreach ($this->moduleHandler->invokeAll('hivelog_app_nav_items') as $key => $item) {
      $items[$key] = $item;
    }
    return $items;
  }

  /**
   * Hivelog core's own built-in nav items.
   *
   * Same 8 destinations, same weights, as `hivelog.links.menu.yml`'s old
   * hand-written entries — now the *source* the menu-link deriver reads,
   * not a second copy kept in sync by hand (task 0119). `group` splits
   * day-to-day record-keeping from the inventory/sales side (task
   * 0120); `section` names the entity type each item's collection is
   * "about", for `resolveActiveItem()`.
   *
   * @return array[]
   *   `['title' => TranslatableMarkup, 'url' => Url, 'weight' => int,
   *   'group' => string, 'section' => string]` descriptors, keyed
   *   uniquely.
   */
  protected function builtInItems(): array {
    return [
      'apiaries' => [
        'title' => $this->t('Apiaries'),
        'url' => Url::fromRoute('entity.apiary.collection'),
        'weight' => 0,
        'group' => 'records',
        'section' => 'apiary',
      ],
      'hives' => [
        'title' => $this->t('Hives'),
        'url' => Url::fromRoute('entity.hive.collection'),
        'weight' => 1,
        'group' => 'records',
        'section' => 'hive',
      ],
      'inspections' => [
        'title' => $this->t('Inspections'),
        'url' => Url::fromRoute('entity.hive_inspection.collection'),
        'weight' => 2,
        'group' => 'records',
        'section' => 'hive_inspection',
      ],
      'queens' => [
        'title' => $this->t('Queens'),
        'url' => Url::fromRoute('entity.queen.collection'),
        'weight' => 3,
        'group' => 'records',
        'section' => 'queen',
      ],
      'queen_observations' => [
        'title' => $this->t('Queen Observations'),
        'url' => Url::fromRoute('entity.queen_observation.collection'),
        'weight' => 4,
        'group' => 'records',
        'section' => 'queen_observation',
      ],
      'inventory_items' => [
        'title' => $this->t('Inventory Items'),
        'url' => Url::fromRoute('entity.inventory_item.collection'),
        'weight' => 5,
        'group' => 'inventory',
        'section' => 'inventory_item',
      ],
      'inventory_purchases' => [
        'title' => $this->t('Inventory Purchases'),
        'url' => Url::fromRoute('entity.inventory_purchase.collection'),
        'weight' => 6,
        'group' => 'inventory',
        'section' => 'inventory_purchase',
      ],
      'products' => [
        'title' => $this->t('Products'),
        'url' => Url::fromRoute('entity.product.collection'),
        'weight' => 7,
        'group' => 'inventory',
        'section' => 'product',
      ],
    ];
  }

}
