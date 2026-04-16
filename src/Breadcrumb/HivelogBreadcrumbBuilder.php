<?php

namespace Drupal\hivelog\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
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
  public function applies(RouteMatchInterface $route_match): bool {
    $route_name = $route_match->getRouteName();
    return str_starts_with($route_name, 'entity.apiary.')
      || str_starts_with($route_name, 'entity.hive.')
      || str_starts_with($route_name, 'entity.hive_inspection.')
      || str_starts_with($route_name, 'hivelog.');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    // Home > Structure > HiveLog.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Structure'), 'system.admin_structure'));
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

    return $breadcrumb;
  }

}
