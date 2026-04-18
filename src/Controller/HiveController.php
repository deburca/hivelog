<?php

namespace Drupal\hivelog\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;

/**
 * Controller for Hive pages.
 */
class HiveController extends ControllerBase {

  /**
   * Provides the add form for a hive within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $hive = $this->entityTypeManager()->getStorage('hive')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder()->getForm($hive, 'add');
  }

  /**
   * Displays a hive with its inspections.
   */
  public function view(Hive $hive) {
    $build = [];

    // Render the hive entity fields.
    $view_builder = $this->entityTypeManager()->getViewBuilder('hive');
    $build['hive'] = $view_builder->view($hive);

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

    // Load inspections for this hive, ordered by date descending.
    $inspection_ids = $this->entityTypeManager()
      ->getStorage('hive_inspection')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive->id())
      ->sort('inspection_date', 'DESC')
      ->execute();

    $inspections = $this->entityTypeManager()
      ->getStorage('hive_inspection')
      ->loadMultiple($inspection_ids);

    // Render a letterboxed weight histogram for the year of the most recent
    // inspection (placed above the inspection list).
    $histogram = $this->buildWeightHistogram($inspections);
    if (!empty($histogram)) {
      $build['weight_histogram'] = $histogram + ['#weight' => 11.5];
    }

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
      '#empty' => $this->t('No inspections have been recorded for this hive yet.'),
      '#weight' => 12,
    ];

    return $build;
  }

  /**
   * Title callback for the hive view page.
   */
  public function title(Hive $hive) {
    return $hive->label();
  }

  /**
   * Builds a letterboxed vertical histogram of inspection weights.
   *
   * Restricted to the year of the most recent inspection that has data.
   *
   * @param \Drupal\hivelog\Entity\HiveInspection[] $inspections
   *   Inspections belonging to the current hive.
   *
   * @return array
   *   A render array for the histogram, or an empty array if there is nothing
   *   to display.
   */
  protected function buildWeightHistogram(array $inspections): array {
    // Identify the most recent inspection date to determine the target year.
    $most_recent = NULL;
    foreach ($inspections as $inspection) {
      $date = $inspection->get('inspection_date')->value;
      if ($date && (!$most_recent || $date > $most_recent)) {
        $most_recent = $date;
      }
    }
    if (!$most_recent) {
      return [];
    }
    $year = substr($most_recent, 0, 4);

    // Collect data points for that year.
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
      ];
    }
    if (empty($points)) {
      return [];
    }

    // Sort chronologically so bars read left to right.
    usort($points, fn($a, $b) => strcmp($a['date'], $b['date']));

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
