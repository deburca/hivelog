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
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Form\HivelogCalendarFilterForm;
use Drupal\hivelog\Form\HivelogFullCalendarFilterForm;
use Drupal\hivelog\Form\HivelogHiveFilterForm;
use Drupal\hivelog\HivelogCalendarChecklistBuilder;
use Drupal\hivelog\HivelogEntityActionsTrait;
use Drupal\hivelog\HivelogStatTileBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for Apiary pages.
 */
class ApiaryController extends ControllerBase {

  use HivelogEntityActionsTrait;

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
   * The stat-tile builder.
   */
  protected HivelogStatTileBuilder $statTileBuilder;

  /**
   * The calendar checklist builder (task 0130).
   */
  protected HivelogCalendarChecklistBuilder $calendarChecklistBuilder;

  /**
   * Constructs an ApiaryController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    AccountInterface $current_user,
    RequestStack $request_stack,
    RendererInterface $renderer,
    HivelogStatTileBuilder $stat_tile_builder,
    HivelogCalendarChecklistBuilder $calendar_checklist_builder,
  ) {
    // $entityTypeManager / $formBuilder / $currentUser are untyped properties
    // inherited from ControllerBase; assign rather than redeclare them.
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
    $this->statTileBuilder = $stat_tile_builder;
    $this->calendarChecklistBuilder = $calendar_checklist_builder;
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
      $container->get('hivelog.stat_tile_builder'),
      $container->get('hivelog.calendar_checklist_builder'),
    );
  }

  /**
   * Displays an apiary with its hives.
   */
  public function view(Apiary $apiary) {
    $build = [];

    // Page-owned Edit/Delete (task 0118) — the module's own convention
    // (ADR-0012, AGENTS.md "Routing, controllers and forms"); Apiary/Hive
    // were the two canonical pages that had no page-owned button group at
    // all, relying entirely on the Navigation module's top bar.
    $build['actions'] = $this->buildActions($apiary);

    // "At a glance" stat tiles — optional submodules only; hivelog core
    // contributes none of its own here. See hivelog.api.php's
    // hook_hivelog_apiary_stat_tiles() and ADR-0099. Weight -1 keeps
    // this first regardless of the other sections' own weights.
    $stat_tiles = $this->statTileBuilder->buildForApiary($apiary);
    if (!empty($stat_tiles)) {
      $build['stat_tiles'] = $stat_tiles + ['#weight' => -1];
    }

    // Render the apiary entity fields.
    $view_builder = $this->entityTypeManager->getViewBuilder('apiary');
    $build['apiary'] = $view_builder->view($apiary);

    // Load the responsive map stylesheet so the apiary's Leaflet map gets a
    // taller, viewport-relative height on small screens (task 0008).
    $build['#attached']['library'][] = 'hivelog/map';

    // Optional submodules (e.g. nanoprobe's Sensors panel) contribute
    // sections here without hivelog core depending on them — see
    // hivelog.api.php's hook_hivelog_apiary_view_panels() and ADR-0099.
    // Each panel carries its own #weight, so invocation order here
    // doesn't determine page position.
    foreach ($this->moduleHandler()->invokeAll('hivelog_apiary_view_panels', [$apiary]) as $key => $panel) {
      $build[$key] = $panel;
    }

    // Heading row: the "Hives" title on the left, the Add Hive action on
    // the right. Placing the action here (rather than inline with the
    // filter form below) keeps it at the top-right of the list section
    // where it logically belongs. The `id` (here and on the three
    // sibling headings below) is the anchor task 0141's delete-blocked
    // page links to via HivelogDeleteDependencyRegistry's
    // `'parent-canonical#<id>'` manage targets.
    $build['hives_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading'], 'id' => 'hives'],
      '#weight' => 10,
      'title' => [
        '#type' => 'html_tag',
        // H2 (task 0128) — a top-level apiary-page section.
        '#tag' => 'h2',
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
    $request = $this->requestStack->getCurrentRequest();
    $filters = $request ? HivelogHiveFilterForm::extract($request) : [];
    $query = $this->entityTypeManager
      ->getStorage('hive')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary->id())
      ->sort('name', 'ASC')
      ->pager(static::HIVES_PER_PAGE, static::HIVES_PAGER_ELEMENT);
    HivelogHiveFilterForm::apply($query, $filters);
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
    // All three actions (View Full Calendar, View all Logs, Add Calendar
    // Action) share the heading's second flex child — a plain container
    // carrying the `hivelog-list-heading__action` class — so the row
    // still has exactly the two children `.hivelog-list-heading` is
    // styled for, with every button right-aligned together rather than
    // one per row. "View all Logs" is task 0121's fix for
    // `entity.apiary_action_log.collection` otherwise having no inbound
    // link anywhere in the UI — an audit trail, not a daily
    // destination, so it belongs here rather than the nav strip.
    $current_week = (int) date('W');
    $current_year = (int) date('Y');
    $build['calendar_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading'], 'id' => 'calendar'],
      '#weight' => 20,
      'title' => [
        '#type' => 'html_tag',
        // H2 (task 0128) — a top-level apiary-page section.
        '#tag' => 'h2',
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
    $apiary_action_log_collection = Url::fromRoute('entity.apiary_action_log.collection');
    if ($apiary_action_log_collection->access()) {
      $build['calendar_heading']['actions']['view_logs'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('View all Logs'),
          'url' => $apiary_action_log_collection->toString(),
        ],
      ];
    }

    $build['calendar_filter'] = $this->formBuilder->getForm(
      HivelogCalendarFilterForm::class,
      Url::fromRoute('entity.apiary.canonical', ['apiary' => $apiary->id()])
    );
    $build['calendar_filter']['#weight'] = 21;

    $calendar_filters = $this->calendarChecklistBuilder->extractCalendarFilters();
    $checklist = $this->calendarChecklistBuilder->buildForApiary($apiary, $calendar_filters['year'], $calendar_filters['status']);
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
        $timing = $this->calendarChecklistBuilder->pendingActionTimingLabel((int) $week_start, $week_end, $current_week);
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
                'label' => (string) $this->t('Done'),
                'url' => Url::fromRoute('hivelog.apiary_action_log.add', [
                  'apiary' => $apiary->id(),
                  'calendar_action' => $calendar_action->id(),
                ], ['query' => ['status' => 'done']])->toString(),
                'variant' => 'primary',
              ],
              [
                'label' => (string) $this->t('Ignored'),
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
        'empty_message' => (string) $this->calendarChecklistBuilder->emptyMessage('apiary', $checklist['total_enabled'], $calendar_filters['status']),
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
      '#attributes' => ['class' => ['hivelog-list-heading'], 'id' => 'inventory'],
      '#weight' => 25,
      'title' => [
        '#type' => 'html_tag',
        // H2 (task 0128) — a top-level apiary-page section.
        '#tag' => 'h2',
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
      $stock_display = $stock === NULL ? '' : rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . $item->get('unit')->value;
      if ($item->isLowStock()) {
        $stock_display = (string) $this->t('@stock (Low Stock)', ['@stock' => $stock_display]);
      }

      $inventory_rows[] = [
        'cells' => [
          $item->toLink()->toString(),
          $category ? ($item->get('category')->getSetting('allowed_values')[$category] ?? $category) : '',
          $item->get('unit')->value,
          $item->get('item_type')->getSetting('allowed_values')[$item_type] ?? $item_type,
          $stock_display,
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
      '#attributes' => ['class' => ['hivelog-list-heading'], 'id' => 'products'],
      '#weight' => 30,
      'title' => [
        '#type' => 'html_tag',
        // H2 (task 0128) — a top-level apiary-page section.
        '#tag' => 'h2',
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
      ->setCacheMaxAge($this->calendarChecklistBuilder->secondsUntilNextIsoWeek());
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
    // Bound as an int rather than a PHP bool: PDO's SQLite driver casts an
    // unbound bool parameter to string ("" for FALSE), which never matches
    // an integer column, silently dropping every disabled row from the
    // enabled=0 filter.
    $enabled = $filters['enabled'] ?? '1';
    if ($enabled !== '') {
      $query->condition('enabled', $enabled === '1' ? 1 : 0);
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
   * Escapes LIKE wildcard characters for safe use inside a LIKE condition.
   *
   * Only used by the calendar filter (`applyFullCalendarFilters()`) now —
   * the hive filter's own copy moved to `HivelogHiveFilterForm::escapeLike()`
   * with the rest of that filter's extract/apply logic (task 0132).
   */
  protected function escapeLike(string $value): string {
    return addcslashes($value, '\\%_');
  }

}
