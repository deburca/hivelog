<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * The HiveLog dashboard — the module's landing page at /hivelog.
 *
 * See ADR-0057 (dashboard landing page & /hivelog information architecture)
 * and task 0056. This is the criterion-1 skeleton: the route, controller,
 * menu link and breadcrumb root are wired up and the apiary collection has
 * moved to /hivelog/apiaries. The dashboard widgets themselves (the
 * needs-attention queue, stat tiles, upcoming, recent activity and the
 * apiaries summary) are built in criteria 2–8.
 */
class DashboardController extends ControllerBase {

  /**
   * Renders the dashboard landing page.
   *
   * @return array
   *   A render array.
   */
  public function view(): array {
    $build = [];

    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('The HiveLog dashboard is being built.'),
    ];

    $build['apiaries_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Go to Apiaries'),
      '#url' => Url::fromRoute('entity.apiary.collection'),
    ];

    // The real dashboard varies per user (it will roll up only the apiaries
    // and hives the viewer can see) and reuses the existing landing-page
    // permission OR-set; declare the context now so criteria 2–8 only add
    // cache tags and dependencies on top of it.
    $build['#cache']['contexts'] = ['user.permissions'];

    return $build;
  }

}
