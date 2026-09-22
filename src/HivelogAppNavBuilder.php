<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Builds `hivelog`'s theme-independent in-app secondary navigation.
 *
 * Rendered via `hook_preprocess_page()` (task 0105), not a placed
 * block or `hook_page_top()` — see that hook's own docblock in
 * `hivelog.module` for why. Combines `hivelog` core's own 8 built-in
 * destinations (the same set `hivelog.links.menu.yml` already lists)
 * with every `hook_hivelog_app_nav_items()` contribution (see
 * `hivelog.api.php`), so `nanoprobe`/`collective`/`nexus` can add their
 * own admin pages without `hivelog` depending on them.
 */
class HivelogAppNavBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
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
    $items = $this->builtInItems();
    foreach ($this->moduleHandler->invokeAll('hivelog_app_nav_items') as $key => $item) {
      $items[$key] = $item;
    }

    $accessible = array_filter($items, fn(array $item) => $item['url']->access());
    if (!$accessible) {
      return [];
    }

    uasort($accessible, fn(array $a, array $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    $links = [];
    foreach ($accessible as $key => $item) {
      $links[$key] = [
        '#type' => 'link',
        '#title' => $item['title'],
        '#url' => $item['url'],
        '#attributes' => ['class' => ['hivelog-app-nav__link']],
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
        'contexts' => ['url.path', 'user.permissions'],
      ],
    ] + $links;
  }

  /**
   * Hivelog core's own built-in nav items.
   *
   * Same 8 destinations, same weights, as `hivelog.links.menu.yml` —
   * kept in sync deliberately, not derived from it, since the menu
   * links stay as a separate, best-effort integration rather than
   * this nav's data source (see task 0105's own notes).
   *
   * @return array[]
   *   `['title' => TranslatableMarkup, 'url' => Url, 'weight' => int]`
   *   descriptors, keyed uniquely.
   */
  protected function builtInItems(): array {
    return [
      'apiaries' => [
        'title' => $this->t('Apiaries'),
        'url' => Url::fromRoute('entity.apiary.collection'),
        'weight' => 0,
      ],
      'hives' => [
        'title' => $this->t('Hives'),
        'url' => Url::fromRoute('entity.hive.collection'),
        'weight' => 1,
      ],
      'inspections' => [
        'title' => $this->t('Inspections'),
        'url' => Url::fromRoute('entity.hive_inspection.collection'),
        'weight' => 2,
      ],
      'queens' => [
        'title' => $this->t('Queens'),
        'url' => Url::fromRoute('entity.queen.collection'),
        'weight' => 3,
      ],
      'queen_observations' => [
        'title' => $this->t('Queen Observations'),
        'url' => Url::fromRoute('entity.queen_observation.collection'),
        'weight' => 4,
      ],
      'inventory_items' => [
        'title' => $this->t('Inventory Items'),
        'url' => Url::fromRoute('entity.inventory_item.collection'),
        'weight' => 5,
      ],
      'inventory_purchases' => [
        'title' => $this->t('Inventory Purchases'),
        'url' => Url::fromRoute('entity.inventory_purchase.collection'),
        'weight' => 6,
      ],
      'products' => [
        'title' => $this->t('Products'),
        'url' => Url::fromRoute('entity.product.collection'),
        'weight' => 7,
      ],
    ];
  }

}
