<?php

namespace Drupal\hivelog\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hivelog\HivelogEntityHierarchy;

/**
 * Provides breadcrumbs for hivelog entity routes.
 *
 * Built around one declarative parent map (task 0116, extracted to
 * `HivelogEntityHierarchy` in task 0127): entity type ID → the reference
 * field that names its parent. `build()` picks the route's "subject"
 * entity (the one upcast route parameter the trail is built from — see
 * `HivelogEntityHierarchy::resolveSubject()`, itself extracted there in
 * task 0120 so the nav strip's active-section resolution reads the same
 * definition of "what is this page about"), then `addAncestryLinks()`
 * walks the map from the subject up to the root, adding one crumb per
 * ancestor in root-to-leaf order. A missing reference (a deleted apiary,
 * an unassigned queen) just stops the walk there, shortening the trail
 * — the same behaviour the previous per-type blocks had.
 *
 * Every trail ends with a crumb naming the current page (ADR-0102, task
 * 0117): the entity's own label on a canonical page, "Edit" / "Delete"
 * on those forms, and the route's own title everywhere else (add forms,
 * scoped or site-wide, and any other bolt-on route under `/hivelog`) —
 * see `addGenericTerminalCrumb()`.
 *
 * `$entityTypeManager` was dropped entirely in task 0116 (deriving
 * collection-route labels from it was "considered and rejected" there,
 * specifically to keep that task a behaviour-preserving pure refactor)
 * and is reintroduced here for exactly that purpose (task 0119): now
 * that `HiveInspection::label_collection` reads "Inspections" like
 * every other surface, `collectionLabels()` can read each type's own
 * `label_collection` instead of a hand-maintained duplicate.
 *
 * Task 0153, ADR-0105 adds a second, independent declarative map,
 * `COLLECTION_ANCESTOR_ROUTE`, walked by `addCollectionAncestryLinks()`
 * — the same "walk a map to the root" shape as `PARENT_FIELD`/
 * `addAncestryLinks()` above, but for a *collection* route's own
 * conceptual parent collection(s) (e.g. Inspections' collection page
 * threading `Apiaries › Hives › Inspections`) rather than a specific
 * entity instance's real ancestor chain. The two maps serve different
 * page kinds and are deliberately not merged: changing what a
 * `PARENT_FIELD` entry means would also silently change every
 * per-instance canonical/edit/delete breadcrumb of that type, which
 * this feature was never asked to touch.
 */
class HivelogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Route → the route parameter its terminal crumb link is built with.
   *
   * The subject's own ID. Used for named sub-pages that are not an
   * entity's canonical page: reports, Insights, sensor readings /
   * config download, token regeneration. See `terminalCrumbLabel()` for
   * the matching label per route — kept as literal `t()` calls rather
   * than a variable-driven one, per Drupal coding standards (translated
   * strings must be extractable as literals).
   */
  protected const TERMINAL_CRUMB_PARAM = [
    'hivelog.apiary.inventory_cost_report' => 'apiary',
    'hivelog.apiary.calendar_action.collection' => 'apiary',
    'entity.hive.insights' => 'hive',
    'entity.sensor_device.readings' => 'sensor_device',
    'nanoprobe.sensor_device.config' => 'sensor_device',
    'collective.api_client.regenerate_token' => 'api_client',
  ];

  /**
   * A collection route → the route of its own parent crumb.
   *
   * Task 0153, ADR-0105: a *separate* declarative map from
   * `HivelogEntityHierarchy::PARENT_FIELD` — that one walks a specific
   * *instance's* real reference field for canonical/edit/delete pages
   * (and is unaffected by this one); this map only ever fires for a
   * *collection* route, threading through the conceptual type
   * hierarchy the two-tier nav strip (task 0148) already shows for the
   * same relationships, one level at a time. `build()` walks this from
   * the current route to its root — neither `entity.apiary.collection`
   * nor `hivelog.insights` is a key here, so the walk always
   * terminates there. Deliberately does not cover
   * `calendar_action`/`hive_action_log`/`apiary_action_log` (their own
   * per-instance threading, task 0122, already has a different,
   * well-established rule this would need reconciling with, not just
   * copying — see ADR-0105's own Open questions) or the combined
   * financial report (no single parent to thread).
   */
  protected const COLLECTION_ANCESTOR_ROUTE = [
    'entity.hive.collection' => 'entity.apiary.collection',
    'entity.hive_inspection.collection' => 'entity.hive.collection',
    'entity.queen.collection' => 'entity.hive.collection',
    'entity.queen_observation.collection' => 'entity.queen.collection',
    'entity.inventory_item.collection' => 'entity.apiary.collection',
    'entity.inventory_purchase.collection' => 'entity.inventory_item.collection',
    'entity.product.collection' => 'entity.apiary.collection',
    'entity.api_client.collection' => 'hivelog.insights',
    'entity.ai_provider_config.collection' => 'hivelog.insights',
    'entity.sensor_device.collection' => 'hivelog.insights',
  ];

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.Commenting.FunctionComment.ParamNameNoMatch
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL): bool {
    $route_name = $route_match->getRouteName();

    // Explicitly exclude non-page routes under /hivelog that must not
    // receive a breadcrumb (e.g. file-download endpoints). Keep this list
    // in sync with hivelog.routing.yml (see AGENTS.md).
    $non_page_routes = [
      'hivelog.queen.observations_csv',
    ];
    if (in_array($route_name, $non_page_routes, TRUE)) {
      return FALSE;
    }

    // Every page whose path is under /hivelog — the module's own entity
    // routes, the hivelog.* controllers, and bolt-on routes such as
    // Layout Builder overrides (layout_builder.overrides.<entity>.*). A
    // path match keeps this exhaustive without a per-route-name allow
    // list that drifts as routes are added (task 0067).
    $route = $route_match->getRouteObject();
    $path = $route ? $route->getPath() : '';
    return $path === '/hivelog' || str_starts_with($path, '/hivelog/');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    // 'url.path.parent' / 'url.path.is_front' alongside 'route': core's
    // own fallback (\Drupal\system\PathBasedBreadcrumbBuilder, priority
    // 0) declares exactly those two on every breadcrumb it builds — a
    // shared block instance (e.g. system_breadcrumb_block) that renders
    // on both a hivelog route and a non-hivelog route in the same
    // session needs the two builders' declared context sets to share at
    // least one context, or Drupal\Core\Cache\VariationCache::set()
    // throws "Trying to overwrite a cache redirect... with one that has
    // nothing in common" the moment the second builder's result is
    // cached — reproduced by DashboardTest, which places that exact
    // block and visits both a hivelog and a non-hivelog (login) route
    // in the same test. Keeping 'route' too, not replacing it with the
    // other two: this builder's trail genuinely can vary between two
    // routes sharing the same path shape (e.g. Layout Builder override
    // routes), which 'url.path.parent' alone wouldn't distinguish.
    $breadcrumb->addCacheContexts(['route', 'url.path.parent', 'url.path.is_front']);
    $route_name = $route_match->getRouteName();

    // Home > HiveLog. "HiveLog" links to the dashboard landing page
    // (ADR-0057); on the dashboard route itself it is the terminal crumb.
    // Every hivelog route ends up with the last-added crumb rendered as
    // plain text by the theme (task 0013), regardless of what route it
    // is — this builder always emits a Link either way.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('HiveLog'), 'hivelog.dashboard'));

    // Collection listings and the cross-apiary report page carry no
    // entity-ancestor chain: every hivelog route is covered by this
    // builder (task 0067), so each of these needs its own terminal
    // crumb — the page's own name, a self-link the theme renders as
    // plain text — rather than falling through to a menu-derived one.
    $collections = $this->collectionLabels();

    // Collection listings + the combined report + Insights: their own
    // name is the terminal crumb (a self-link the theme renders as
    // plain text). `hivelog.insights` (task 0146, named `hivelog.setup`
    // before task 0152's rename) belongs here rather than in
    // `TERMINAL_CRUMB_PARAM` below: that map threads a *subject entity's*
    // ancestor chain in front of a literal terminal label, and Insights,
    // like the combined report, resolves no subject at all.
    $leaf_pages = $collections + [
      'hivelog.apiaries.financial_report' => $this->t('Financial Report: All Apiaries'),
      'hivelog.insights' => $this->t('Insights'),
    ];
    if (isset($leaf_pages[$route_name])) {
      // Task 0153, ADR-0105: a route in `COLLECTION_ANCESTOR_ROUTE`
      // gets its conceptual parent collection(s) threaded in first —
      // every other leaf page (the combined report, Insights itself,
      // calendar actions and the two action-log types) stays exactly
      // the flat single self-link it always was.
      $this->addCollectionAncestryLinks($breadcrumb, $route_name, $leaf_pages);
      $breadcrumb->addLink(Link::createFromRoute($this->collectionCrumbLabel($route_name, $leaf_pages), $route_name));
      return $breadcrumb;
    }

    // Site-wide "add" forms (entity.<type>.add_form, no parent in the
    // path) hang off their collection: Home › HiveLog › <Plural> › Add
    // <Type> — threading that collection's own ancestry first (task
    // 0153), same as a direct visit to the collection itself would,
    // so the two pages never disagree about where the collection sits.
    if (preg_match('/^entity\.([a-z_]+)\.add_form$/', $route_name, $m)
      && isset($collections["entity.{$m[1]}.collection"])) {
      $collection_route = "entity.{$m[1]}.collection";
      $this->addCollectionAncestryLinks($breadcrumb, $collection_route, $leaf_pages);
      $breadcrumb->addLink(Link::createFromRoute(
        $this->collectionCrumbLabel($collection_route, $leaf_pages),
        $collection_route,
      ));
      $this->addGenericTerminalCrumb($breadcrumb, $route_match, $route_name);
      return $breadcrumb;
    }

    $subject = HivelogEntityHierarchy::resolveSubject($route_match, $route_name);
    if (!$subject) {
      return $breadcrumb;
    }

    $this->addAncestryLinks($breadcrumb, $subject, $collections);

    $terminal_param = self::TERMINAL_CRUMB_PARAM[$route_name] ?? NULL;
    if ($terminal_param) {
      // Named sub-pages already follow the rule (task 0102): their own
      // short label, linked to the current route.
      $breadcrumb->addLink(Link::createFromRoute(
        $this->terminalCrumbLabel($route_name),
        $route_name,
        [$terminal_param => $subject->id()],
      ));
    }
    elseif (!str_ends_with($route_name, '.canonical')) {
      // Canonical pages already got their terminal crumb from
      // `addEntityLink()` above (the entity's own label, task 0013).
      // Everything else that reaches here — edit / delete forms, scoped
      // add forms, and any bolt-on route such as a Layout Builder
      // override — needs one of its own (task 0117 / ADR-0102).
      $this->addGenericTerminalCrumb($breadcrumb, $route_match, $route_name);
    }

    return $breadcrumb;
  }

  /**
   * Collection-route labels, keyed by route name (task 0119).
   *
   * Read from each entity type's own `label_collection` rather than a
   * hand-maintained duplicate — `HivelogEntityHierarchy::COLLECTION_TYPES`
   * is the single list of which types have a collection route at all.
   * `hasDefinition()` guards a submodule type that isn't installed;
   * skipping it there is safe because a route for that type couldn't be
   * the current one either, so no caller ever misses a label it needs.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Collection route name => that type's collection label.
   */
  protected function collectionLabels(): array {
    $labels = [];
    foreach (HivelogEntityHierarchy::COLLECTION_TYPES as $type_id) {
      if ($this->entityTypeManager->hasDefinition($type_id)) {
        $labels["entity.$type_id.collection"] = $this->entityTypeManager->getDefinition($type_id)->getCollectionLabel();
      }
    }
    return $labels;
  }

  /**
   * Adds one crumb per `COLLECTION_ANCESTOR_ROUTE` ancestor, root first.
   *
   * Task 0153, ADR-0105. Mirrors `addAncestryLinks()`'s own "walk a
   * declarative map to the root" shape, but over route names for a
   * collection page rather than entity instances for a canonical one.
   *
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   The breadcrumb being built.
   * @param string $route_name
   *   The current (leaf) collection route.
   * @param array $leaf_pages
   *   Route name → default label, from `build()` — every possible
   *   ancestor in `COLLECTION_ANCESTOR_ROUTE` is guaranteed to be a key
   *   here (either a real entity collection, or `hivelog.insights`).
   */
  protected function addCollectionAncestryLinks(Breadcrumb $breadcrumb, string $route_name, array $leaf_pages): void {
    $chain = [];
    $current = $route_name;
    while (isset(self::COLLECTION_ANCESTOR_ROUTE[$current])) {
      $current = self::COLLECTION_ANCESTOR_ROUTE[$current];
      $chain[] = $current;
    }
    foreach (array_reverse($chain) as $ancestor_route) {
      $breadcrumb->addLink(Link::createFromRoute($this->collectionCrumbLabel($ancestor_route, $leaf_pages), $ancestor_route));
    }
  }

  /**
   * A collection route's breadcrumb text: an override, or its default.
   *
   * Task 0153, ADR-0105: five steps use shorter breadcrumb-only text
   * than their real `label_collection` (unchanged everywhere else —
   * the nav strip, the page's own heading, routing.yml's own `_title`)
   * since only "Setup → Insights" came with its own explicit rename
   * instruction; every other shortened label here is breadcrumb
   * display text only.
   */
  protected function collectionCrumbLabel(string $route_name, array $leaf_pages) {
    return match ($route_name) {
      'entity.queen_observation.collection' => $this->t('Observations'),
      'entity.inventory_item.collection' => $this->t('Inventory'),
      'entity.inventory_purchase.collection' => $this->t('Purchases'),
      'entity.ai_provider_config.collection' => $this->t('AI Providers'),
      'entity.sensor_device.collection' => $this->t('Sensors'),
      default => $leaf_pages[$route_name],
    };
  }

  /**
   * The terminal crumb label for one of `TERMINAL_CRUMB_PARAM`'s routes.
   *
   * A `match()` rather than a variable-keyed array so every label stays
   * a literal `t()` call.
   */
  protected function terminalCrumbLabel(string $route_name) {
    return match ($route_name) {
      'hivelog.apiary.inventory_cost_report' => $this->t('Financial Report'),
      'hivelog.apiary.calendar_action.collection' => $this->t('Calendar'),
      'entity.hive.insights' => $this->t('Insights'),
      'entity.sensor_device.readings' => $this->t('Readings'),
      'nanoprobe.sensor_device.config' => $this->t('Download Configuration'),
      'collective.api_client.regenerate_token' => $this->t('Regenerate Token'),
    };
  }

  /**
   * Adds a terminal crumb for an edit / delete / add / bolt-on page.
   *
   * Edit and delete forms get the short fixed label "Edit" / "Delete"
   * (ADR-0102) — the preceding crumb, already a link to the entity,
   * names it, so repeating the entity's own label here would be
   * redundant. Everything else (scoped and site-wide add forms, a
   * Layout Builder override, or any other bolt-on route) gets its own
   * route title, a self-link to the current page built from the
   * route's own raw parameters — the same shape whatever those
   * parameters are (a single entity ID, or an entity plus a calendar
   * action, or none at all for a site-wide add form).
   */
  protected function addGenericTerminalCrumb(Breadcrumb $breadcrumb, RouteMatchInterface $route_match, string $route_name): void {
    if (str_ends_with($route_name, '.edit_form')) {
      $label = $this->t('Edit');
    }
    elseif (str_ends_with($route_name, '.delete_form')) {
      $label = $this->t('Delete');
    }
    else {
      $label = $this->routeTitle($route_match);
    }
    if ($label === NULL) {
      return;
    }
    $breadcrumb->addLink(Link::createFromRoute($label, $route_name, $route_match->getRawParameters()->all()));
  }

  /**
   * The current route's own title, static or resolved, or NULL.
   *
   * Every add route in this module declares a static `_title`
   * (routing.yml), read directly here per this task's own design — no
   * need for the title resolver in the common case. A route with a
   * `_title_callback` instead (a hypothetical Layout Builder override;
   * none of this module's own routes use one outside the named
   * sub-pages `TERMINAL_CRUMB_PARAM` already covers) falls back to
   * resolving it the same way core would when rendering the page title.
   */
  protected function routeTitle(RouteMatchInterface $route_match): ?string {
    $route = $route_match->getRouteObject();
    if (!$route) {
      return NULL;
    }
    $title = $route->getDefault('_title');
    if ($title) {
      return $title;
    }
    if ($route->getDefault('_title_callback')) {
      /** @var \Drupal\Core\Controller\TitleResolverInterface $title_resolver */
      $title_resolver = \Drupal::service('title_resolver');
      $title = $title_resolver->getTitle(\Drupal::request(), $route);
      return $title === NULL ? NULL : (string) $title;
    }
    return NULL;
  }

  /**
   * Adds one crumb per ancestor, root to leaf, ending with `$entity`.
   *
   * Collection-threaded types (`COLLECTION_THREADED_TYPES`) get their
   * own collection link first instead of an apiary/hive ancestor chain.
   * Every other type walks `PARENT_FIELD` from `$entity` up to whichever
   * ancestor has no parent field (or a NULL reference, which just stops
   * the walk there — a missing parent shortens the trail rather than
   * breaking it).
   *
   * Three ancestor types get an extra crumb threaded in front of them,
   * wherever they appear in the chain — not only when they're the
   * route's own subject, so e.g. an observation of an unassigned queen
   * gets the same "Queens" fallback the queen's own canonical page
   * would (task 0122):
   * - `apiary` — the root of every hivelog trail. Since it has no
   *   `PARENT_FIELD` entry of its own, it's always the first element
   *   once reached, so this reads the same as "always" rather than a
   *   fallback.
   * - `calendar_action` — its own apiary-scoped Calendar page
   *   (`hivelog.apiary.calendar_action.collection`), where a beekeeper
   *   actually opens one from, rather than jumping straight from the
   *   apiary. Applies equally to `CalendarActionItemRequirement` /
   *   `CalendarActionProductYield` trails, which already thread through
   *   `calendar_action` as an ancestor — for consistency, not because
   *   the task named those two explicitly.
   * - A `COLLECTION_FALLBACK_TYPES` type (`queen`) whose *own* walk
   *   comes up completely empty (no hive set) gets its own collection
   *   crumb as a fallback, exactly like an assigned queen gets a real
   *   Apiary › Hive ancestry.
   *
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   The breadcrumb being built.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The route's subject entity.
   * @param array $collections
   *   Entity type ID → collection route name/label pairs, from `build()`,
   *   reused here for the collection-threaded types' own collection link.
   */
  protected function addAncestryLinks(Breadcrumb $breadcrumb, FieldableEntityInterface $entity, array $collections): void {
    $type_id = $entity->getEntityTypeId();

    if (in_array($type_id, HivelogEntityHierarchy::COLLECTION_THREADED_TYPES, TRUE)) {
      $breadcrumb->addLink(Link::createFromRoute($collections["entity.$type_id.collection"], "entity.$type_id.collection"));
      $this->addEntityLink($breadcrumb, $entity);
      return;
    }

    $chain = [];
    $current = $entity;
    while ($current instanceof FieldableEntityInterface) {
      $chain[] = $current;
      $current = HivelogEntityHierarchy::resolveParent($current);
    }

    foreach (array_reverse($chain) as $ancestor) {
      $ancestor_type = $ancestor->getEntityTypeId();
      if ($ancestor_type === 'apiary') {
        $breadcrumb->addLink(Link::createFromRoute($collections['entity.apiary.collection'], 'entity.apiary.collection'));
      }
      elseif ($ancestor_type === 'calendar_action') {
        $calendar_apiary = HivelogEntityHierarchy::resolveParent($ancestor);
        if ($calendar_apiary) {
          $breadcrumb->addLink(Link::createFromRoute(
            $this->t('Calendar'),
            'hivelog.apiary.calendar_action.collection',
            ['apiary' => $calendar_apiary->id()],
          ));
        }
      }
      elseif (in_array($ancestor_type, HivelogEntityHierarchy::COLLECTION_FALLBACK_TYPES, TRUE)
        && !HivelogEntityHierarchy::resolveParent($ancestor)) {
        $breadcrumb->addLink(Link::createFromRoute($collections["entity.$ancestor_type.collection"], "entity.$ancestor_type.collection"));
      }
      $this->addEntityLink($breadcrumb, $ancestor);
    }
  }

  /**
   * Adds one crumb for a single entity.
   *
   * Its own canonical link, or `<nolink>` for types with no canonical
   * page (today's requirement / yield behaviour).
   */
  protected function addEntityLink(Breadcrumb $breadcrumb, EntityInterface $entity): void {
    $breadcrumb->addCacheableDependency($entity);
    if ($entity->hasLinkTemplate('canonical')) {
      $type_id = $entity->getEntityTypeId();
      $breadcrumb->addLink(Link::createFromRoute($entity->label(), "entity.$type_id.canonical", [$type_id => $entity->id()]));
      return;
    }
    $breadcrumb->addLink(Link::createFromRoute($entity->label(), '<nolink>'));
  }

}
