<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Form\HivelogHiveFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for Apiary pages.
 */
class ApiaryController extends ControllerBase {

  /**
   * Default number of hives shown per page in the embedded list.
   */
  public const HIVES_PER_PAGE = 20;

  /**
   * Pager element id for the embedded hive table.
   */
  protected const HIVES_PAGER_ELEMENT = 0;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs an ApiaryController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    AccountInterface $current_user,
    RequestStack $request_stack,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager / $formBuilder / $currentUser are untyped properties
    // inherited from ControllerBase; assign rather than redeclare them.
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('renderer'),
    );
  }

  /**
   * Displays an apiary with its hives.
   */
  public function view(Apiary $apiary) {
    $build = [];

    // Render the apiary entity fields.
    $view_builder = $this->entityTypeManager->getViewBuilder('apiary');
    $build['apiary'] = $view_builder->view($apiary);

    // Load the responsive map stylesheet so the apiary's Leaflet map gets a
    // taller, viewport-relative height on small screens (task 0008).
    $build['#attached']['library'][] = 'hivelog/map';

    // Heading row: the "Hives" title on the left, the Add Hive action on
    // the right. Placing the action here (rather than inline with the
    // filter form below) keeps it at the top-right of the list section
    // where it logically belongs.
    $build['hives_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 10,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Hives'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'add' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Add Hive'),
          'url' => Url::fromRoute('hivelog.hive.add', ['apiary' => $apiary->id()])->toString(),
          'variant' => 'primary',
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ],
    ];

    // Filter form sits on its own row below the heading. The form's own
    // CSS pushes the Filter / Reset buttons to the right-hand side of the
    // filter row.
    $build['hives_filter'] = $this->formBuilder->getForm(HivelogHiveFilterForm::class, $apiary);
    $build['hives_filter']['#weight'] = 11;

    // Build the query with filters and pagination applied.
    $filters = $this->extractHiveFilters();
    $query = $this->entityTypeManager
      ->getStorage('hive')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('name', 'ASC')
      ->pager(static::HIVES_PER_PAGE, static::HIVES_PAGER_ELEMENT);
    $this->applyHiveFilters($query, $filters);
    $hive_ids = $query->execute();

    $hives = $hive_ids
      ? $this->entityTypeManager->getStorage('hive')->loadMultiple($hive_ids)
      : [];
    $hives = array_filter(
      $hives,
      fn($hive) => $hive->access('view', $this->currentUser)
    );

    $header = [
      $this->t('Name'),
      $this->t('Breed'),
      $this->t('Temperament'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($hives as $hive) {
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => [
            ['label' => (string) $this->t('Edit'), 'url' => $hive->toUrl('edit-form')->toString()],
            [
              'label' => (string) $this->t('Delete'),
              'url' => $hive->toUrl('delete-form')->toString(),
              'variant' => 'danger',
            ],
          ],
        ],
      ];
      $rows[] = [
        'cells' => [
          $hive->toLink()->toString(),
          $hive->get('bee_breed')->value ? $hive->get('bee_breed')->getSetting('allowed_values')[$hive->get('bee_breed')->value] ?? $hive->get('bee_breed')->value : '',
          $hive->get('temperament')->value ? $hive->get('temperament')->getSetting('allowed_values')[$hive->get('temperament')->value] ?? $hive->get('temperament')->value : '',
          $hive->get('status')->getSetting('allowed_values')[$hive->get('status')->value] ?? $hive->get('status')->value,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['hives_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $header),
        'rows' => $rows,
        'empty_message' => (string) (!empty($filters)
          ? $this->t('No hives match the current filters.')
          : $this->t('No hives have been added to this apiary yet.')),
      ],
      '#weight' => 12,
    ];

    $build['hives_pager'] = [
      '#type' => 'pager',
      '#element' => static::HIVES_PAGER_ELEMENT,
      '#weight' => 13,
    ];

    // Seasonal Calendar: the apiary-wide plan of seasonal duties, shared by
    // every hive in it (see ADR-0025). Placed after the hives list since
    // hives are the primary thing an apiary page is about; the calendar is
    // secondary, supporting information.
    $build['calendar_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 20,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Seasonal Calendar'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'add' => [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Add Calendar Action'),
          'url' => Url::fromRoute('hivelog.calendar_action.add', ['apiary' => $apiary->id()])->toString(),
          'variant' => 'primary',
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ],
    ];

    $calendar_action_ids = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->condition('enabled', TRUE)
      ->sort('week_start', 'ASC')
      ->execute();

    $calendar_actions = $calendar_action_ids
      ? $this->entityTypeManager->getStorage('calendar_action')->loadMultiple($calendar_action_ids)
      : [];
    $calendar_actions = array_filter(
      $calendar_actions,
      fn($calendar_action) => $calendar_action->access('view', $this->currentUser)
    );

    $calendar_header = [
      $this->t('Title'),
      $this->t('Week(s)'),
      $this->t('Category'),
      $this->t('Operations'),
    ];

    $calendar_rows = [];
    foreach ($calendar_actions as $calendar_action) {
      $week_start = $calendar_action->get('week_start')->value;
      $week_end = $calendar_action->get('week_end')->value;
      $weeks = ($week_end !== NULL && $week_end !== '' && (int) $week_end !== (int) $week_start)
        ? $this->t('@start–@end', ['@start' => $week_start, '@end' => $week_end])
        : (string) $week_start;

      $category = $calendar_action->get('category')->value;
      $category_label = $category
        ? ($calendar_action->get('category')->getSetting('allowed_values')[$category] ?? $category)
        : '';

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
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => ['buttons' => $buttons],
      ];

      $calendar_rows[] = [
        'cells' => [
          $calendar_action->toLink()->toString(),
          (string) $weeks,
          (string) $category_label,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['calendar_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $calendar_header),
        'rows' => $calendar_rows,
        'empty_message' => (string) $this->t('No calendar actions have been added to this apiary yet.'),
      ],
      '#weight' => 21,
    ];

    // Explicit cache metadata.
    // - url.query_args: pager + filter state travels in the query string.
    // - user.permissions: hive/calendar-action rows are post-filtered by
    //   per-entity access, so two users with different permissions must not
    //   share a cache entry.
    // - Apiary entity tags: invalidate on apiary update/delete.
    // - Hive list cache tag + each rendered hive's own tags: invalidate on
    //   any hive change so the embedded table is never stale.
    // - Calendar action list cache tag + each rendered calendar action's own
    //   tags: invalidate on any calendar action change.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($apiary)
      ->addCacheTags($this->entityTypeManager->getDefinition('hive')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action')->getListCacheTags());
    foreach ($hives as $hive) {
      $cache->addCacheableDependency($hive);
    }
    foreach ($calendar_actions as $calendar_action) {
      $cache->addCacheableDependency($calendar_action);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the apiary view page.
   */
  public function title(Apiary $apiary) {
    return $apiary->label();
  }

  /**
   * Extracts hive filter values from the current request.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  protected function extractHiveFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return [];
    }
    $filters = [];
    foreach (['status', 'bee_breed', 'temperament', 'name'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies hive filters to an entity query.
   */
  protected function applyHiveFilters(QueryInterface $query, array $filters): void {
    if (isset($filters['status'])) {
      $query->condition('status', $filters['status']);
    }
    if (isset($filters['bee_breed'])) {
      $query->condition('bee_breed', $filters['bee_breed']);
    }
    if (isset($filters['temperament'])) {
      $query->condition('temperament', $filters['temperament']);
    }
    if (isset($filters['name'])) {
      $query->condition('name', '%' . $this->escapeLike($filters['name']) . '%', 'LIKE');
    }
  }

  /**
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   */
  protected function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

}
