<?php

namespace Drupal\hivelog\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Queen;
use Drupal\hivelog\Form\HivelogCalendarFilterForm;
use Drupal\hivelog\Form\HivelogInspectionFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for Hive pages.
 */
class HiveController extends ControllerBase {

  /**
   * Default number of inspections shown per page in the embedded list.
   */
  public const INSPECTIONS_PER_PAGE = 20;

  /**
   * Pager element id for the embedded inspection table.
   */
  protected const INSPECTIONS_PAGER_ELEMENT = 0;

  /**
   * Default number of queen observations shown per page in the embedded list.
   */
  public const OBSERVATIONS_PER_PAGE = 20;

  /**
   * Pager element id for the embedded queen observations table.
   *
   * Distinct from INSPECTIONS_PAGER_ELEMENT since both tables paginate
   * independently on the same page (see buildHiveActivitySection()).
   */
  protected const OBSERVATIONS_PAGER_ELEMENT = 1;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a HiveController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    FormBuilderInterface $form_builder,
    FileUrlGeneratorInterface $file_url_generator,
    RequestStack $request_stack,
    RendererInterface $renderer,
  ) {
    // $entityTypeManager / $entityFormBuilder / $formBuilder are untyped
    // properties inherited from ControllerBase; assign them rather than
    // redeclaring with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->formBuilder = $form_builder;
    $this->fileUrlGenerator = $file_url_generator;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('form_builder'),
      $container->get('file_url_generator'),
      $container->get('request_stack'),
      $container->get('renderer'),
    );
  }

  /**
   * Provides the add form for a hive within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $hive = $this->entityTypeManager->getStorage('hive')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($hive, 'add');
  }

  /**
   * Displays a hive with its inspections.
   */
  public function view(Hive $hive) {
    $build = [];

    // Render the hive entity fields.
    $view_builder = $this->entityTypeManager->getViewBuilder('hive');
    $build['hive'] = $view_builder->view($hive);
    $build['hive']['#weight'] = 5;

    // Render a letterboxed weight histogram for the year of the most recent
    // inspection, based on the full (unpaginated, unfiltered) inspection set
    // so the summary chart is not affected by active filters or paging.
    // Placed above the Inspections heading/Add Inspection action so it
    // reads as a summary of the whole inspection history.
    [$histogram_points, $histogram_inspections, $axis_start, $axis_end] = $this->collectHistogramPoints($hive);
    $histogram = $this->buildWeightHistogram($histogram_points, $axis_start, $axis_end);
    if (!empty($histogram)) {
      $build['weight_histogram'] = $histogram + ['#weight' => 7];
    }

    // Queen section — rendered after the histogram so the histogram stays
    // visually on top of the page, but still above the inspection list so
    // the current queen is the last thing the reader sees before the
    // inspection history.
    $active_queen = $hive->getActiveQueen();
    $build['queen'] = $this->buildQueenSection($hive, $active_queen) + ['#weight' => 8];

    // Hive Activity: Inspections and Queen Observations side-by-side — the
    // two logs a beekeeper keeps during the same hive visit belong next to
    // each other, not on separate pages. Stacks to a single column below
    // the 768px breakpoint per the module's responsive convention
    // (ADR-0011).
    [$inspections_column, $inspections] = $this->buildInspectionsColumn($hive);
    [$observations_column, $observations] = $this->buildObservationsColumn($hive, $active_queen);
    $build['hive_activity'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-activity-columns']],
      '#attached' => ['library' => ['hivelog/activity_columns']],
      '#weight' => 10,
      'inspections' => $inspections_column,
      'observations' => $observations_column,
    ];

    // Attached pictures, rendered in a grid below the inspection list.
    $images = $this->buildImagesGrid($hive);
    if (!empty($images)) {
      $build['images'] = $images + ['#weight' => 20];
    }

    // Seasonal Calendar checklist: cross-references the apiary's enabled
    // CalendarAction rows against this hive's own logs for a selected
    // year, defaulting to unreported items only (see ADR-0025). The filter
    // form lets a beekeeper switch status (Unreported/Done/Ignored/All)
    // and year (previous/current/next) — switching to next year, before
    // any logs exist for it, is what makes "preview the coming year's
    // pending items" work for free.
    //
    // The current ISO week is surfaced in the heading, and unreported
    // rows get a Due now/Overdue/Upcoming suffix computed against it, so
    // it's never ambiguous whether an action needs attention right now.
    // Only meaningful when viewing the current year — previewing a future
    // year would trivially label everything "Upcoming", which isn't
    // useful information, so the suffix is omitted there.
    $current_week = (int) date('W');
    $current_year = (int) date('Y');
    $build['calendar_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 25,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Seasonal Calendar (current week: @week)', ['@week' => $current_week]),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
    ];

    $build['calendar_filter'] = $this->formBuilder->getForm(
      HivelogCalendarFilterForm::class,
      Url::fromRoute('entity.hive.canonical', ['hive' => $hive->id()])
    );
    $build['calendar_filter']['#weight'] = 26;

    $calendar_filters = $this->extractCalendarFilters();
    $checklist = $this->buildCalendarChecklist($hive, $calendar_filters['year'], $calendar_filters['status']);
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
                'url' => Url::fromRoute('hivelog.hive_action_log.add', [
                  'hive' => $hive->id(),
                  'calendar_action' => $calendar_action->id(),
                ], ['query' => ['status' => 'done']])->toString(),
                'variant' => 'primary',
              ],
              [
                'label' => (string) $this->t('Report Ignored'),
                'url' => Url::fromRoute('hivelog.hive_action_log.add', [
                  'hive' => $hive->id(),
                  'calendar_action' => $calendar_action->id(),
                ], ['query' => ['status' => 'ignored']])->toString(),
              ],
            ],
          ],
        ];
      }
      else {
        // Already reported: offer to view (and, if permitted, edit) the
        // log that reported it, plus a direct link to a linked inspection
        // if reporting "done" created one (task 0023).
        $buttons = [];
        if ($log) {
          $buttons[] = ['label' => (string) $this->t('View Log'), 'url' => $log->toUrl('canonical')->toString()];
          if ($log->access('update')) {
            $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $log->toUrl('edit-form')->toString()];
          }
          $linked_inspection = $log->get('inspection')->entity;
          if ($linked_inspection && $linked_inspection->access('view')) {
            $buttons[] = [
              'label' => (string) $this->t('View Inspection'),
              'url' => $linked_inspection->toUrl('canonical')->toString(),
            ];
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
      '#weight' => 27,
    ];

    // Explicit cache metadata.
    // - url.query_args: inspection + observation pager state, and now the
    //   calendar checklist's status/year filter, are all encoded in the
    //   query string.
    // - user.permissions: the inspection/observation lists respect
    //   per-entity access checks, so cache entries must vary by effective
    //   permissions.
    // - Hive entity tags: invalidate on hive update/delete.
    // - Inspection list cache tag + every rendered inspection's tags:
    //   invalidate on any inspection change.
    // - Queen observation list cache tag + every rendered observation's
    //   tags: invalidate on any observation change.
    // - Calendar action / hive action log list cache tags + every rendered
    //   calendar action/log's own tags: invalidate on any change to either,
    //   since the checklist is computed by cross-referencing both on read.
    // - max-age: the heading's "current week" and each unreported row's
    //   Due now/Overdue/Upcoming suffix are computed from date('W')/date('Y')
    //   ("now"), so the render must not be cached past the moment the ISO
    //   week actually changes, or it would show a stale week/timing after
    //   that boundary passes.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($hive)
      ->addCacheTags($this->entityTypeManager->getDefinition('hive_inspection')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('queen')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('queen_observation')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('calendar_action')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('hive_action_log')->getListCacheTags())
      ->setCacheMaxAge($this->secondsUntilNextIsoWeek());
    if ($active_queen) {
      $cache->addCacheableDependency($active_queen);
    }
    foreach ($inspections as $inspection) {
      $cache->addCacheableDependency($inspection);
    }
    foreach ($observations as $observation) {
      $cache->addCacheableDependency($observation);
    }
    // The histogram is derived from a separate unfiltered query, so include
    // those inspections' cache tags too.
    foreach ($histogram_inspections as $inspection) {
      $cache->addCacheableDependency($inspection);
    }
    foreach ($checklist['rows'] as $entry) {
      $cache->addCacheableDependency($entry['calendar_action']);
      if ($entry['log']) {
        $cache->addCacheableDependency($entry['log']);
        $linked_inspection = $entry['log']->get('inspection')->entity;
        if ($linked_inspection) {
          $cache->addCacheableDependency($linked_inspection);
        }
      }
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the hive view page.
   */
  public function title(Hive $hive) {
    return $hive->label();
  }

  /**
   * Builds the queen section shown on the hive view page.
   *
   * When an active queen is present, summarise its key attributes and
   * expose View / Edit links. Otherwise, invite the user to add a queen
   * via the hive-scoped add route. Either way, any other queens the hive
   * has previously had are listed below as history (see
   * Hive::getQueens()) so retiring a queen doesn't erase her from the
   * hive's story.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being rendered.
   * @param \Drupal\hivelog\Entity\Queen|null $queen
   *   The hive's currently active queen, if any.
   */
  protected function buildQueenSection(Hive $hive, ?Queen $queen): array {
    $section = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Queen'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
    ];

    if ($queen) {
      $colour = $queen->get('queen_colour')->value;
      $colour_label = $colour
        ? ($queen->get('queen_colour')->getSetting('allowed_values')[$colour] ?? $colour)
        : $this->t('Not set');

      $breed = $queen->get('breed')->value;
      $breed_label = $breed
        ? ($queen->get('breed')->getSetting('allowed_values')[$breed] ?? $breed)
        : $this->t('Not set');

      $section['details'] = [
        '#type' => 'table',
        '#header' => [$this->t('Field'), $this->t('Value')],
        '#attributes' => ['class' => ['hivelog-queen-table']],
        '#attached' => ['library' => ['hivelog/tables']],
        '#rows' => [
          [$this->t('Queen ID'), $queen->toLink()->toString()],
          [$this->t('Breed'), $breed_label],
          [$this->t('Colour'), $colour_label],
          [$this->t('Year'), $queen->get('queen_year')->value ?: $this->t('Not set')],
          [$this->t('Introduced'), $queen->get('introduction_date')->value ?: $this->t('Not set')],
        ],
      ];
      // Note: no "Add Observation" button here — it lives on the Queen
      // Observations column's own heading instead (see
      // buildObservationsColumn()), alongside the observations it adds to.
      $section['edit'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Edit Queen'),
          'url' => $queen->toUrl('edit-form')->toString(),
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ];
    }
    else {
      $section['empty'] = [
        '#markup' => '<p>' . $this->t('No active queen is recorded for this hive.') . '</p>',
      ];
      $section['add'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Add Queen'),
          'url' => Url::fromRoute('hivelog.queen.add', ['hive' => $hive->id()])->toString(),
          'variant' => 'primary',
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ];
    }

    $history = array_values(array_filter(
      $hive->getQueens(),
      fn(Queen $candidate) => !$queen || $candidate->id() !== $queen->id()
    ));
    if ($history) {
      $section['history'] = $this->buildQueenHistorySection($history);
    }

    return $section;
  }

  /**
   * Builds the "Previous Queens" history table shown below the queen section.
   *
   * @param \Drupal\hivelog\Entity\Queen[] $queens
   *   Non-current queens the hive has had, most recent first.
   */
  protected function buildQueenHistorySection(array $queens): array {
    $rows = [];
    foreach ($queens as $queen) {
      $breed = $queen->get('breed')->value;
      $breed_label = $breed
        ? ($queen->get('breed')->getSetting('allowed_values')[$breed] ?? $breed)
        : $this->t('Not set');
      $status = $queen->get('status')->value;
      $status_label = $queen->get('status')->getSetting('allowed_values')[$status] ?? $status;
      $rows[] = [
        $queen->toLink()->toString(),
        $breed_label,
        $status_label,
        $queen->get('introduction_date')->value ?: $this->t('Not set'),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-queen-history']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Previous Queens'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Queen ID'), $this->t('Breed'), $this->t('Status'), $this->t('Introduced')],
        '#rows' => $rows,
        '#attributes' => ['class' => ['hivelog-queen-table']],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds the Inspections column of the hive activity section.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being rendered.
   *
   * @return array{0: array, 1: \Drupal\hivelog\Entity\HiveInspection[]}
   *   Tuple of [render array, loaded inspection entities for cache deps].
   */
  protected function buildInspectionsColumn(Hive $hive): array {
    $filters = $this->extractInspectionFilters();
    $query = $this->entityTypeManager
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive->id())
      ->sort('inspection_date', 'DESC')
      ->pager(static::INSPECTIONS_PER_PAGE, static::INSPECTIONS_PAGER_ELEMENT);
    $this->applyInspectionFilters($query, $filters);
    $inspection_ids = $query->execute();

    $inspections = $inspection_ids
      ? $this->entityTypeManager->getStorage('hive_inspection')->loadMultiple($inspection_ids)
      : [];

    $header = [
      $this->t('Date'),
      $this->t('Weight'),
      $this->t('Queen'),
      $this->t('Brood'),
      $this->t('Honey'),
      $this->t('Temperament'),
      $this->t('Population'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($inspections as $inspection) {
      $weight = $inspection->get('weight')->value;
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => [
            ['label' => (string) $this->t('View'), 'url' => $inspection->toUrl('canonical')->toString()],
            ['label' => (string) $this->t('Edit'), 'url' => $inspection->toUrl('edit-form')->toString()],
            [
              'label' => (string) $this->t('Delete'),
              'url' => $inspection->toUrl('delete-form')->toString(),
              'variant' => 'danger',
            ],
          ],
        ],
      ];
      $rows[] = [
        'cells' => [
          (string) ($inspection->get('inspection_date')->value ?: $this->t('N/A')),
          $weight !== NULL ? $weight . ' kg' : '',
          (string) ($inspection->get('queen_seen')->value ? $this->t('Yes') : $this->t('No')),
          $inspection->get('brood_pattern')->value ? $inspection->get('brood_pattern')->getSetting('allowed_values')[$inspection->get('brood_pattern')->value] ?? '' : '',
          $inspection->get('honey_stores')->value ? $inspection->get('honey_stores')->getSetting('allowed_values')[$inspection->get('honey_stores')->value] ?? '' : '',
          $inspection->get('temperament')->value ? $inspection->get('temperament')->getSetting('allowed_values')[$inspection->get('temperament')->value] ?? '' : '',
          $inspection->get('population')->value ? $inspection->get('population')->getSetting('allowed_values')[$inspection->get('population')->value] ?? '' : '',
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-activity-column']],
      'heading' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Inspections'),
          '#attributes' => ['class' => ['hivelog-list-heading__title']],
        ],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Inspection'),
            'url' => Url::fromRoute('hivelog.inspection.add', ['hive' => $hive->id()])->toString(),
            'variant' => 'primary',
            'extra_classes' => 'hivelog-list-heading__action',
          ],
        ],
      ],
      'filter' => $this->formBuilder->getForm(HivelogInspectionFilterForm::class, $hive),
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $header),
          'rows' => $rows,
          'empty_message' => (string) (!empty($filters)
            ? $this->t('No inspections match the current filters.')
            : $this->t('No inspections have been recorded for this hive yet.')),
        ],
      ],
      'pager' => [
        '#type' => 'pager',
        '#element' => static::INSPECTIONS_PAGER_ELEMENT,
      ],
    ];

    return [$build, $inspections];
  }

  /**
   * Builds the Queen Observations column of the hive activity section.
   *
   * Aggregates observations across every queen the hive has ever had (see
   * Hive::getQueens()), not just the current one, so the column keeps its
   * history when a queen is replaced — mirroring how Inspections continue
   * across queen changes.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being rendered.
   * @param \Drupal\hivelog\Entity\Queen|null $active_queen
   *   The hive's currently active queen, if any — only she can receive a
   *   new observation, so the "Add Observation" button only appears when
   *   this is set.
   *
   * @return array{0: array, 1: \Drupal\hivelog\Entity\QueenObservation[]}
   *   Tuple of [render array, loaded observation entities for cache deps].
   */
  protected function buildObservationsColumn(Hive $hive, ?Queen $active_queen): array {
    $queen_ids = array_map(fn(Queen $queen) => $queen->id(), $hive->getQueens());

    $observations = [];
    if ($queen_ids) {
      $query = $this->entityTypeManager
        ->getStorage('queen_observation')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('queen', $queen_ids, 'IN')
        ->sort('observation_date', 'DESC')
        ->pager(static::OBSERVATIONS_PER_PAGE, static::OBSERVATIONS_PAGER_ELEMENT);
      $observation_ids = $query->execute();
      $observations = $observation_ids
        ? $this->entityTypeManager->getStorage('queen_observation')->loadMultiple($observation_ids)
        : [];
    }

    $header = [
      $this->t('Date'),
      $this->t('Queen'),
      $this->t('Health'),
      $this->t('Temperament'),
      $this->t('Active'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($observations as $observation) {
      $health = $observation->get('health')->value;
      $temperament = $observation->get('temperament')->value;
      $observed_queen = $observation->get('queen')->entity;
      $actions = [
        '#type' => 'component',
        '#component' => 'hivelog:button-group',
        '#props' => [
          'buttons' => [
            ['label' => (string) $this->t('View'), 'url' => $observation->toUrl('canonical')->toString()],
            ['label' => (string) $this->t('Edit'), 'url' => $observation->toUrl('edit-form')->toString()],
            [
              'label' => (string) $this->t('Delete'),
              'url' => $observation->toUrl('delete-form')->toString(),
              'variant' => 'danger',
            ],
          ],
        ],
      ];
      $rows[] = [
        'cells' => [
          $observation->toLink($observation->get('observation_date')->value ?: $this->t('N/A'))->toString(),
          $observed_queen ? $observed_queen->toLink()->toString() : '',
          $health ? ($observation->get('health')->getSetting('allowed_values')[$health] ?? $health) : '',
          $temperament ? ($observation->get('temperament')->getSetting('allowed_values')[$temperament] ?? $temperament) : '',
          (string) ($observation->get('active')->value ? $this->t('Yes') : $this->t('No')),
          $this->renderer->renderInIsolation($actions),
        ],
      ];
    }

    $heading = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Queen Observations'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
    ];
    if ($active_queen) {
      $heading['add'] = [
        '#type' => 'component',
        '#component' => 'hivelog:button',
        '#props' => [
          'label' => (string) $this->t('Add Observation'),
          'url' => Url::fromRoute('hivelog.queen_observation.add', ['queen' => $active_queen->id()])->toString(),
          'variant' => 'primary',
          'extra_classes' => 'hivelog-list-heading__action',
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-activity-column']],
      'heading' => $heading,
      'table' => [
        '#type' => 'component',
        '#component' => 'hivelog:entity-table',
        '#props' => [
          'headers' => array_map('strval', $header),
          'rows' => $rows,
          'empty_message' => (string) $this->t('No queen observations have been recorded for this hive yet.'),
        ],
      ],
      'pager' => [
        '#type' => 'pager',
        '#element' => static::OBSERVATIONS_PAGER_ELEMENT,
      ],
    ];

    return [$build, $observations];
  }

  /**
   * Extracts inspection filter values from the current request.
   *
   * @return array<string, string>
   *   Associative array keyed by filter name. Only non-empty values are
   *   included.
   */
  protected function extractInspectionFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return [];
    }
    $filters = [];
    foreach (['date_from', 'date_to', 'queen_seen', 'brood_pattern'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }
    return $filters;
  }

  /**
   * Applies inspection filters to an entity query.
   */
  protected function applyInspectionFilters(QueryInterface $query, array $filters): void {
    if (isset($filters['date_from'])) {
      $query->condition('inspection_date', $filters['date_from'], '>=');
    }
    if (isset($filters['date_to'])) {
      $query->condition('inspection_date', $filters['date_to'], '<=');
    }
    if (isset($filters['queen_seen']) && in_array($filters['queen_seen'], ['0', '1'], TRUE)) {
      $query->condition('queen_seen', (int) $filters['queen_seen']);
    }
    if (isset($filters['brood_pattern'])) {
      $query->condition('brood_pattern', $filters['brood_pattern']);
    }
  }

  /**
   * Builds the seasonal calendar checklist rows for a hive.
   *
   * Cross-references every `enabled` `CalendarAction` belonging to the
   * hive's apiary against the hive's own `HiveActionLog` rows for the
   * given year — no rows are ever pre-materialised (see ADR-0025). Absence
   * of a log, or one with `status = pending`, means "unreported". When
   * multiple logs exist for the same `(hive, calendar_action, year)` (this
   * is allowed by design), the most recently changed one wins for display
   * purposes.
   *
   * This task hard-codes the `$status_filter` caller argument to `pending`
   * and `$year` to the current year; task 0021 adds a real filter form
   * that overrides both from the query string, and "all" as a
   * `$status_filter` value to show every row regardless of status.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive to build a checklist for.
   * @param int $year
   *   Which annual occurrence of each calendar action to check against.
   * @param string $status_filter
   *   One of `pending`, `done`, `ignored`, or `all`.
   *
   * @return array
   *   An array with two keys: `total_enabled` is the count of enabled,
   *   hive-scoped calendar actions on the hive's apiary *before* the status
   *   filter is applied — used to tell "nothing pending" apart from "no
   *   calendar actions exist at all" for the empty-state message. `rows` is
   *   an array keyed by calendar action id, each entry an associative array
   *   with `calendar_action` (the \Drupal\hivelog\Entity\CalendarAction),
   *   `log` (the matching \Drupal\hivelog\Entity\HiveActionLog, or NULL if
   *   unreported), and `status` (the effective status string used for
   *   filtering/display). Apiary-scoped calendar actions (task 0027) are
   *   excluded entirely — they never appear on any hive's checklist.
   */
  protected function buildCalendarChecklist(Hive $hive, int $year, string $status_filter): array {
    $apiary_id = $hive->get('apiary')->target_id;
    if (!$apiary_id) {
      return ['total_enabled' => 0, 'rows' => []];
    }

    $calendar_action_ids = $this->entityTypeManager
      ->getStorage('calendar_action')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_id)
      ->condition('enabled', TRUE)
      ->condition('scope', 'hive')
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

    // Load every log for this hive + year against these calendar actions in
    // one query, then index by calendar_action id — last-changed wins if
    // more than one log exists for the same calendar action.
    $log_ids = $this->entityTypeManager
      ->getStorage('hive_action_log')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive->id())
      ->condition('calendar_action', array_keys($calendar_actions), 'IN')
      ->condition('year', $year)
      ->sort('changed', 'ASC')
      ->execute();

    $logs_by_action = [];
    if ($log_ids) {
      foreach ($this->entityTypeManager->getStorage('hive_action_log')->loadMultiple($log_ids) as $log) {
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
   * ADR-0025. Unlike `extractInspectionFilters()`, this always returns an
   * effective value for both keys (there is no "no filter applied" state
   * to fall back to).
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
   *   Count of enabled calendar actions on the hive's apiary, before the
   *   status filter is applied (from `buildCalendarChecklist()`).
   * @param string $status_filter
   *   The active status filter (`pending`/`done`/`ignored`/`all`).
   */
  protected function calendarChecklistEmptyMessage(int $total_enabled, string $status_filter): TranslatableMarkup {
    if ($total_enabled === 0) {
      return $this->t('This apiary has no calendar actions set up yet.');
    }

    $messages = [
      'pending' => $this->t('No pending seasonal actions for this hive.'),
      'done' => $this->t('No actions have been reported as done for this hive.'),
      'ignored' => $this->t('No actions have been reported as ignored for this hive.'),
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

  /**
   * Default start of the histogram's beekeeping season window (mm-dd).
   */
  protected const HISTOGRAM_SEASON_START = '05-01';

  /**
   * Default end of the histogram's beekeeping season window (mm-dd).
   */
  protected const HISTOGRAM_SEASON_END = '08-31';

  /**
   * Collects histogram data points from the full inspection set for a hive.
   *
   * The histogram summarises the year of the most recent inspection and is
   * deliberately not restricted by filters or pagination. The axis range is
   * anchored to the configured beekeeping season window (01/05–31/08) and
   * widened out to the first/last inspection of the year when they fall
   * outside that window.
   *
   * @return array{0: array<int, array{date:string, mmdd:string, weight:float, year:string}>, 1: \Drupal\hivelog\Entity\HiveInspection[], 2: ?string, 3: ?string}
   *   Tuple of [histogram data points, inspections loaded for the histogram,
   *   axis start date (YYYY-MM-DD), axis end date (YYYY-MM-DD)].
   */
  protected function collectHistogramPoints(Hive $hive): array {
    $inspection_ids = $this->entityTypeManager
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive->id())
      ->sort('inspection_date', 'DESC')
      ->execute();

    if (!$inspection_ids) {
      return [[], [], NULL, NULL];
    }
    $inspections = $this->entityTypeManager
      ->getStorage('hive_inspection')
      ->loadMultiple($inspection_ids);

    // Identify the most recent inspection date to determine the target year.
    $most_recent = NULL;
    foreach ($inspections as $inspection) {
      $date = $inspection->get('inspection_date')->value;
      if ($date && (!$most_recent || $date > $most_recent)) {
        $most_recent = $date;
      }
    }
    if (!$most_recent) {
      return [[], $inspections, NULL, NULL];
    }
    $year = substr($most_recent, 0, 4);

    $points = [];
    foreach ($inspections as $inspection) {
      $date = $inspection->get('inspection_date')->value;
      $weight = $inspection->get('weight')->value;
      if (!$date || $weight === NULL || $weight === '') {
        continue;
      }
      if (substr($date, 0, 4) !== $year) {
        continue;
      }
      $points[] = [
        'date' => $date,
        'mmdd' => substr($date, 5, 2) . '/' . substr($date, 8, 2),
        'weight' => (float) $weight,
        'year' => $year,
      ];
    }

    usort($points, fn($a, $b) => strcmp($a['date'], $b['date']));

    if (empty($points)) {
      return [[], $inspections, NULL, NULL];
    }

    // Anchor the axis to the beekeeping season and widen only when the data
    // itself extends past those bounds. YYYY-MM-DD strings compare
    // lexicographically so no DateTime parsing is required here.
    $season_start = $year . '-' . static::HISTOGRAM_SEASON_START;
    $season_end = $year . '-' . static::HISTOGRAM_SEASON_END;
    $first_date = $points[0]['date'];
    $last_date = $points[count($points) - 1]['date'];
    $axis_start = $first_date < $season_start ? $first_date : $season_start;
    $axis_end = $last_date > $season_end ? $last_date : $season_end;

    return [$points, $inspections, $axis_start, $axis_end];
  }

  /**
   * Builds a grid of hive pictures with links to the full-size image.
   */
  protected function buildImagesGrid(Hive $hive): array {
    if ($hive->get('images')->isEmpty()) {
      return [];
    }

    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('thumbnail');

    $items = [];
    foreach ($hive->get('images') as $delta => $item) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $item->entity;
      if (!$file) {
        continue;
      }

      $full_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      $thumb_url = $image_style ? $image_style->buildUrl($file->getFileUri()) : $full_url;
      $alt = (string) ($item->alt ?? '');

      $items[] = [
        'full_url' => $full_url,
        'thumb_url' => $thumb_url,
        'alt' => $alt,
      ];
    }

    if (empty($items)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-hive-photos'],
      ],
      '#attached' => [
        'library' => ['hivelog/images'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Pictures'),
      ],
      'grid' => [
        '#type' => 'inline_template',
        '#template' => '<div class="hivelog-photos-grid">{% for item in items %}<a class="hivelog-photos-grid__item" href="{{ item.full_url }}" target="_blank" rel="noopener"><img src="{{ item.thumb_url }}" alt="{{ item.alt }}" loading="lazy" /></a>{% endfor %}</div>',
        '#context' => [
          'items' => $items,
        ],
      ],
    ];
  }

  /**
   * Builds a letterboxed vertical histogram of inspection weights.
   *
   * Bars are positioned proportionally along a calendar-time X axis that
   * spans [axis_start, axis_end]. Month start tick marks are rendered
   * beneath the axis to orient the reader.
   *
   * @param array<int, array{date:string, mmdd:string, weight:float, year?:string}> $points
   *   Pre-collected data points for the target year.
   * @param string|null $axis_start
   *   The left edge of the X axis, as a YYYY-MM-DD date.
   * @param string|null $axis_end
   *   The right edge of the X axis, as a YYYY-MM-DD date.
   *
   * @return array
   *   A render array for the histogram, or an empty array if there is nothing
   *   to display.
   */
  protected function buildWeightHistogram(array $points, ?string $axis_start = NULL, ?string $axis_end = NULL): array {
    if (empty($points) || !$axis_start || !$axis_end) {
      return [];
    }

    $year = $points[0]['year'] ?? substr($points[0]['date'], 0, 4);

    // SVG layout constants. Padding_x is wider than before to make room for
    // data points that sit right on the axis edges.
    $svg_width = 800;
    $svg_height = 300;
    $padding_top = 40;
    $padding_bottom = 50;
    $padding_x = 40;
    $chart_height = $svg_height - $padding_top - $padding_bottom;
    $chart_width = $svg_width - (2 * $padding_x);
    $max_weight = max(array_column($points, 'weight')) ?: 1.0;
    $axis_y = $padding_top + $chart_height;

    // Convert the axis bounds and each point's date to day-of-year. Since
    // collectHistogramPoints() restricts points to a single year, using
    // day-of-year (0-based) keeps the math simple and avoids timestamp /
    // timezone concerns.
    $start_doy = $this->dayOfYear($axis_start);
    $end_doy = $this->dayOfYear($axis_end);
    $total_days = max($end_doy - $start_doy, 1);

    // Fixed bar width, shrunk if two adjacent points sit very close together.
    $bar_width = 18;
    if (count($points) > 1) {
      $min_gap_px = $chart_width;
      $prev_doy = NULL;
      foreach ($points as $point) {
        $doy = $this->dayOfYear($point['date']);
        if ($prev_doy !== NULL) {
          $gap_px = (($doy - $prev_doy) / $total_days) * $chart_width;
          if ($gap_px > 0) {
            $min_gap_px = min($min_gap_px, $gap_px);
          }
        }
        $prev_doy = $doy;
      }
      $bar_width = (int) max(4, min($bar_width, $min_gap_px * 0.9));
    }

    $bars = [];
    foreach ($points as $point) {
      $doy = $this->dayOfYear($point['date']);
      $fraction = ($doy - $start_doy) / $total_days;
      $center_x = $padding_x + ($fraction * $chart_width);
      $bar_height = ($point['weight'] / $max_weight) * $chart_height;
      $bar_y = $padding_top + ($chart_height - $bar_height);
      $bars[] = [
        'x' => (int) round($center_x - ($bar_width / 2)),
        'y' => (int) round($bar_y),
        'height' => (int) round($bar_height),
        'label_x' => (int) round($center_x),
        'value_y' => max((int) round($bar_y) - 6, $padding_top - 4),
        'date_y' => $axis_y + 14,
        'mmdd' => $point['mmdd'],
        'weight_label' => rtrim(rtrim(number_format($point['weight'], 2, '.', ''), '0'), '.') . ' kg',
      ];
    }

    // Month tick marks: for each month whose first day falls within the
    // axis range, place a short tick below the axis and render a three
    // letter month abbreviation below it.
    $ticks = [];
    $month_names = [
      1 => 'Jan',
      2 => 'Feb',
      3 => 'Mar',
      4 => 'Apr',
      5 => 'May',
      6 => 'Jun',
      7 => 'Jul',
      8 => 'Aug',
      9 => 'Sep',
      10 => 'Oct',
      11 => 'Nov',
      12 => 'Dec',
    ];
    $start_month = (int) substr($axis_start, 5, 2);
    $end_month = (int) substr($axis_end, 5, 2);
    for ($m = $start_month; $m <= min($end_month + 1, 12); $m++) {
      $tick_date = sprintf('%s-%02d-01', $year, $m);
      if ($tick_date < $axis_start || $tick_date > $axis_end) {
        continue;
      }
      $doy = $this->dayOfYear($tick_date);
      $fraction = ($doy - $start_doy) / $total_days;
      $x = (int) round($padding_x + $fraction * $chart_width);
      $ticks[] = [
        'x' => $x,
        'y1' => $axis_y,
        'y2' => $axis_y + 4,
        'label_x' => $x,
        'label_y' => $axis_y + 32,
        'label' => $month_names[$m] ?? '',
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'hivelog-weight-histogram',
          'hivelog-weight-histogram--letterboxed',
        ],
      ],
      '#attached' => [
        'library' => ['hivelog/weight_histogram'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Inspection weights for @year', ['@year' => $year]),
        '#attributes' => ['class' => ['hivelog-weight-histogram__title']],
      ],
      'chart' => [
        '#type' => 'inline_template',
        '#template' => '<div class="hivelog-weight-histogram__frame"><svg viewBox="0 0 {{ svg_width }} {{ svg_height }}" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ label }}"><title>{{ label }}</title>{% for bar in bars %}<rect class="hivelog-weight-histogram__bar" x="{{ bar.x }}" y="{{ bar.y }}" width="{{ bar_width }}" height="{{ bar.height }}" fill="#f2a42e" /><text class="hivelog-weight-histogram__value" x="{{ bar.label_x }}" y="{{ bar.value_y }}" text-anchor="middle" font-size="12" fill="#333">{{ bar.weight_label }}</text><text class="hivelog-weight-histogram__date" x="{{ bar.label_x }}" y="{{ bar.date_y }}" text-anchor="middle" font-size="11" fill="#333">{{ bar.mmdd }}</text>{% endfor %}<line x1="{{ padding_x }}" y1="{{ axis_y }}" x2="{{ svg_width - padding_x }}" y2="{{ axis_y }}" stroke="#666" stroke-width="1" />{% for tick in ticks %}<line class="hivelog-weight-histogram__tick" x1="{{ tick.x }}" y1="{{ tick.y1 }}" x2="{{ tick.x }}" y2="{{ tick.y2 }}" stroke="#666" stroke-width="1" /><text class="hivelog-weight-histogram__month" x="{{ tick.label_x }}" y="{{ tick.label_y }}" text-anchor="middle" font-size="11" fill="#666">{{ tick.label }}</text>{% endfor %}</svg></div>',
        '#context' => [
          'label' => $this->t('Inspection weights for @year', ['@year' => $year]),
          'svg_width' => $svg_width,
          'svg_height' => $svg_height,
          'padding_x' => $padding_x,
          'axis_y' => $axis_y,
          'bar_width' => $bar_width,
          'bars' => $bars,
          'ticks' => $ticks,
        ],
      ],
    ];
  }

  /**
   * Returns the zero-based day of the year for a YYYY-MM-DD date.
   */
  protected function dayOfYear(string $date): int {
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($dt === FALSE) {
      return 0;
    }
    return (int) $dt->format('z');
  }

}
