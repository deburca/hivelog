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

    // Explicitly exclude non-page hivelog.* routes that must not receive a
    // breadcrumb (e.g. file-download endpoints). Add new exclusions here
    // whenever a non-page hivelog.* route is introduced, and keep this list
    // in sync with hivelog.routing.yml (see AGENTS.md).
    // Task 0001 (queen observation CSV export) will add 'hivelog.queen.observations_csv'.
    $non_page_routes = [
      'hivelog.queen.observations_csv',
    ];
    if (in_array($route_name, $non_page_routes, TRUE)) {
      return FALSE;
    }

    return str_starts_with($route_name, 'entity.apiary.')
      || str_starts_with($route_name, 'entity.hive.')
      || str_starts_with($route_name, 'entity.hive_inspection.')
      || str_starts_with($route_name, 'entity.queen.')
      || str_starts_with($route_name, 'entity.queen_observation.')
      || str_starts_with($route_name, 'entity.calendar_action.')
      || str_starts_with($route_name, 'entity.hive_action_log.')
      // Covers hivelog.calendar_action.add and hivelog.hive_action_log.add
      // too — both are real pages (forms), not non-page endpoints.
      || str_starts_with($route_name, 'hivelog.');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    // Home > HiveLog.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('HiveLog'), 'entity.apiary.collection'));

    $route_name = $route_match->getRouteName();

    // Apiary-level routes: add the apiary crumb. On canonical pages the apiary
    // label becomes the terminal crumb (rendered as plain text by the theme);
    // on edit/delete pages it is a navigable ancestor link.
    $apiary = $route_match->getParameter('apiary');
    if ($apiary && is_object($apiary)) {
      $breadcrumb->addCacheableDependency($apiary);
      $breadcrumb->addLink(Link::createFromRoute($apiary->label(), 'entity.apiary.canonical', ['apiary' => $apiary->id()]));
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
    // crumb. Guarded to the entity's own CRUD routes (rather than just
    // checking the parameter is present) because
    // hivelog.hive_action_log.add also carries a `calendar_action` route
    // parameter — identifying which action is being logged, not "this page
    // is about a calendar action" — and must not gain a duplicate/incorrect
    // crumb from this block; the hive block below handles that route.
    $calendar_action = $route_match->getParameter('calendar_action');
    if ($calendar_action && is_object($calendar_action) && str_starts_with($route_name, 'entity.calendar_action.')) {
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

    return $breadcrumb;
  }

}
