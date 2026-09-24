<?php

declare(strict_types=1);

namespace Drupal\hivelog;

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
   *
   * @return array
   *   The component render array.
   */
  protected function buildEntityTable(array $headers, array $rows, string $empty_message): array {
    return [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $headers),
        'rows' => $rows,
        'empty_message' => $empty_message,
      ],
    ];
  }

}
