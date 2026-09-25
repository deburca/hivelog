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
 * `resolveSubject()`), then `addAncestryLinks()` walks the map from the
 * subject up to the root, adding one crumb per ancestor in root-to-leaf
 * order. A missing reference (a deleted apiary, an unassigned queen)
 * just stops the walk there, shortening the trail — the same behaviour
 * the previous per-type blocks had.
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
 */
class HivelogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Route parameter names to check for the route's subject entity.
   *
   * Order does not affect the result for any current route — at most one
   * of these ever upcasts to an object on a given route, except the two
   * action-log "add" routes below, which are resolved explicitly first.
   */
  protected const SUBJECT_PARAMS = [
    'apiary',
    'hive',
    'hive_inspection',
    'queen',
    'queen_observation',
    'calendar_action',
    'hive_action_log',
    'apiary_action_log',
    'inventory_item',
    'inventory_purchase',
    'product',
    'sensor_device',
    'ai_provider_config',
    'api_client',
    'calendar_action_item_requirement',
    'calendar_action_product_yield',
  ];

  /**
   * Routes where {calendar_action} is present but is not the subject.
   *
   * `hivelog.hive_action_log.add` and `hivelog.apiary_action_log.add`
   * carry `{calendar_action}` only to say which action is being logged;
   * the trail threads via `{hive}` / `{apiary}` instead.
   */
  protected const CALENDAR_ACTION_NOT_SUBJECT_ROUTES = [
    'hivelog.hive_action_log.add',
    'hivelog.apiary_action_log.add',
  ];

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
    $breadcrumb->addCacheContexts(['route']);
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

    // Collection listings + the combined report: their own name is the
    // terminal crumb (a self-link the theme renders as plain text).
    $leaf_pages = $collections + [
      'hivelog.apiaries.financial_report' => $this->t('Financial Report: All Apiaries'),
    ];
    if (isset($leaf_pages[$route_name])) {
      $breadcrumb->addLink(Link::createFromRoute($leaf_pages[$route_name], $route_name));
      return $breadcrumb;
    }

    // Site-wide "add" forms (entity.<type>.add_form, no parent in the
    // path) hang off their collection: Home › HiveLog › <Plural> › Add
    // <Type>.
    if (preg_match('/^entity\.([a-z_]+)\.add_form$/', $route_name, $m)
      && isset($collections["entity.{$m[1]}.collection"])) {
      $breadcrumb->addLink(Link::createFromRoute(
        $collections["entity.{$m[1]}.collection"],
        "entity.{$m[1]}.collection",
      ));
      $this->addGenericTerminalCrumb($breadcrumb, $route_match, $route_name);
      return $breadcrumb;
    }

    $subject = $this->resolveSubject($route_match, $route_name);
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
   * Picks the route's subject entity — the one the trail is built from.
   *
   * At most one of `SUBJECT_PARAMS` ever upcasts to an object on a given
   * route, except the two routes in
   * `CALENDAR_ACTION_NOT_SUBJECT_ROUTES`, which also carry
   * `{calendar_action}` — excluded there explicitly rather than by
   * checking which other params are present, so the rule stays anchored
   * to specific routes instead of a coincidence of parameter names.
   */
  protected function resolveSubject(RouteMatchInterface $route_match, string $route_name): ?FieldableEntityInterface {
    foreach (self::SUBJECT_PARAMS as $param) {
      if ($param === 'calendar_action' && in_array($route_name, self::CALENDAR_ACTION_NOT_SUBJECT_ROUTES, TRUE)) {
        continue;
      }
      $value = $route_match->getParameter($param);
      if ($value instanceof FieldableEntityInterface) {
        return $value;
      }
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
