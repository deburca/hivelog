<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
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
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    FormBuilderInterface $form_builder,
    FileUrlGeneratorInterface $file_url_generator,
    RequestStack $request_stack,
  ) {
    // $entityTypeManager / $entityFormBuilder / $formBuilder are untyped
    // properties inherited from ControllerBase; assign them rather than
    // redeclaring with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->formBuilder = $form_builder;
    $this->fileUrlGenerator = $file_url_generator;
    $this->requestStack = $request_stack;
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

    // Heading row: the "Inspections" title on the left, the Add Inspection
    // action on the right. Placing the action here (rather than inline
    // with the filter form below) keeps it at the top-right of the list
    // section where it logically belongs.
    $build['inspections_heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => 10,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Inspections'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Inspection'),
        '#url' => Url::fromRoute('hivelog.inspection.add', ['hive' => $hive->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'hivelog-list-heading__action'],
        ],
      ],
    ];

    // Filter form sits on its own row below the heading. The form's own
    // CSS pushes the Filter / Reset buttons to the right-hand side of the
    // filter row.
    $build['inspections_filter'] = $this->formBuilder->getForm(HivelogInspectionFilterForm::class, $hive);
    $build['inspections_filter']['#weight'] = 11;

    // Build the paginated + filtered inspection query.
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
      $rows[] = [
        $inspection->get('inspection_date')->value ?: $this->t('N/A'),
        $weight !== NULL ? $weight . ' kg' : '',
        $inspection->get('queen_seen')->value ? $this->t('Yes') : $this->t('No'),
        $inspection->get('brood_pattern')->value ? $inspection->get('brood_pattern')->getSetting('allowed_values')[$inspection->get('brood_pattern')->value] ?? '' : '',
        $inspection->get('honey_stores')->value ? $inspection->get('honey_stores')->getSetting('allowed_values')[$inspection->get('honey_stores')->value] ?? '' : '',
        $inspection->get('temperament')->value ? $inspection->get('temperament')->getSetting('allowed_values')[$inspection->get('temperament')->value] ?? '' : '',
        $inspection->get('population')->value ? $inspection->get('population')->getSetting('allowed_values')[$inspection->get('population')->value] ?? '' : '',
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'view' => [
                'title' => $this->t('View'),
                'url' => $inspection->toUrl('canonical'),
              ],
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => $inspection->toUrl('edit-form'),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => $inspection->toUrl('delete-form'),
              ],
            ],
          ],
        ],
      ];
    }

    $build['inspections_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => !empty($filters)
        ? $this->t('No inspections match the current filters.')
        : $this->t('No inspections have been recorded for this hive yet.'),
      '#weight' => 12,
      '#attributes' => ['class' => ['hivelog-table']],
      '#attached' => ['library' => ['hivelog/tables']],
    ];

    $build['inspections_pager'] = [
      '#type' => 'pager',
      '#element' => static::INSPECTIONS_PAGER_ELEMENT,
      '#weight' => 13,
    ];

    // Attached pictures, rendered in a grid below the inspection list.
    $images = $this->buildImagesGrid($hive);
    if (!empty($images)) {
      $build['images'] = $images + ['#weight' => 20];
    }

    // Explicit cache metadata.
    // - url.query_args: filter + pager state is encoded in the query string.
    // - user.permissions: the inspection list respects per-entity access
    //   checks, so cache entries must vary by effective permissions.
    // - Hive entity tags: invalidate on hive update/delete.
    // - Inspection list cache tag + every rendered inspection's tags:
    //   invalidate on any inspection change.
    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($hive)
      ->addCacheTags($this->entityTypeManager->getDefinition('hive_inspection')->getListCacheTags())
      ->addCacheTags($this->entityTypeManager->getDefinition('queen')->getListCacheTags());
    if ($active_queen) {
      $cache->addCacheableDependency($active_queen);
    }
    foreach ($inspections as $inspection) {
      $cache->addCacheableDependency($inspection);
    }
    // The histogram is derived from a separate unfiltered query, so include
    // those inspections' cache tags too.
    foreach ($histogram_inspections as $inspection) {
      $cache->addCacheableDependency($inspection);
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
   * via the hive-scoped add route.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being rendered.
   * @param \Drupal\hivelog\Entity\Queen|null $queen
   *   The hive's currently active queen, if any.
   */
  protected function buildQueenSection(Hive $hive, $queen): array {
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

      $section['details'] = [
        '#type' => 'table',
        '#header' => [$this->t('Field'), $this->t('Value')],
        '#attributes' => ['class' => ['hivelog-table']],
        '#attached' => ['library' => ['hivelog/tables']],
        '#rows' => [
          [$this->t('Queen ID'), $queen->toLink()->toString()],
          [$this->t('Colour'), $colour_label],
          [$this->t('Year'), $queen->get('queen_year')->value ?: $this->t('Not set')],
          [$this->t('Introduced'), $queen->get('introduction_date')->value ?: $this->t('Not set')],
        ],
      ];
      $section['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit Queen'),
        '#url' => $queen->toUrl('edit-form'),
        '#attributes' => [
          'class' => ['button', 'hivelog-list-heading__action'],
        ],
      ];
      $section['add_observation'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Observation'),
        '#url' => Url::fromRoute('hivelog.queen_observation.add', ['queen' => $queen->id()]),
        '#attributes' => [
          'class' => ['button', 'hivelog-list-heading__action'],
        ],
      ];
    }
    else {
      $section['empty'] = [
        '#markup' => '<p>' . $this->t('No active queen is recorded for this hive.') . '</p>',
      ];
      $section['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Queen'),
        '#url' => Url::fromRoute('hivelog.queen.add', ['hive' => $hive->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'hivelog-list-heading__action'],
        ],
      ];
    }

    return $section;
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
      1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
      5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
      9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
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
