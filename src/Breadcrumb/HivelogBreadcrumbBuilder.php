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
    return str_starts_with($route_name, 'entity.apiary.')
      || str_starts_with($route_name, 'entity.hive.')
      || str_starts_with($route_name, 'entity.hive_inspection.')
      || str_starts_with($route_name, 'entity.queen.')
      || str_starts_with($route_name, 'entity.queen_observation.')
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

    // Apiary-level routes: add the apiary link for edit/delete pages.
    $apiary = $route_match->getParameter('apiary');
    if ($apiary && is_object($apiary)) {
      $breadcrumb->addCacheableDependency($apiary);
      if ($route_name !== 'entity.apiary.canonical') {
        $breadcrumb->addLink(Link::createFromRoute($apiary->label(), 'entity.apiary.canonical', ['apiary' => $apiary->id()]));
      }
    }

    // Hive-level routes: add apiary and hive links.
    $hive = $route_match->getParameter('hive');
    if ($hive && is_object($hive)) {
      $breadcrumb->addCacheableDependency($hive);
      $hive_apiary = $hive->get('apiary')->entity;
      if ($hive_apiary) {
        $breadcrumb->addCacheableDependency($hive_apiary);
        $breadcrumb->addLink(Link::createFromRoute($hive_apiary->label(), 'entity.apiary.canonical', ['apiary' => $hive_apiary->id()]));
      }
      if ($route_name !== 'entity.hive.canonical') {
        $breadcrumb->addLink(Link::createFromRoute($hive->label(), 'entity.hive.canonical', ['hive' => $hive->id()]));
      }
    }

    // Inspection-level routes: add apiary, hive, and inspection links.
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
      if ($route_name !== 'entity.hive_inspection.canonical') {
        $breadcrumb->addLink(Link::createFromRoute($inspection->label(), 'entity.hive_inspection.canonical', ['hive_inspection' => $inspection->id()]));
      }
    }

    // Queen-level routes: when a queen has a hive, thread Apiary → Hive
    // ancestry. Queens that are unassigned (archived with no hive) just
    // render the base HiveLog breadcrumb with a trailing queen link.
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
      if ($route_name !== 'entity.queen.canonical') {
        $breadcrumb->addLink(Link::createFromRoute($queen->label(), 'entity.queen.canonical', ['queen' => $queen->id()]));
      }
    }

    // Queen observation routes: thread Apiary → Hive → Queen ancestry for
    // observations, skipping hive/apiary if the queen is unassigned.
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
      if ($route_name !== 'entity.queen_observation.canonical') {
        $breadcrumb->addLink(Link::createFromRoute($observation->label(), 'entity.queen_observation.canonical', ['queen_observation' => $observation->id()]));
      }
    }

    return $breadcrumb;
  }

}
