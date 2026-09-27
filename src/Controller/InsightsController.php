<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hivelog\HivelogAppNavBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Insights" landing page — the parent hub for site-configuration items.
 *
 * Task 0146 / ADR-0104 (as "Setup"), renamed by task 0152 / ADR-0105: a
 * primary nav item with no existing entity page of its own to point
 * at, unlike "Apiaries" — unassigned this route would leave the nav
 * strip's Insights hub with nothing to link to. Lists whichever
 * `parent: 'insights'` nav items (`HivelogAppNavBuilder::getAccessibleChildren()`)
 * the current user can access; the route's own access is gated by
 * `InsightsPageAccessCheck` on the same query, so this never renders
 * an empty page for a user who was let in.
 */
class InsightsController extends ControllerBase {

  public function __construct(
    protected HivelogAppNavBuilder $appNavBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    // `new self()`, not `new static()` — this class is not expected to
    // be subclassed (see `HivelogMenuLinks::create()`'s own precedent).
    return new self($container->get('hivelog.app_nav_builder'));
  }

  /**
   * Page title.
   */
  public function title(): TranslatableMarkup {
    return $this->t('Insights');
  }

  /**
   * Builds the page.
   *
   * @return array
   *   A render array.
   */
  public function view(): array {
    $children = $this->appNavBuilder->getAccessibleChildren('insights');
    if (!$children) {
      // Defensive only — `InsightsPageAccessCheck` already denies
      // access to the route itself once this list is empty, so a real
      // request should never reach this branch.
      return [
        '#markup' => $this->t('There is nothing here yet.'),
        '#cache' => ['contexts' => ['user.permissions']],
      ];
    }

    $items = [];
    foreach ($children as $item) {
      $items[] = [
        '#type' => 'link',
        '#title' => $item['title'],
        '#url' => $item['url'],
        '#attributes' => ['class' => ['hivelog-insights-page__link']],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
      '#attributes' => ['class' => ['hivelog-insights-page__list']],
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

}
