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
 * - `buildHeader()`: column header labels, keyed however is convenient
 *   (unused beyond `array_values()`).
 * - `buildRow(EntityInterface $entity)`: the row's *value* cells, in
 *   `buildHeader()` order — do not include an Operations cell, `render()`
 *   appends one automatically via `buildOperations()`.
 * - `getHeadingActions()`: Add button / cross-link button props for the
 *   list heading, or `[]` for no heading at all (entity types with no
 *   context-free add route — Hive, HiveInspection, QueenObservation,
 *   HiveActionLog, ApiaryActionLog, CalendarAction — return `[]`; see
 *   AGENTS.md "Routing, controllers and forms").
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
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort($this->entityType->getKey(static::SORT_KEY));
    $this->applyFilters($query);
    $ids = $query->execute();
    $entities = $ids ? $this->getStorage()->loadMultiple($ids) : [];
    $accessible = array_filter($entities, fn(EntityInterface $entity) => $entity->access('view', $this->currentUser));
    return $this->paginateEntities($accessible, (int) $this->limit);
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
      $build['heading'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        '#weight' => -90,
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['hivelog-list-heading__action']],
          'buttons' => [
            '#type' => 'component',
            '#component' => 'hivelog:button-group',
            '#props' => ['buttons' => $heading_actions],
          ],
        ],
        '#attached' => ['library' => ['hivelog/buttons']],
      ];
    }

    $filter_form = $this->getFilterForm();
    $cache_contexts = $this->entityType->getListCacheContexts();
    if ($filter_form) {
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
        : (string) $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()])
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
