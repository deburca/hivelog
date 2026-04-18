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
    [$histogram_points, $histogram_inspections] = $this->collectHistogramPoints($hive);
    $histogram = $this->buildWeightHistogram($histogram_points);
    if (!empty($histogram)) {
      $build['weight_histogram'] = $histogram + ['#weight' => 7];
    }

    // Add inspections heading and action link.
    $build['inspections_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Inspections'),
      '#weight' => 10,
    ];

    $build['add_inspection'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Inspection'),
      '#url' => Url::fromRoute('hivelog.inspection.add', ['hive' => $hive->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => 11,
    ];

    // Filter form.
    $build['inspections_filter'] = $this->formBuilder->getForm(HivelogInspectionFilterForm::class, $hive);
    $build['inspections_filter']['#weight'] = 11.5;

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
      ->addCacheTags($this->entityTypeManager->getDefinition('hive_inspection')->getListCacheTags());
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
   * Collects histogram data points from the full inspection set for a hive.
   *
   * The histogram summarises the year of the most recent inspection and is
   * deliberately not restricted by filters or pagination.
   *
   * @return array{0: array<int, array{date:string, mmdd:string, weight:float, year:string}>, 1: \Drupal\hivelog\Entity\HiveInspection[]}
   *   Tuple of [histogram data points, inspections loaded for the histogram].
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
      return [[], []];
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
      return [[], $inspections];
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

    return [$points, $inspections];
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
   * @param array<int, array{date:string, mmdd:string, weight:float, year?:string}> $points
   *   Pre-collected data points for the target year.
   *
   * @return array
   *   A render array for the histogram, or an empty array if there is nothing
   *   to display.
   */
  protected function buildWeightHistogram(array $points): array {
    if (empty($points)) {
      return [];
    }

    $year = $points[0]['year'] ?? substr($points[0]['date'], 0, 4);

    // SVG layout constants.
    $svg_width = 800;
    $svg_height = 300;
    $padding_top = 40;
    $padding_bottom = 40;
    $padding_x = 20;
    $chart_height = $svg_height - $padding_top - $padding_bottom;
    $chart_width = $svg_width - (2 * $padding_x);
    $slot_width = $chart_width / max(count($points), 1);
    $bar_width = (int) min($slot_width * 0.6, 60);
    $max_weight = max(array_column($points, 'weight')) ?: 1.0;
    $axis_y = $padding_top + $chart_height;

    $bars = [];
    foreach ($points as $i => $point) {
      $center_x = $padding_x + ($slot_width * ($i + 0.5));
      $bar_height = ($point['weight'] / $max_weight) * $chart_height;
      $bar_y = $padding_top + ($chart_height - $bar_height);
      $bars[] = [
        'x' => (int) round($center_x - ($bar_width / 2)),
        'y' => (int) round($bar_y),
        'height' => (int) round($bar_height),
        'label_x' => (int) round($center_x),
        'value_y' => max((int) round($bar_y) - 6, $padding_top - 4),
        'date_y' => $axis_y + 16,
        'mmdd' => $point['mmdd'],
        'weight_label' => rtrim(rtrim(number_format($point['weight'], 2, '.', ''), '0'), '.') . ' kg',
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
        '#template' => '<div class="hivelog-weight-histogram__frame"><svg viewBox="0 0 {{ svg_width }} {{ svg_height }}" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ label }}"><title>{{ label }}</title>{% for bar in bars %}<rect class="hivelog-weight-histogram__bar" x="{{ bar.x }}" y="{{ bar.y }}" width="{{ bar_width }}" height="{{ bar.height }}" fill="#f2a42e" /><text class="hivelog-weight-histogram__value" x="{{ bar.label_x }}" y="{{ bar.value_y }}" text-anchor="middle" font-size="12" fill="#333">{{ bar.weight_label }}</text><text class="hivelog-weight-histogram__date" x="{{ bar.label_x }}" y="{{ bar.date_y }}" text-anchor="middle" font-size="11" fill="#333">{{ bar.mmdd }}</text>{% endfor %}<line x1="{{ padding_x }}" y1="{{ axis_y }}" x2="{{ svg_width - padding_x }}" y2="{{ axis_y }}" stroke="#666" stroke-width="1" /></svg></div>',
        '#context' => [
          'label' => $this->t('Inspection weights for @year', ['@year' => $year]),
          'svg_width' => $svg_width,
          'svg_height' => $svg_height,
          'padding_x' => $padding_x,
          'axis_y' => $axis_y,
          'bar_width' => $bar_width,
          'bars' => $bars,
        ],
      ],
    ];
  }

}
