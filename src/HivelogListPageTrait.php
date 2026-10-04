<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Url;

/**
 * Shared pagination and table-building helpers for HiveLog list pages.
 *
 * Used by `HivelogListBuilder` (entity collection pages) and
 * `CalendarActionController::collection()` (a controller-built list with
 * its own filter form, task 0126) so both paginate a filtered result set
 * the same way rather than duplicating the arithmetic.
 */
trait HivelogListPageTrait {

  /**
   * Slices an already access-filtered entity list to the current page.
   *
   * Filtering must happen *before* this call, not after: paging first and
   * filtering the page afterwards would shorten pages unpredictably (a
   * page of 50 raw rows might yield only a handful the viewer can see).
   * This trades a bigger single query for correct paging — fine at
   * HiveLog's realistic list sizes (tens to low hundreds); a
   * `query_access`-based per-row condition would be the option if a list
   * ever grows large enough for that to matter.
   *
   * @param array $entities
   *   The full, already-filtered result set, keyed by entity ID.
   * @param int $limit
   *   Rows per page. `0` returns `$entities` unpaged (no limit).
   * @param int $pager_element
   *   The pager element ID, for pages with more than one pager.
   *
   * @return array
   *   The current page's slice of `$entities`, same keys preserved.
   */
  protected function paginateEntities(array $entities, int $limit, int $pager_element = 0): array {
    if (!$limit) {
      return $entities;
    }
    $page = \Drupal::service('pager.manager')
      ->createPager(count($entities), $limit, $pager_element)
      ->getCurrentPage();
    return array_slice($entities, $page * $limit, $limit, TRUE);
  }

  /**
   * Builds a `hivelog:entity-table` component render array.
   *
   * @param array $headers
   *   Column header strings.
   * @param array $rows
   *   Rows already shaped as `['cells' => [...]]`, per the component.
   * @param string $empty_message
   *   Shown in place of the table body when `$rows` is empty.
   * @param array $column_sorts
   *   Optional (task 0171): sortable columns keyed by header string, each
   *   `['url' => string, 'direction' => 'none'|'ascending'|'descending']`.
   *
   * @return array
   *   The component render array.
   */
  protected function buildEntityTable(array $headers, array $rows, string $empty_message, array $column_sorts = []): array {
    $table = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $headers),
        'rows' => $rows,
        'empty_message' => $empty_message,
      ],
    ];
    // Omitted when empty, not passed as []: an empty PHP array encodes as a
    // JSON array and would fail the component's `object` schema.
    if ($column_sorts) {
      $table['#props']['column_sorts'] = $column_sorts;
    }
    return $table;
  }

  /**
   * Builds the heading row of a list page: one right-aligned button group.
   *
   * Shared by `HivelogListBuilder::render()` and the controller-built
   * Calendar Actions page (task 0172) so both lay the heading out the same
   * way.
   *
   * @param array[] $actions
   *   Button props for `hivelog:button-group`: a label, a URL and an
   *   optional variant. Empty means no heading at all.
   *
   * @return array
   *   The heading render array, or `[]` when there are no actions.
   */
  protected function buildListHeading(array $actions): array {
    if (!$actions) {
      return [];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => -90,
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'buttons' => [
          '#type' => 'component',
          '#component' => 'hivelog:button-group',
          '#props' => ['buttons' => $actions],
        ],
      ],
      '#attached' => ['library' => ['hivelog/buttons']],
      // Which cross-links are present depends on the viewer's permissions
      // (accessibleLinkAction()), so the heading must vary by them or one
      // user's buttons could be served to another from the page cache.
      // Permission-gated routes only: link an entity-access-gated route and
      // this needs the `user` context instead.
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

  /**
   * A cross-link button for a heading, or NULL if the viewer can't open it.
   *
   * Task 0172: a link to a page the viewer is not allowed on is a dead end
   * (a 403), so cross-links are only offered when the route's own access
   * check passes. Callers `array_filter()` the result.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $label
   *   The button label.
   * @param string $route_name
   *   The target route.
   * @param string|null $variant
   *   Optional button variant (`primary`, `danger`).
   *
   * @return array|null
   *   `hivelog:button-group` button props, or NULL.
   */
  protected function accessibleLinkAction($label, string $route_name, ?string $variant = NULL): ?array {
    $url = Url::fromRoute($route_name);
    if (!$url->access()) {
      return NULL;
    }
    $action = ['label' => (string) $label, 'url' => $url->toString()];
    if ($variant !== NULL) {
      $action['variant'] = $variant;
    }
    return $action;
  }

}
