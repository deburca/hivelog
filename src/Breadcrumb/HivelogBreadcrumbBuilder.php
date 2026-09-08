<?php

namespace Drupal\hivelog\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides breadcrumbs for hivelog entity routes.
 */
class HivelogBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a HivelogBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

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
    // (ADR-0057); on the dashboard route itself it is the terminal crumb,
    // which the theme renders as plain text.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('HiveLog'), 'hivelog.dashboard'));

    // Collection listings and the cross-apiary report page carry no
    // entity-ancestor chain: "HiveLog" points at the dashboard (ADR-0057),
    // so each needs its own terminal crumb — the page's own name, a
    // self-link the theme renders as plain text. Every hivelog list page
    // gets the same "Home › HiveLog › <Name>" shape this way, matching
    // what the menu breadcrumb gives the collections that are not routed
    // through this builder.
    $collections = [
      'entity.apiary.collection' => $this->t('Apiaries'),
      'entity.hive.collection' => $this->t('Hives'),
      'entity.hive_inspection.collection' => $this->t('Inspections'),
      'entity.queen.collection' => $this->t('Queens'),
      'entity.queen_observation.collection' => $this->t('Queen Observations'),
      'entity.calendar_action.collection' => $this->t('Calendar Actions'),
      'entity.hive_action_log.collection' => $this->t('Hive Action Logs'),
      'entity.apiary_action_log.collection' => $this->t('Apiary Action Logs'),
      'entity.inventory_item.collection' => $this->t('Inventory Items'),
      'entity.inventory_purchase.collection' => $this->t('Inventory Purchases'),
      'entity.product.collection' => $this->t('Products'),
    ];

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
    // path) hang off their collection: Home › HiveLog › <Plural>.
    if (preg_match('/^entity\.([a-z_]+)\.add_form$/', $route_name, $m)
      && isset($collections["entity.{$m[1]}.collection"])) {
      $breadcrumb->addLink(Link::createFromRoute(
        $collections["entity.{$m[1]}.collection"],
        "entity.{$m[1]}.collection",
      ));
      return $breadcrumb;
    }

    // Apiary-level routes: add the apiary crumb. On canonical pages the apiary
    // label becomes the terminal crumb (rendered as plain text by the theme);
    // on edit/delete pages it is a navigable ancestor link.
    $apiary = $route_match->getParameter('apiary');
    if ($apiary && is_object($apiary)) {
      $breadcrumb->addCacheableDependency($apiary);
      $breadcrumb->addLink(Link::createFromRoute($apiary->label(), 'entity.apiary.canonical', ['apiary' => $apiary->id()]));

      // Apiary-scoped pages that are not the canonical page itself add a
      // terminal crumb naming the page, so the trail ends with the page's
      // own name rather than a linked apiary label.
      $apiary_page_crumbs = [
        'hivelog.apiary.inventory_cost_report' => $this->t('Financial Report'),
        'hivelog.apiary.calendar_action.collection' => $this->t('Calendar'),
      ];
      if (isset($apiary_page_crumbs[$route_name])) {
        $breadcrumb->addLink(Link::createFromRoute($apiary_page_crumbs[$route_name], $route_name, ['apiary' => $apiary->id()]));
        return $breadcrumb;
      }
    }

    // Hive-level routes: add apiary ancestor link then hive crumb.
    $hive = $route_match->getParameter('hive');
    if ($hive && is_object($hive)) {
      $breadcrumb->addCacheableDependency($hive);
      $hive_apiary = $hive->get('apiary')->entity;
      if ($hive_apiary) {
        $breadcrumb->addCacheableDependency($hive_apiary);
        $breadcrumb->addLink(Link::createFromRoute($hive_apiary->label(), 'entity.apiary.canonical', ['apiary' => $hive_apiary->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($hive->label(), 'entity.hive.canonical', ['hive' => $hive->id()]));
    }

    // Inspection-level routes: add apiary and hive ancestor links then
    // inspection crumb.
    $inspection = $route_match->getParameter('hive_inspection');
    if ($inspection && is_object($inspection)) {
      $breadcrumb->addCacheableDependency($inspection);
      $inspection_hive = $inspection->get('hive')->entity;
      if ($inspection_hive) {
        $breadcrumb->addCacheableDependency($inspection_hive);
        $inspection_apiary = $inspection_hive->get('apiary')->entity;
        if ($inspection_apiary) {
          $breadcrumb->addCacheableDependency($inspection_apiary);
          $breadcrumb->addLink(Link::createFromRoute($inspection_apiary->label(), 'entity.apiary.canonical', ['apiary' => $inspection_apiary->id()]));
        }
        $breadcrumb->addLink(Link::createFromRoute($inspection_hive->label(), 'entity.hive.canonical', ['hive' => $inspection_hive->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($inspection->label(), 'entity.hive_inspection.canonical', ['hive_inspection' => $inspection->id()]));
    }

    // Queen-level routes: thread Apiary → Hive ancestry when the queen has a
    // hive; unassigned queens get just the base trail plus queen crumb.
    $queen = $route_match->getParameter('queen');
    if ($queen && is_object($queen)) {
      $breadcrumb->addCacheableDependency($queen);
      $queen_hive = $queen->get('hive')->entity;
      if ($queen_hive) {
        $breadcrumb->addCacheableDependency($queen_hive);
        $queen_apiary = $queen_hive->get('apiary')->entity;
        if ($queen_apiary) {
          $breadcrumb->addCacheableDependency($queen_apiary);
          $breadcrumb->addLink(Link::createFromRoute($queen_apiary->label(), 'entity.apiary.canonical', ['apiary' => $queen_apiary->id()]));
        }
        $breadcrumb->addLink(Link::createFromRoute($queen_hive->label(), 'entity.hive.canonical', ['hive' => $queen_hive->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($queen->label(), 'entity.queen.canonical', ['queen' => $queen->id()]));
    }

    // Queen observation routes: thread Apiary → Hive → Queen ancestry then
    // observation crumb.
    $observation = $route_match->getParameter('queen_observation');
    if ($observation && is_object($observation)) {
      $breadcrumb->addCacheableDependency($observation);
      $observation_queen = $observation->get('queen')->entity;
      if ($observation_queen) {
        $breadcrumb->addCacheableDependency($observation_queen);
        $observation_hive = $observation_queen->get('hive')->entity;
        if ($observation_hive) {
          $breadcrumb->addCacheableDependency($observation_hive);
          $observation_apiary = $observation_hive->get('apiary')->entity;
          if ($observation_apiary) {
            $breadcrumb->addCacheableDependency($observation_apiary);
            $breadcrumb->addLink(Link::createFromRoute($observation_apiary->label(), 'entity.apiary.canonical', ['apiary' => $observation_apiary->id()]));
          }
          $breadcrumb->addLink(Link::createFromRoute($observation_hive->label(), 'entity.hive.canonical', ['hive' => $observation_hive->id()]));
        }
        $breadcrumb->addLink(Link::createFromRoute($observation_queen->label(), 'entity.queen.canonical', ['queen' => $observation_queen->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($observation->label(), 'entity.queen_observation.canonical', ['queen_observation' => $observation->id()]));
    }

    // Calendar action routes: add apiary ancestor link then calendar action
    // crumb. Fires wherever a `calendar_action` route parameter identifies
    // the page's subject — its own CRUD + Layout Builder routes, and the
    // requirement / yield "add" forms nested under it — but NOT the
    // hive/apiary action-log "add" routes, which carry `calendar_action`
    // only to say which action is being logged (they thread via the
    // hive / apiary instead).
    $calendar_action = $route_match->getParameter('calendar_action');
    if ($calendar_action && is_object($calendar_action)
      && !in_array($route_name, ['hivelog.hive_action_log.add', 'hivelog.apiary_action_log.add'], TRUE)) {
      $breadcrumb->addCacheableDependency($calendar_action);
      $calendar_action_apiary = $calendar_action->get('apiary')->entity;
      if ($calendar_action_apiary) {
        $breadcrumb->addCacheableDependency($calendar_action_apiary);
        $breadcrumb->addLink(Link::createFromRoute($calendar_action_apiary->label(), 'entity.apiary.canonical', ['apiary' => $calendar_action_apiary->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($calendar_action->label(), 'entity.calendar_action.canonical', ['calendar_action' => $calendar_action->id()]));
    }

    // Hive action log routes: thread Apiary → Hive ancestry then log crumb.
    $hive_action_log = $route_match->getParameter('hive_action_log');
    if ($hive_action_log && is_object($hive_action_log)) {
      $breadcrumb->addCacheableDependency($hive_action_log);
      $log_hive = $hive_action_log->get('hive')->entity;
      if ($log_hive) {
        $breadcrumb->addCacheableDependency($log_hive);
        $log_apiary = $log_hive->get('apiary')->entity;
        if ($log_apiary) {
          $breadcrumb->addCacheableDependency($log_apiary);
          $breadcrumb->addLink(Link::createFromRoute($log_apiary->label(), 'entity.apiary.canonical', ['apiary' => $log_apiary->id()]));
        }
        $breadcrumb->addLink(Link::createFromRoute($log_hive->label(), 'entity.hive.canonical', ['hive' => $log_hive->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($hive_action_log->label(), 'entity.hive_action_log.canonical', ['hive_action_log' => $hive_action_log->id()]));
    }

    // Apiary action log routes: add apiary ancestor link then log crumb.
    // Simpler than the hive action log block above — apiary_action_log
    // references its apiary directly, no hive level to traverse.
    $apiary_action_log = $route_match->getParameter('apiary_action_log');
    if ($apiary_action_log && is_object($apiary_action_log)) {
      $breadcrumb->addCacheableDependency($apiary_action_log);
      $log_apiary = $apiary_action_log->get('apiary')->entity;
      if ($log_apiary) {
        $breadcrumb->addCacheableDependency($log_apiary);
        $breadcrumb->addLink(Link::createFromRoute($log_apiary->label(), 'entity.apiary.canonical', ['apiary' => $log_apiary->id()]));
      }
      $breadcrumb->addLink(Link::createFromRoute($apiary_action_log->label(), 'entity.apiary_action_log.canonical', ['apiary_action_log' => $apiary_action_log->id()]));
    }

    // Inventory item / inventory purchase / product routes — each
    // references its apiary directly (like apiary_action_log). Covers the
    // canonical / edit / delete / Layout Builder routes for all three.
    foreach ([
      'inventory_item' => 'entity.inventory_item.canonical',
      'inventory_purchase' => 'entity.inventory_purchase.canonical',
      'product' => 'entity.product.canonical',
    ] as $param => $canonical_route) {
      $entity = $route_match->getParameter($param);
      if ($entity && is_object($entity)) {
        $breadcrumb->addCacheableDependency($entity);
        $entity_apiary = $entity->get('apiary')->entity;
        if ($entity_apiary) {
          $breadcrumb->addCacheableDependency($entity_apiary);
          $breadcrumb->addLink(Link::createFromRoute($entity_apiary->label(), 'entity.apiary.canonical', ['apiary' => $entity_apiary->id()]));
        }
        $breadcrumb->addLink(Link::createFromRoute($entity->label(), $canonical_route, [$param => $entity->id()]));
      }
    }

    // Calendar-action requirement / yield edit + delete routes: thread
    // Apiary → Calendar action, then a non-linked terminal (these
    // sub-entities have no canonical page of their own).
    foreach (['calendar_action_item_requirement', 'calendar_action_product_yield'] as $param) {
      $sub = $route_match->getParameter($param);
      if ($sub && is_object($sub)) {
        $breadcrumb->addCacheableDependency($sub);
        $sub_action = $sub->get('calendar_action')->entity;
        if ($sub_action) {
          $breadcrumb->addCacheableDependency($sub_action);
          $sub_apiary = $sub_action->get('apiary')->entity;
          if ($sub_apiary) {
            $breadcrumb->addCacheableDependency($sub_apiary);
            $breadcrumb->addLink(Link::createFromRoute($sub_apiary->label(), 'entity.apiary.canonical', ['apiary' => $sub_apiary->id()]));
          }
          $breadcrumb->addLink(Link::createFromRoute($sub_action->label(), 'entity.calendar_action.canonical', ['calendar_action' => $sub_action->id()]));
        }
        $breadcrumb->addLink(Link::createFromRoute($sub->label(), '<nolink>'));
      }
    }

    return $breadcrumb;
  }

}
