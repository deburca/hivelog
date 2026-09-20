<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\CalendarAction;
use Drupal\hivelog\Form\HivelogCalendarActionsFilterForm;
use Drupal\hivelog\Utility\SimpleBulletText;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for Calendar Action pages.
 */
class CalendarActionController extends ControllerBase {

  /**
   * Default number of calendar actions shown per page on the collection page.
   *
   * Matches core EntityListBuilder's own default, since this controller
   * replaces that default list builder on the route (task 0073).
   */
  public const CALENDAR_ACTIONS_PER_PAGE = 50;

  /**
   * Pager element id for the collection page's table.
   *
   * A standalone page — element 0 is safe to reuse, there is only ever
   * one paginated list here.
   */
  protected const CALENDAR_ACTIONS_PAGER_ELEMENT = 0;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a CalendarActionController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    RendererInterface $renderer,
    FormBuilderInterface $form_builder,
    RequestStack $request_stack,
  ) {
    // $entityTypeManager / $entityFormBuilder / $formBuilder are untyped
    // ControllerBase properties; assign them rather than redeclaring them
    // with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->renderer = $renderer;
    $this->formBuilder = $form_builder;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer'),
      $container->get('form_builder'),
      $container->get('request_stack'),
    );
  }

  /**
   * Lists every calendar action visible to the user, across all apiaries.
   *
   * Global companion to ApiaryController::fullCalendar() — filterable by
   * week range so a beekeeper can find open/upcoming work without paging
   * through every apiary's calendar, and so the dashboard's "Open seasonal
   * tasks" stat tile can link straight to "the rest of the year from this
   * week" (task 0073). Keeps CalendarActionListBuilder's original default
   * of showing every row regardless of the `enabled` flag — this is the
   * page a beekeeper uses to find and re-enable a disabled action.
   */
  public function collection(): array {
    $build = [];

    $build['filter'] = $this->formBuilder->getForm(HivelogCalendarActionsFilterForm::class);
    $build['filter']['#weight'] = 0;

    $filters = $this->extractCollectionFilters();
    $query = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('week_start', 'ASC')
      ->pager(static::CALENDAR_ACTIONS_PER_PAGE, static::CALENDAR_ACTIONS_PAGER_ELEMENT);
    $this->applyCollectionFilters($query, $filters);
    $calendar_action_ids = $query->execute();

    $calendar_actions = $calendar_action_ids
      ? $this->entityTypeManager->getStorage('calendar_action')->loadMultiple($calendar_action_ids)
      : [];
    $calendar_actions = array_filter(
      $calendar_actions,
      fn($calendar_action) => $calendar_action->access('view')
    );

    $scope_labels = [
      'hive' => $this->t('Hive'),
      'apiary' => $this->t('Apiary'),
    ];

    $header = [
      $this->t('Title'),
      $this->t('Apiary'),
      $this->t('Scope'),
      $this->t('Category'),
      $this->t('Week(s)'),
      $this->t('Enabled'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($calendar_actions as $calendar_action) {
      $apiary = $calendar_action->get('apiary')->entity;

      $week_start = $calendar_action->get('week_start')->value;
      $week_end = $calendar_action->get('week_end')->value;
      $weeks = ($week_end !== NULL && $week_end !== '' && (int) $week_end !== (int) $week_start)
        ? $this->t('@start–@end', ['@start' => $week_start, '@end' => $week_end])
        : (string) $week_start;

      $scope = $calendar_action->get('scope')->value;
      $scope_display = (string) ($scope_labels[$scope] ?? $scope);

      $category = $calendar_action->get('category')->value;
      $category_label = $category
        ? ($calendar_action->get('category')->getSetting('allowed_values')[$category] ?? $category)
        : '';

      $enabled_display = $calendar_action->get('enabled')->value ? (string) $this->t('Yes') : (string) $this->t('Disabled');

      $buttons = [];
      if ($calendar_action->access('update')) {
        $buttons[] = [
          'label' => (string) $this->t('Edit'),
          'url' => $calendar_action->toUrl('edit-form')->toString(),
        ];
      }
      if ($calendar_action->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $calendar_action->toUrl('delete-form')->toString(),
          'variant' => 'danger',
        ];
      }
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ];

      $rows[] = [
        'cells' => [
          $calendar_action->toLink()->toString(),
          $apiary ? $apiary->toLink()->toString() : '',
          $scope_display,
          (string) $category_label,
          (string) $weeks,
          $enabled_display,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $header),
        'rows' => $rows,
        'empty_message' => (string) (!empty($filters)
          ? $this->t('No calendar actions match the current filters.')
          : $this->t('No calendar actions have been added yet.')),
      ],
      '#weight' => 1,
    ];

    $build['pager'] = [
      '#type' => 'pager',
      '#element' => static::CALENDAR_ACTIONS_PAGER_ELEMENT,
      '#weight' => 2,
    ];

    $build['footnote'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['hivelog-list-footnote']],
      '#attached' => ['library' => ['hivelog/tables']],
      '#value' => $this->t('How "Open seasonal tasks" is counted: the dashboard tile counts every enabled calendar action not yet reported Done or Ignored for the current year, for any week — an apiary-scoped action counts once, a hive-scoped action counts once per hive in its apiary that has not reported it. This table lists each calendar action once (not fanned out per hive); use the week filter above to narrow it down.'),
      '#weight' => 3,
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action')->getListCacheTags());
    foreach ($calendar_actions as $calendar_action) {
      $cache->addCacheableDependency($calendar_action);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Extracts Calendar Actions collection filter values from the request.
   *
   * @return array{week_from?: int, week_to?: int}
   *   Associative array keyed by filter name. Only present values are
   *   included, each clamped to the valid 1–53 ISO week range and swapped
   *   into order if given reversed.
   */
  protected function extractCollectionFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return [];
    }

    $filters = [];

    $week_from = trim((string) $request->query->get('week_from', ''));
    if ($week_from !== '' && ctype_digit($week_from)) {
      $filters['week_from'] = max(1, min(53, (int) $week_from));
    }

    $week_to = trim((string) $request->query->get('week_to', ''));
    if ($week_to !== '' && ctype_digit($week_to)) {
      $filters['week_to'] = max(1, min(53, (int) $week_to));
    }

    if (isset($filters['week_from'], $filters['week_to']) && $filters['week_from'] > $filters['week_to']) {
      [$filters['week_from'], $filters['week_to']] = [$filters['week_to'], $filters['week_from']];
    }

    return $filters;
  }

  /**
   * Applies Calendar Actions collection filters to an entity query.
   *
   * Matches against `week_start` only, the same simplified "does the
   * action start in this window" semantic DashboardController::
   * buildUpcoming() already uses for its own week-range look-ahead, rather
   * than a full window-overlap check against `week_end` too.
   */
  protected function applyCollectionFilters(QueryInterface $query, array $filters): void {
    if (isset($filters['week_from'])) {
      $query->condition('week_start', $filters['week_from'], '>=');
    }
    if (isset($filters['week_to'])) {
      $query->condition('week_start', $filters['week_to'], '<=');
    }
  }

  /**
   * Provides the add form for a calendar action within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $calendar_action = $this->entityTypeManager->getStorage('calendar_action')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($calendar_action, 'add');
  }

  /**
   * Provides the add form for a requirement within a calendar action context.
   */
  public function addRequirementForm(CalendarAction $calendar_action) {
    $requirement = $this->entityTypeManager->getStorage('calendar_action_item_requirement')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    return $this->entityFormBuilder->getForm($requirement, 'add');
  }

  /**
   * Provides the add form for a yield within a calendar action context.
   */
  public function addYieldForm(CalendarAction $calendar_action) {
    $yield = $this->entityTypeManager->getStorage('calendar_action_product_yield')->create([
      'calendar_action' => $calendar_action->id(),
    ]);
    return $this->entityFormBuilder->getForm($yield, 'add');
  }

  /**
   * Displays a calendar action with its fields grouped into readable sections.
   */
  public function view(CalendarAction $calendar_action) {
    $build = [
      'actions' => $this->buildActions($calendar_action),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $calendar_action, [
        'apiary',
        'title',
        'category',
        'enabled',
        'scope',
      ]),
      'schedule' => $this->buildSection($this->t('Schedule'), $calendar_action, [
        'week_start',
        'week_end',
        'recurring',
      ]),
      'description' => $this->buildSection($this->t('Description'), $calendar_action, [
        'description',
      ]),
    ];

    [$requirements_section, $requirements] = $this->buildRequirementsSection($calendar_action);
    $build['requirements'] = $requirements_section;

    [$yield_section, $yields] = $this->buildYieldSection($calendar_action);
    $build['yields'] = $yield_section;

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($calendar_action)
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action_item_requirement')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action_product_yield')->getListCacheTags());
    foreach ($requirements as $requirement) {
      $cache->addCacheableDependency($requirement);
    }
    foreach ($yields as $yield) {
      $cache->addCacheableDependency($yield);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the calendar action view page.
   */
  public function title(CalendarAction $calendar_action) {
    return $calendar_action->label();
  }

  /**
   * Builds Edit and Delete action links for the calendar action view.
   */
  protected function buildActions(CalendarAction $calendar_action): array {
    $buttons = [];
    if ($calendar_action->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $calendar_action->toUrl('edit-form')->toString()];
    }
    if ($calendar_action->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $calendar_action->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    if (empty($buttons)) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#weight' => -10,
    ];
  }

  /**
   * Builds the embedded "Required Items" section of the calendar action view.
   *
   * This is the recipe that a "done" report's inventory usage form pre-fills
   * from. Mirrors HiveController::buildInspectionsColumn()'s heading+table
   * shape, without pagination — recipes are short lists by nature.
   *
   * @param \Drupal\hivelog\Entity\CalendarAction $calendar_action
   *   The calendar action being rendered.
   *
   * @return array{0: array, 1: \Drupal\hivelog\Entity\CalendarActionItemRequirement[]}
   *   Tuple of [render array, loaded requirement entities for cache deps].
   */
  protected function buildRequirementsSection(CalendarAction $calendar_action): array {
    $requirement_ids = $this->entityTypeManager
      ->getStorage('calendar_action_item_requirement')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('calendar_action', $calendar_action->id())
      ->sort('id', 'ASC')
      ->execute();
    $requirements = $requirement_ids
      ? $this->entityTypeManager->getStorage('calendar_action_item_requirement')->loadMultiple($requirement_ids)
      : [];

    $header = [
      $this->t('Item'),
      $this->t('Quantity'),
      $this->t('Unit'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($requirements as $requirement) {
      $item = $requirement->get('item')->entity;
      $buttons = [];
      if ($requirement->access('update')) {
        $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $requirement->toUrl('edit-form')->toString()];
      }
      if ($requirement->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $requirement->toUrl('delete-form')->toString(),
          'variant' => 'danger',
        ];
      }
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ];
      $rows[] = [
        'cells' => [
          $item ? $item->toLink()->toString() : (string) $this->t('Unknown item'),
          rtrim(rtrim(number_format((float) $requirement->get('quantity')->value, 3, '.', ''), '0'), '.'),
          $item ? $item->get('unit')->value : '',
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $section = [
      '#type' => 'container',
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Required Items'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Required Item'),
            'url' => Url::fromRoute('hivelog.calendar_action_item_requirement.add', ['calendar_action' => $calendar_action->id()])->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ],
      ],
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $header),
          'rows' => $rows,
          'empty_message' => (string) $this->t('No required items have been recorded for this calendar action yet.'),
        ],
      ],
    ];

    return [$section, $requirements];
  }

  /**
   * Builds the embedded "Expected Yield" section of the calendar action view.
   *
   * This is the recipe that a "done" report's yield form pre-fills from.
   * Placed alongside (not replacing) buildRequirementsSection() — a
   * calendar action can need items (jars) and yield products (honey) at
   * once. Mirrors buildRequirementsSection() exactly, one level removed
   * (outputs instead of inputs).
   *
   * @param \Drupal\hivelog\Entity\CalendarAction $calendar_action
   *   The calendar action being rendered.
   *
   * @return array{0: array, 1: \Drupal\hivelog\Entity\CalendarActionProductYield[]}
   *   Tuple of [render array, loaded yield entities for cache deps].
   */
  protected function buildYieldSection(CalendarAction $calendar_action): array {
    $yield_ids = $this->entityTypeManager
      ->getStorage('calendar_action_product_yield')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('calendar_action', $calendar_action->id())
      ->sort('id', 'ASC')
      ->execute();
    $yields = $yield_ids
      ? $this->entityTypeManager->getStorage('calendar_action_product_yield')->loadMultiple($yield_ids)
      : [];

    $header = [
      $this->t('Product'),
      $this->t('Quantity'),
      $this->t('Unit'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($yields as $yield) {
      $product = $yield->get('product')->entity;
      $buttons = [];
      if ($yield->access('update')) {
        $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $yield->toUrl('edit-form')->toString()];
      }
      if ($yield->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $yield->toUrl('delete-form')->toString(),
          'variant' => 'danger',
        ];
      }
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ];
      $rows[] = [
        'cells' => [
          $product ? $product->toLink()->toString() : (string) $this->t('Unknown product'),
          rtrim(rtrim(number_format((float) $yield->get('quantity')->value, 3, '.', ''), '0'), '.'),
          $product ? $product->get('unit')->value : '',
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $section = [
      '#type' => 'container',
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Expected Yield'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Expected Yield'),
            'url' => Url::fromRoute('hivelog.calendar_action_product_yield.add', ['calendar_action' => $calendar_action->id()])->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ],
      ],
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $header),
          'rows' => $rows,
          'empty_message' => (string) $this->t('No expected yield has been recorded for this calendar action yet.'),
        ],
      ],
    ];

    return [$section, $yields];
  }

  /**
   * Builds a consistently formatted calendar action section.
   */
  protected function buildSection($title, CalendarAction $calendar_action, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-calendar-action-section'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $this->buildRows($calendar_action, $fields),
        '#attributes' => [
          'class' => ['hivelog-calendar-action-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(CalendarAction $calendar_action, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $calendar_action->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($calendar_action, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single calendar action field.
   */
  protected function buildFieldValue(CalendarAction $calendar_action, string $field_name): array {
    $field = $calendar_action->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'apiary':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'category':
      case 'scope':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

      case 'enabled':
      case 'recurring':
        return [
          '#plain_text' => $field->value ? (string) $this->t('Yes') : (string) $this->t('No'),
        ];

      case 'description':
        return [
          '#markup' => SimpleBulletText::render((string) $field->value),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
