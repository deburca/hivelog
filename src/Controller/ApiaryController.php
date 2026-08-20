<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Form\HivelogCalendarFilterForm;
use Drupal\hivelog\Form\HivelogFullCalendarFilterForm;
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
   * Default number of inventory items shown per page in the embedded list.
   */
  public const INVENTORY_ITEMS_PER_PAGE = 20;

  /**
   * Pager element id for the embedded inventory items table.
   *
   * Distinct from HIVES_PAGER_ELEMENT since both tables are embedded on
   * the same apiary view() page.
   */
  protected const INVENTORY_ITEMS_PAGER_ELEMENT = 1;

  /**
   * Default number of products shown per page in the embedded list.
   */
  public const PRODUCTS_PER_PAGE = 20;

  /**
   * Pager element id for the embedded products table.
   *
   * Distinct from HIVES_PAGER_ELEMENT/INVENTORY_ITEMS_PAGER_ELEMENT since
   * all three tables are embedded on the same apiary view() page.
   */
  protected const PRODUCTS_PAGER_ELEMENT = 2;

  /**
   * Default number of calendar actions shown per page on the Full Calendar page.
   */
  public const CALENDAR_ACTIONS_PER_PAGE = 20;

  /**
   * Pager element id for the Full Calendar page's table.
   *
   * A standalone page (unlike the embedded hive/inspection tables), so
   * element 0 is safe to reuse — there is only ever one paginated list on
   * this page.
   */
  protected const CALENDAR_ACTIONS_PAGER_ELEMENT = 0;

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
      $queen = $hive->getActiveQueen();
      $breed = $queen ? $queen->get('breed')->value : NULL;
      $rows[] = [
        'cells' => [
          $hive->toLink()->toString(),
          $breed ? $queen->get('breed')->getSetting('allowed_values')[$breed] ?? $breed : '',
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

    // Seasonal Calendar: the apiary-scoped checklist of seasonal duties that
    // apply once for the whole apiary (task 0027) — e.g. CBR renewal, site
    // maintenance — as opposed to hive-scoped duties reported separately on
    // every hive. Placed after the hives list since hives are the primary
    // thing an apiary page is about; the calendar is secondary, supporting
    // information.
    //
    // The current ISO week is surfaced in the heading (rather than a new
    // flex child in .hivelog-list-heading, which is styled for exactly two
    // children via justify-content: space-between); each unreported row's
    // timing (Due now/Overdue/Upcoming) is merged into the Status column,
    // mirroring HiveController's checklist exactly — see ADR-0025 addendum
    // on "current week" visibility (task 0026).
    //
    // Both actions (View Full Calendar, Add Calendar Action) share the
    // heading's second flex child — a plain container carrying the
    // `hivelog-list-heading__action` class — so the row still has exactly
    // the two children `.hivelog-list-heading` is styled for, with both
    // buttons right-aligned together rather than one per row.
    $current_week = (int) date('W');
    $current_year = (int) date('Y');
    $build['calendar_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 20,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Seasonal Calendar (current week: @week)', ['@week' => $current_week]),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'full_calendar' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('View Full Calendar'),
            'url' => Url::fromRoute('hivelog.apiary.calendar_action.collection', ['apiary' => $apiary->id()])->toString(),
          ],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Calendar Action'),
            'url' => Url::fromRoute('hivelog.calendar_action.add', ['apiary' => $apiary->id()])->toString(),
            'variant' => 'primary',
          ],
        ],
      ],
    ];

    $build['calendar_filter'] = $this->formBuilder->getForm(
      HivelogCalendarFilterForm::class,
      Url::fromRoute('entity.apiary.canonical', ['apiary' => $apiary->id()])
    );
    $build['calendar_filter']['#weight'] = 21;

    $calendar_filters = $this->extractCalendarFilters();
    $checklist = $this->buildApiaryCalendarChecklist($apiary, $calendar_filters['year'], $calendar_filters['status']);
    $show_timing = ($calendar_filters['year'] === $current_year);

    $checklist_header = [
      $this->t('Title'),
      $this->t('Week(s)'),
      $this->t('Status'),
      $this->t('Week Completed'),
      $this->t('Notes'),
      $this->t('Operations'),
    ];

    $status_labels = [
      'pending' => $this->t('Unreported'),
      'done' => $this->t('Done'),
      'ignored' => $this->t('Ignored'),
    ];

    $checklist_rows = [];
    foreach ($checklist['rows'] as $entry) {
      $calendar_action = $entry['calendar_action'];
      $log = $entry['log'];
      $status = $entry['status'];

      $week_start = $calendar_action->get('week_start')->value;
      $week_end = $calendar_action->get('week_end')->value;
      $weeks = ($week_end !== NULL && $week_end !== '' && (int) $week_end !== (int) $week_start)
        ? $this->t('@start–@end', ['@start' => $week_start, '@end' => $week_end])
        : (string) $week_start;

      $week_completed = $log ? $log->get('week_completed')->value : NULL;
      $week_completed_display = ($week_completed !== NULL && $week_completed !== '') ? (string) $week_completed : '';

      $notes = $log ? (string) $log->get('notes')->value : '';
      $notes_display = $notes !== '' ? nl2br(Html::escape($notes)) : '';

      $status_display = (string) ($status_labels[$status] ?? $status);
      if ($status === 'pending' && $show_timing) {
        $timing = $this->pendingActionTimingLabel((int) $week_start, $week_end, $current_week);
        $status_display = (string) $this->t('@status (@timing)', ['@status' => $status_display, '@timing' => $timing]);
      }

      if ($status === 'pending') {
        // Unreported: offer to report it, rather than a generic "Log"
        // action — these are safe GET navigations to the add-form with a
        // ?status= query default; the actual write only happens through
        // that form's own CSRF-protected POST submission (ADR-0018).
        $actions = [
          '#type' => 'component',
          '#component' => 'hivelog:button-group',
          '#props' => [
            'buttons' => [
              [
                'label' => (string) $this->t('Report Done'),
                'url' => Url::fromRoute('hivelog.apiary_action_log.add', [
                  'apiary' => $apiary->id(),
                  'calendar_action' => $calendar_action->id(),
                ], ['query' => ['status' => 'done']])->toString(),
                'variant' => 'primary',
              ],
              [
                'label' => (string) $this->t('Report Ignored'),
                'url' => Url::fromRoute('hivelog.apiary_action_log.add', [
                  'apiary' => $apiary->id(),
                  'calendar_action' => $calendar_action->id(),
                ], ['query' => ['status' => 'ignored']])->toString(),
              ],
            ],
          ],
        ];
      }
      else {
        // Already reported: offer to view (and, if permitted, edit) the
        // log that reported it. No linked-inspection button here — that
        // feature is inherently hive-scoped (see ApiaryActionLog docblock).
        $buttons = [];
        if ($log) {
          $buttons[] = ['label' => (string) $this->t('View Log'), 'url' => $log->toUrl('canonical')->toString()];
          if ($log->access('update')) {
            $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $log->toUrl('edit-form')->toString()];
          }
        }
        $actions = [
          '#type' => 'component',
          '#component' => 'hivelog:button-group',
          '#props' => ['buttons' => $buttons],
        ];
      }

      $checklist_rows[] = [
        'cells' => [
          $calendar_action->toLink()->toString(),
          (string) $weeks,
          $status_display,
          $week_completed_display,
          $notes_display,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['calendar_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $checklist_header),
        'rows' => $checklist_rows,
        'empty_message' => (string) $this->calendarChecklistEmptyMessage($checklist['total_enabled'], $calendar_filters['status']),
      ],
      '#weight' => 22,
    ];

    // Inventory: an apiary-scoped table of inventory items, matching the
    // Hives table's pattern above. No "View Inventory Items" link-out —
    // the items are already listed right here, and the main-nav
    // "Inventory Items" link (hivelog.links.menu.yml) still reaches the
    // global, cross-apiary catalog when that's what's actually wanted.
    // "Add Purchase" (apiary-scoped, pre-fills apiary) is the direct path
    // to recording stock for a consumable item — stock on hand is a
    // computed value (purchases minus usage), never directly editable.
    $build['inventory_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 25,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Inventory'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'cost_report' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('View Financial Report'),
            'url' => Url::fromRoute('hivelog.apiary.inventory_cost_report', ['apiary' => $apiary->id()])->toString(),
          ],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Inventory Item'),
            'url' => Url::fromRoute('hivelog.inventory_item.add', ['apiary' => $apiary->id()])->toString(),
            'variant' => 'primary',
          ],
        ],
        'add_purchase' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Purchase'),
            'url' => Url::fromRoute('hivelog.inventory_purchase.add', ['apiary' => $apiary->id()])->toString(),
          ],
        ],
      ],
    ];

    $inventory_item_ids = $this->entityTypeManager
      ->getStorage('inventory_item')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('name', 'ASC')
      ->pager(static::INVENTORY_ITEMS_PER_PAGE, static::INVENTORY_ITEMS_PAGER_ELEMENT)
      ->execute();

    $inventory_items = $inventory_item_ids
      ? $this->entityTypeManager->getStorage('inventory_item')->loadMultiple($inventory_item_ids)
      : [];
    $inventory_items = array_filter(
      $inventory_items,
      fn($item) => $item->access('view', $this->currentUser)
    );

    $inventory_header = [
      $this->t('Name'),
      $this->t('Category'),
      $this->t('Unit'),
      $this->t('Type'),
      $this->t('Stock on Hand'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $inventory_rows = [];
    foreach ($inventory_items as $item) {
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => [
            ['label' => (string) $this->t('Edit'), 'url' => $item->toUrl('edit-form')->toString()],
            [
              'label' => (string) $this->t('Delete'),
              'url' => $item->toUrl('delete-form')->toString(),
              'variant' => 'danger',
            ],
          ],
        ],
      ];

      $category = $item->get('category')->value;
      $item_type = $item->get('item_type')->value;
      $status = $item->get('status')->value;
      /** @var \Drupal\hivelog\Entity\InventoryItem $item */
      $stock = $item->getStockOnHand();

      $inventory_rows[] = [
        'cells' => [
          $item->toLink()->toString(),
          $category ? ($item->get('category')->getSetting('allowed_values')[$category] ?? $category) : '',
          $item->get('unit')->value,
          $item->get('item_type')->getSetting('allowed_values')[$item_type] ?? $item_type,
          $stock === NULL ? '' : rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . $item->get('unit')->value,
          $item->get('status')->getSetting('allowed_values')[$status] ?? $status,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['inventory_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $inventory_header),
        'rows' => $inventory_rows,
        'empty_message' => (string) $this->t('No inventory items have been added to this apiary yet.'),
      ],
      '#weight' => 26,
    ];

    $build['inventory_pager'] = [
      '#type' => 'pager',
      '#element' => static::INVENTORY_ITEMS_PAGER_ELEMENT,
      '#weight' => 27,
    ];

    // Products: the sellable-output catalog (honey, wax, propolis) — same
    // apiary-scoped, embedded-table shape as Inventory above, per
    // [[0035-product-catalog-entity-and-ui]].
    $build['products_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 30,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Products'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Product'),
            'url' => Url::fromRoute('hivelog.product.add', ['apiary' => $apiary->id()])->toString(),
            'variant' => 'primary',
          ],
        ],
      ],
    ];

    $product_ids = $this->entityTypeManager
      ->getStorage('product')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('name', 'ASC')
      ->pager(static::PRODUCTS_PER_PAGE, static::PRODUCTS_PAGER_ELEMENT)
      ->execute();

    $products = $product_ids
      ? $this->entityTypeManager->getStorage('product')->loadMultiple($product_ids)
      : [];
    $products = array_filter(
      $products,
      fn($product) => $product->access('view', $this->currentUser)
    );

    $products_header = [
      $this->t('Name'),
      $this->t('Unit'),
      $this->t('Expected Unit Price'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $products_rows = [];
    foreach ($products as $product) {
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => [
            ['label' => (string) $this->t('Edit'), 'url' => $product->toUrl('edit-form')->toString()],
            [
              'label' => (string) $this->t('Delete'),
              'url' => $product->toUrl('delete-form')->toString(),
              'variant' => 'danger',
            ],
          ],
        ],
      ];

      $status = $product->get('status')->value;
      $price = $product->get('expected_unit_price')->value;

      $products_rows[] = [
        'cells' => [
          $product->toLink()->toString(),
          $product->get('unit')->value,
          $price !== NULL && $price !== '' ? number_format((float) $price, 2) : '',
          $product->get('status')->getSetting('allowed_values')[$status] ?? $status,
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build['products_table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => array_map('strval', $products_header),
        'rows' => $products_rows,
        'empty_message' => (string) $this->t('No products have been added to this apiary yet.'),
      ],
      '#weight' => 31,
    ];

    $build['products_pager'] = [
      '#type' => 'pager',
      '#element' => static::PRODUCTS_PAGER_ELEMENT,
      '#weight' => 32,
    ];

    // Explicit cache metadata.
    // - url.query_args: pager + filter state, and now the calendar
    //   checklist's status/year filter, are all encoded in the query string.
    // - user.permissions: hive/calendar-action/log rows are post-filtered
    //   by per-entity access, so two users with different permissions must
    //   not share a cache entry.
    // - Apiary entity tags: invalidate on apiary update/delete.
    // - Hive list cache tag + each rendered hive's own tags: invalidate on
    //   any hive change so the embedded table is never stale.
    // - Calendar action / apiary action log list cache tags + every
    //   rendered calendar action/log's own tags: invalidate on any change
    //   to either, since the checklist is computed by cross-referencing
    //   both on read.
    // - Inventory item list cache tag + each rendered item's own tags:
    //   invalidate on any inventory item change. Also inventory_purchase/
    //   inventory_usage list cache tags, since the Stock on Hand column is
    //   derived from both and a purchase/usage save doesn't bump the
    //   owning InventoryItem's own cache tag — matching
    //   InventoryItemController::view()'s cache metadata for the same
    //   derived value.
    // - Product list cache tag + each rendered product's own tags:
    //   invalidate on any product change so the embedded table is never
    //   stale.
    // - max-age: the heading's "current week" and each unreported row's
    //   Due now/Overdue/Upcoming suffix are computed from date('W')/
    //   date('Y') ("now"), so the render must not be cached past the
    //   moment the ISO week actually changes, or it would show a stale
    //   week/timing after that boundary passes.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($apiary)
      ->addCacheTags($this->entityTypeManager->getDefinition('hive')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('apiary_action_log')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('inventory_item')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('inventory_purchase')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('inventory_usage')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('product')->getListCacheTags())
      ->setCacheMaxAge($this->secondsUntilNextIsoWeek());
    foreach ($inventory_items as $item) {
      $cache->addCacheableDependency($item);
    }
    foreach ($products as $product) {
      $cache->addCacheableDependency($product);
    }
    foreach ($hives as $hive) {
      $cache->addCacheableDependency($hive);
    }
    foreach ($checklist['rows'] as $entry) {
      $cache->addCacheableDependency($entry['calendar_action']);
      if ($entry['log']) {
        $cache->addCacheableDependency($entry['log']);
      }
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Displays every calendar action for an apiary, both scopes.
   *
   * Reference/management view — no Status/Report buttons here; actual
   * reporting still happens on the apiary page (for apiary-scoped items) or
   * each hive's page (for hive-scoped items). Formatted like every other
   * embedded/collection table in the module (heading + Add action, a GET
   * filter form, a paginated `hivelog:entity-table`, and per-row Edit/
   * Delete operations), rather than the plain read-only table this page
   * originally shipped with. Sorted by week_start, with a Scope column so
   * it's visually clear which is which.
   */
  public function fullCalendar(Apiary $apiary) {
    $build = [];

    // Heading row: Add Calendar Action, right-aligned via
    // .hivelog-list-heading__action's margin-left: auto. No title text
    // here — this is a standalone page (unlike Hives/Inventory, which are
    // sections within the longer apiary page and need their own label),
    // so a duplicate of the route's own page title
    // (fullCalendarTitle()) would just repeat the <h1> immediately above.
    $build['heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 0,
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

    $build['filter'] = $this->formBuilder->getForm(HivelogFullCalendarFilterForm::class, $apiary);
    $build['filter']['#weight'] = 1;

    $filters = $this->extractFullCalendarFilters();
    $query = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('week_start', 'ASC')
      ->pager(static::CALENDAR_ACTIONS_PER_PAGE, static::CALENDAR_ACTIONS_PAGER_ELEMENT);
    $this->applyFullCalendarFilters($query, $filters);
    $calendar_action_ids = $query->execute();

    $calendar_actions = $calendar_action_ids
      ? $this->entityTypeManager->getStorage('calendar_action')->loadMultiple($calendar_action_ids)
      : [];
    $calendar_actions = array_filter(
      $calendar_actions,
      fn($calendar_action) => $calendar_action->access('view', $this->currentUser)
    );

    $scope_labels = [
      'hive' => $this->t('Hive'),
      'apiary' => $this->t('Apiary'),
    ];

    $header = [
      $this->t('Title'),
      $this->t('Scope'),
      $this->t('Week(s)'),
      $this->t('Category'),
      $this->t('Enabled'),
      $this->t('Operations'),
    ];

    // The current page's own URL (including any applied filters/pager
    // state), passed as a `destination` query parameter on the Edit/
    // Delete links below. CalendarActionForm/CalendarActionDeleteForm
    // otherwise always redirect back to the apiary's canonical page on
    // save/delete — Drupal core's RedirectResponseSubscriber overrides
    // that with `destination` whenever it's present, which is what sends
    // the beekeeper back to this page (rather than the apiary page)
    // without any changes needed to those two shared form classes.
    $request = $this->requestStack->getCurrentRequest();
    $destination = $request ? $request->getRequestUri() : Url::fromRoute('hivelog.apiary.calendar_action.collection', ['apiary' => $apiary->id()])->toString();

    $rows = [];
    foreach ($calendar_actions as $calendar_action) {
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
          'url' => $calendar_action->toUrl('edit-form', ['query' => ['destination' => $destination]])->toString(),
        ];
      }
      if ($calendar_action->access('delete')) {
        $buttons[] = [
          'label' => (string) $this->t('Delete'),
          'url' => $calendar_action->toUrl('delete-form', ['query' => ['destination' => $destination]])->toString(),
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
          $scope_display,
          (string) $weeks,
          (string) $category_label,
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
          : $this->t('No calendar actions have been added to this apiary yet.')),
      ],
      '#weight' => 2,
    ];

    $build['pager'] = [
      '#type' => 'pager',
      '#element' => static::CALENDAR_ACTIONS_PAGER_ELEMENT,
      '#weight' => 3,
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($apiary)
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action')->getListCacheTags());
    foreach ($calendar_actions as $calendar_action) {
      $cache->addCacheableDependency($calendar_action);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Extracts Full Calendar filter values from the current request.
   *
   * Unlike the other filter-value keys, `enabled` is only included when
   * it differs from its default (`1`, "Enabled only") — this is what lets
   * `!empty($filters)` keep distinguishing "no calendar actions exist at
   * all" from "none match the current filters" for the empty-state
   * message, while `applyFullCalendarFilters()` still treats a missing
   * `enabled` key as the default rather than "no restriction".
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-default values are
   *   included.
   */
  protected function extractFullCalendarFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return [];
    }

    $filters = [];

    $scope = trim((string) $request->query->get('scope', ''));
    if ($scope !== '') {
      $filters['scope'] = $scope;
    }

    $category = trim((string) $request->query->get('category', ''));
    if ($category !== '') {
      $filters['category'] = $category;
    }

    $enabled = trim((string) $request->query->get('enabled', '1'));
    if (!in_array($enabled, ['', '0', '1'], TRUE)) {
      $enabled = '1';
    }
    if ($enabled !== '1') {
      $filters['enabled'] = $enabled;
    }

    $title = trim((string) $request->query->get('title', ''));
    if ($title !== '') {
      $filters['title'] = $title;
    }

    return $filters;
  }

  /**
   * Applies Full Calendar filters to an entity query.
   */
  protected function applyFullCalendarFilters(QueryInterface $query, array $filters): void {
    if (isset($filters['scope'])) {
      $query->condition('scope', $filters['scope']);
    }
    if (isset($filters['category'])) {
      $query->condition('category', $filters['category']);
    }
    if (isset($filters['title'])) {
      $query->condition('title', '%' . $this->escapeLike($filters['title']) . '%', 'LIKE');
    }
    // A missing key means the default ("Enabled only", `1`) applies — this
    // is what preserves the page's original "hide disabled actions"
    // behaviour. An explicit empty string ("- Any -") means no
    // restriction at all.
    $enabled = $filters['enabled'] ?? '1';
    if ($enabled !== '') {
      $query->condition('enabled', $enabled === '1');
    }
  }

  /**
   * Title callback for the full calendar page.
   */
  public function fullCalendarTitle(Apiary $apiary) {
    return $this->t('Full Calendar: @apiary', ['@apiary' => $apiary->label()]);
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
    foreach (['status', 'breed', 'temperament', 'name'] as $key) {
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
    if (isset($filters['breed'])) {
      // Breed lives on the active queen, not the hive, so resolve matching
      // hive ids via the queen entity first (see Hive::getActiveQueen()).
      $hive_ids = $this->hiveIdsForActiveQueenBreed($filters['breed']);
      $query->condition('id', $hive_ids, 'IN');
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

  /**
   * Finds hive ids whose active queen has the given breed.
   *
   * @param string $breed
   *   One of the `breed` field's allowed values on the Queen entity.
   *
   * @return int[]
   *   Matching hive ids, or `[0]` (a value no hive can have) if none match,
   *   so callers can pass the result straight into an `IN` condition.
   */
  protected function hiveIdsForActiveQueenBreed(string $breed): array {
    $queen_storage = $this->entityTypeManager->getStorage('queen');
    $queen_ids = $queen_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('breed', $breed)
      ->condition('status', 'active')
      ->execute();

    $hive_ids = [];
    if ($queen_ids) {
      foreach ($queen_storage->loadMultiple($queen_ids) as $queen) {
        $hive_id = $queen->get('hive')->target_id;
        if ($hive_id) {
          $hive_ids[] = $hive_id;
        }
      }
    }

    return $hive_ids ?: [0];
  }

  /**
   * Builds the apiary-scoped calendar checklist for a given year/status.
   *
   * Cross-references every enabled, apiary-scoped (`scope = 'apiary'`)
   * calendar action for this apiary against `ApiaryActionLog` entries for
   * the given year, defaulting unreported combinations to a synthetic
   * `pending` status. Mirrors `HiveController::buildCalendarChecklist()`
   * exactly, but keyed by apiary + calendar_action + year, via
   * `apiary_action_log` rather than `hive_action_log`, and restricted to
   * `scope = 'apiary'` rather than `scope = 'hive'` — the two scopes never
   * appear on the same checklist (task 0027).
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary to build a checklist for.
   * @param int $year
   *   Which annual occurrence of each calendar action to check against.
   * @param string $status_filter
   *   One of `pending`, `done`, `ignored`, or `all`.
   *
   * @return array
   *   An array with two keys: `total_enabled` is the count of enabled,
   *   apiary-scoped calendar actions on this apiary *before* the status
   *   filter is applied — used to tell "nothing pending" apart from "no
   *   calendar actions exist at all" for the empty-state message. `rows` is
   *   an array keyed by calendar action id, each entry an associative array
   *   with `calendar_action` (the \Drupal\hivelog\Entity\CalendarAction),
   *   `log` (the matching \Drupal\hivelog\Entity\ApiaryActionLog, or NULL if
   *   unreported), and `status` (the effective status string used for
   *   filtering/display). Hive-scoped calendar actions are excluded
   *   entirely — they never appear on any apiary's checklist.
   */
  protected function buildApiaryCalendarChecklist(Apiary $apiary, int $year, string $status_filter): array {
    $calendar_action_ids = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->condition('enabled', TRUE)
      ->condition('scope', 'apiary')
      ->sort('week_start', 'ASC')
      ->execute();

    if (!$calendar_action_ids) {
      return ['total_enabled' => 0, 'rows' => []];
    }

    $calendar_actions = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->loadMultiple($calendar_action_ids);
    $calendar_actions = array_filter(
      $calendar_actions,
      fn($calendar_action) => $calendar_action->access('view')
    );
    $total_enabled = count($calendar_actions);
    if (!$calendar_actions) {
      return ['total_enabled' => $total_enabled, 'rows' => []];
    }

    // Load every log for this apiary + year against these calendar actions
    // in one query, then index by calendar_action id — last-changed wins if
    // more than one log exists for the same calendar action.
    $log_ids = $this->entityTypeManager
      ->getStorage('apiary_action_log')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->condition('calendar_action', array_keys($calendar_actions), 'IN')
      ->condition('year', $year)
      ->sort('changed', 'ASC')
      ->execute();

    $logs_by_action = [];
    if ($log_ids) {
      foreach ($this->entityTypeManager->getStorage('apiary_action_log')->loadMultiple($log_ids) as $log) {
        // Later iterations (sorted ascending by `changed`) overwrite
        // earlier ones, so the most recently changed log wins.
        $logs_by_action[$log->get('calendar_action')->target_id] = $log;
      }
    }

    $rows = [];
    foreach ($calendar_actions as $calendar_action) {
      $log = $logs_by_action[$calendar_action->id()] ?? NULL;
      $effective_status = $log ? $log->get('status')->value : 'pending';
      if ($status_filter !== 'all' && $effective_status !== $status_filter) {
        continue;
      }
      $rows[$calendar_action->id()] = [
        'calendar_action' => $calendar_action,
        'log' => $log,
        'status' => $effective_status,
      ];
    }

    return ['total_enabled' => $total_enabled, 'rows' => $rows];
  }

  /**
   * Extracts and validates the calendar checklist's status/year filters.
   *
   * Defaults to the "unreported, current year" view when the query string
   * is absent or holds an invalid value — this is what makes that the
   * checklist's default view rather than an optional refinement, per
   * ADR-0025. Mirrors `HiveController::extractCalendarFilters()` exactly.
   *
   * @return array{status: string, year: int}
   *   `status` is one of `pending`/`done`/`ignored`/`all`; `year` is one of
   *   the current year, the previous year, or the next year.
   */
  protected function extractCalendarFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query : NULL;
    $current_year = (int) date('Y');

    $status = $query ? (string) $query->get('status', 'pending') : 'pending';
    if (!in_array($status, ['pending', 'done', 'ignored', 'all'], TRUE)) {
      $status = 'pending';
    }

    $year = $query ? (int) $query->get('year', (string) $current_year) : $current_year;
    if (!in_array($year, [$current_year - 1, $current_year, $current_year + 1], TRUE)) {
      $year = $current_year;
    }

    return ['status' => $status, 'year' => $year];
  }

  /**
   * Builds the empty-state message for the calendar checklist table.
   *
   * Distinguishes "no calendar actions exist at all" from "none match the
   * current status filter", per the task's explicit requirement to tell
   * the two apart.
   *
   * @param int $total_enabled
   *   Count of enabled, apiary-scoped calendar actions on this apiary,
   *   before the status filter is applied (from
   *   `buildApiaryCalendarChecklist()`).
   * @param string $status_filter
   *   The active status filter (`pending`/`done`/`ignored`/`all`).
   */
  protected function calendarChecklistEmptyMessage(int $total_enabled, string $status_filter): TranslatableMarkup {
    if ($total_enabled === 0) {
      return $this->t('This apiary has no apiary-scoped calendar actions set up yet.');
    }

    $messages = [
      'pending' => $this->t('No pending seasonal actions for this apiary.'),
      'done' => $this->t('No actions have been reported as done for this apiary.'),
      'ignored' => $this->t('No actions have been reported as ignored for this apiary.'),
    ];

    return $messages[$status_filter] ?? $this->t('No calendar actions match the current filters.');
  }

  /**
   * Describes an unreported calendar action's timing versus the current week.
   *
   * `CalendarAction` never wraps across the year boundary (`week_end` must
   * be `>= week_start`, enforced by `CalendarAction::preSave()`), so plain
   * integer comparison is sufficient — no modulo/wraparound arithmetic is
   * needed. Only called for `pending` (unreported) rows, so "Overdue" is
   * always actionable — it can never apply to something already done or
   * ignored.
   *
   * @param int $week_start
   *   The calendar action's start week.
   * @param int|string|null $week_end
   *   The calendar action's end week, or NULL/empty for a single week.
   * @param int $current_week
   *   The current ISO week number to compare against.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   "Upcoming", "Due now", or "Overdue".
   */
  protected function pendingActionTimingLabel(int $week_start, $week_end, int $current_week): TranslatableMarkup {
    $effective_end = ($week_end !== NULL && $week_end !== '') ? (int) $week_end : $week_start;

    if ($current_week < $week_start) {
      return $this->t('Upcoming');
    }
    if ($current_week > $effective_end) {
      return $this->t('Overdue');
    }
    return $this->t('Due now');
  }

  /**
   * Seconds remaining until the ISO week changes (next Monday, midnight).
   *
   * Used to bound the cache max-age for any render that surfaces the
   * current week or a week-relative timing label, so a cached page never
   * shows a stale week after the boundary passes.
   *
   * @return int
   *   Seconds until the next ISO week boundary.
   */
  protected function secondsUntilNextIsoWeek(): int {
    $now = new \DateTimeImmutable('now');
    $next_boundary = new \DateTimeImmutable('next monday midnight');
    return max(0, $next_boundary->getTimestamp() - $now->getTimestamp());
  }

}
