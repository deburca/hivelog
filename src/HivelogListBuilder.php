<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base list builder for HiveLog entity collection pages.
 *
 * `render()` builds the whole page — an optional heading with action
 * buttons, a `hivelog:entity-table` with pre-rendered Edit/Delete
 * operations, the empty-state message and a pager whenever `$limit` is
 * set (task 0126, replacing three previously-separate page shapes: eight
 * builders each duplicating their own ~60-line `render()`, five falling
 * back to core's plain `#type => table` with no heading and no pager, and
 * one controller-built page). A subclass declares only what actually
 * differs:
 * - `buildHeader()`: column header labels. The keys only matter to a list
 *   that opts into sorting via `getSortableColumns()` (task 0171), which
 *   refers to them.
 * - `buildRow(EntityInterface $entity)`: the row's *value* cells, in
 *   `buildHeader()` order — do not include an Operations cell, `render()`
 *   appends one automatically via `buildOperations()`.
 * - `getSortableColumns()` (optional, task 0171): which headers become
 *   sort links, and the stored field each sorts on.
 * - `getHeadingActions()`: Add button / cross-link button props for the
 *   list heading, or `[]` for no heading at all. Entity types with no
 *   context-free add route (Hive, HiveInspection, QueenObservation,
 *   HiveActionLog, ApiaryActionLog, CalendarAction) never get an Add
 *   button here — see AGENTS.md "Routing, controllers and forms" — but
 *   three of them (Hive, HiveInspection, QueenObservation; task 0159)
 *   still return a single "View <parent collection>" cross-link, the
 *   same kind of navigational shortcut `InventoryPurchaseListBuilder`'s
 *   "View Inventory Items" already is.
 *
 * `load()` filters rows by per-entity `access('view')` (task 0124) and
 * paginates the *filtered* set (task 0126) — in that order, so filtering
 * doesn't shorten pages the way filtering after a DB-level `LIMIT`/
 * `OFFSET` would. See `HivelogListPageTrait::paginateEntities()`.
 *
 * A subclass with a filter form (task 0132: Hive, HiveInspection,
 * QueenObservation) additionally overrides `getFilterForm()` and
 * `applyFilters()` together — both extract the same values from the
 * current request (typically via the filter form class's own static
 * `extract()`), so the rendered form's `#default_value`s and the query
 * conditions can never drift apart.
 */
abstract class HivelogListBuilder extends EntityListBuilder {

  use HivelogListPageTrait;

  /**
   * The current user, used to filter load() by per-row view access.
   */
  protected AccountInterface $currentUser;

  /**
   * The renderer, used to pre-render each row's Operations cell.
   */
  protected RendererInterface $renderer;

  /**
   * The form builder, used to render a subclass's filter form, if any.
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The request stack, passed to a subclass's filter extraction.
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   *
   * Subclasses that override createInstance() to inject additional
   * services must still set $instance->currentUser, ->renderer,
   * ->formBuilder and ->requestStack themselves.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->currentUser = $container->get('current_user');
    $instance->renderer = $container->get('renderer');
    $instance->formBuilder = $container->get('form_builder');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Loads every entity the query matches (no DB-level pager), filters to
   * the ones the current user may view, then slices to the current page.
   */
  public function load() {
    $query = $this->getStorage()->getQuery()->accessCheck(TRUE);
    // Task 0171: a requested sort goes first; the default key always stays
    // as the tie-breaker so equal values (and NULLs) page deterministically.
    $sort = $this->currentSort();
    if ($sort) {
      $query->sort($sort['field'], strtoupper($sort['direction']));
    }
    $query->sort($this->entityType->getKey(static::SORT_KEY));
    $this->applyFilters($query);
    $ids = $query->execute();
    $entities = $ids ? $this->getStorage()->loadMultiple($ids) : [];
    $accessible = array_filter($entities, fn(EntityInterface $entity) => $entity->access('view', $this->currentUser));
    return $this->paginateEntities($accessible, (int) $this->limit);
  }

  /**
   * The columns a visitor may sort this list by (task 0171).
   *
   * Opt-in: the default is none. Keys are `buildHeader()` keys; values are
   * what each sorts on: a base field of the listed entity, or — for a
   * reference column such as Apiary — a relationship path to the related
   * record's label (`apiary.entity.name`), which the entity query joins
   * (LEFT, so a record whose reference has gone is still listed, just at the
   * NULL end). Never a computed value, and never an ordinal enum whose
   * alphabetical order would mislead, e.g. health. The `sort` query
   * parameter is only ever matched against these keys, so nothing a visitor
   * sends reaches the query as a field name or path.
   *
   * @return array<string, string>
   *   Header key => field name or relationship path.
   */
  protected function getSortableColumns(): array {
    return [];
  }

  /**
   * The sort requested in the current query string, or NULL for the default.
   *
   * An unknown, non-sortable or non-string `sort` falls back to the default
   * order, never an error; `order` is `desc` only if it says so.
   *
   * @return array{key: string, field: string, direction: string}|null
   *   The header key, the field to sort on and `asc`|`desc`.
   */
  protected function currentSort(): ?array {
    $columns = $this->getSortableColumns();
    $request = $this->requestStack->getCurrentRequest();
    if (!$columns || !$request) {
      return NULL;
    }
    $query = $request->query->all();
    $key = is_string($query['sort'] ?? NULL) ? $query['sort'] : '';
    if ($key === '' || !isset($columns[$key])) {
      return NULL;
    }
    $order = is_string($query['order'] ?? NULL) ? strtolower($query['order']) : 'asc';
    return [
      'key' => $key,
      'field' => $columns[$key],
      'direction' => $order === 'desc' ? 'desc' : 'asc',
    ];
  }

  /**
   * Builds the entity-table component's `column_sorts` prop.
   *
   * Each sortable column links to its own *next* sort — ascending first,
   * then toggling — on the current page's other query parameters (filters
   * included) but without `page`, since a different order makes the old
   * page number meaningless.
   *
   * @param array<string, \Drupal\Core\StringTranslation\TranslatableMarkup|string> $header
   *   The `buildHeader()` result.
   * @param array|null $current
   *   The `currentSort()` result.
   *
   * @return array<string, array{url: string, direction: string}>
   *   Keyed by header string; empty when nothing is sortable.
   */
  protected function buildColumnSorts(array $header, ?array $current): array {
    $request = $this->requestStack->getCurrentRequest();
    $columns = $this->getSortableColumns();
    if (!$columns || !$request) {
      return [];
    }
    $base = $request->query->all();
    unset($base['page'], $base['sort'], $base['order']);

    $sorts = [];
    foreach ($header as $key => $label) {
      if (!isset($columns[$key])) {
        continue;
      }
      $is_current = $current && $current['key'] === $key;
      $next = $is_current && $current['direction'] === 'asc' ? 'desc' : 'asc';
      $sorts[(string) $label] = [
        'url' => Url::fromRoute('<current>', [], ['query' => $base + ['sort' => $key, 'order' => $next]])->toString(),
        'direction' => $is_current ? ($current['direction'] === 'asc' ? 'ascending' : 'descending') : 'none',
      ];
    }
    return $sorts;
  }

  /**
   * The rendered filter form for this list, or `[]` for none.
   *
   * Default: no filter form. A subclass with one typically returns
   * `$this->formBuilder->getForm(SomeFilterForm::class)`.
   */
  protected function getFilterForm(): array {
    return [];
  }

  /**
   * Adds this list's active filter conditions to `$query`, if any.
   *
   * Default: no-op. A subclass with a filter form overrides this,
   * typically delegating to that form class's own static `apply()` with
   * values from its own static `extract()`.
   */
  protected function applyFilters(QueryInterface $query): void {}

  /**
   * Whether any filter is currently active, for the empty-state message.
   *
   * Default: `FALSE`. A subclass with a filter form overrides this to
   * match whatever `applyFilters()` extracted.
   */
  protected function hasActiveFilters(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * A hivelog:button-group cluster in place of core's dropbutton.
   */
  public function buildOperations(EntityInterface $entity) {
    $buttons = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Edit'),
        'url' => $entity->toUrl('edit-form')->toString(),
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#attached' => ['library' => ['hivelog/buttons']],
    ];
  }

  /**
   * Add button / cross-link button props for the list heading.
   *
   * Each entry is a `hivelog:button-group` button prop
   * (`label`/`url`/optional `variant`). Return `[]` for no heading.
   *
   * @return array
   *   Button props, or an empty array.
   */
  protected function getHeadingActions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $headers = array_map('strval', array_values($this->buildHeader()));

    $rows = [];
    foreach ($this->load() as $entity) {
      $cells = $this->buildRow($entity);
      if (!$cells) {
        continue;
      }
      $operations = $this->buildOperations($entity);
      $cells[] = (string) $this->renderer->renderInIsolation($operations);
      $rows[] = ['cells' => array_values($cells)];
    }

    $build = [];

    $heading_actions = $this->getHeadingActions();
    if ($heading_actions) {
      $build['heading'] = $this->buildListHeading($heading_actions);
    }

    $filter_form = $this->getFilterForm();
    $cache_contexts = $this->entityType->getListCacheContexts();
    $sort = $this->currentSort();
    $column_sorts = $this->buildColumnSorts($this->buildHeader(), $sort);
    if ($column_sorts) {
      $cache_contexts[] = 'url.query_args';
    }
    if ($filter_form) {
      // Task 0171: a GET form replaces the whole query string on submit, so
      // the active sort has to ride along as hidden inputs or filtering
      // would silently reset it. Reset (a link to the bare route) still
      // clears both, by design.
      if ($sort) {
        foreach (['sort' => $sort['key'], 'order' => $sort['direction']] as $name => $value) {
          $filter_form['sort_state_' . $name] = [
            '#type' => 'html_tag',
            '#tag' => 'input',
            '#attributes' => ['type' => 'hidden', 'name' => $name, 'value' => $value],
          ];
        }
      }
      $build['filter'] = $filter_form + ['#weight' => -80];
      // The table's own cache varies by the filter query string too, not
      // just the entity type's own default contexts — the filter form
      // already declares this on itself, but the table is a sibling
      // render element, not a parent/child of the form.
      $cache_contexts[] = 'url.query_args';
    }

    $build['table'] = $this->buildEntityTable(
      $headers,
      $rows,
      $this->hasActiveFilters()
        ? (string) $this->t('No @label match the current filters.', ['@label' => $this->entityType->getPluralLabel()])
        : (string) $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
      $column_sorts
    );
    $build['table']['#cache'] = [
      'contexts' => $cache_contexts,
      'tags' => $this->entityType->getListCacheTags(),
    ];

    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
        '#weight' => 10,
      ];
    }

    return $build;
  }

}
