<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Controller\InventoryReportController;
use Drupal\hivelog\Entity\Hive;

/**
 * Builds the stat-tile row for the Hive/Apiary canonical pages.
 *
 * Mirrors `HivelogAppNavBuilder`'s own shape exactly (task 0105): every
 * implementation of hook_hivelog_hive_stat_tiles()/
 * hook_hivelog_apiary_stat_tiles() returns plain tile descriptors, this
 * class builds every tile's actual `hivelog:stat-tile` component markup
 * uniformly and lays them out in one shared grid. The Hive page also
 * shows core's own fact tiles (status, type, temperament, queen, empty
 * weight; task 0257, `coreTilesForHive()`), weighted before every
 * submodule tile. Those are for the web page only: `tilesForHive()`, which
 * the mobile API serves, still returns the submodules' tiles alone, since
 * the app reads the same facts from the hive record itself. The Apiary page
 * shows core's own activity tiles (task 0263, `coreTilesForApiary()`): the
 * dashboard's figures for one apiary. Its fact strip is separate
 * (`HivelogStatTileStripTrait`).
 */
class HivelogStatTileBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected HivelogAlertCollector $alertCollector,
    protected ClassResolverInterface $classResolver,
  ) {}

  /**
   * Builds the stat-tile row for a Hive canonical page.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being displayed.
   *
   * @return array
   *   A render array, or an empty array if no implementation
   *   contributed a tile for this hive.
   */
  public function buildForHive(Hive $hive): array {
    return $this->build($this->coreTilesForHive($hive) + $this->tilesForHive($hive));
  }

  /**
   * Gets core's own fact tiles for a hive (task 0257), as descriptors.
   *
   * Weighted -50 to -10 so they come before every submodule tile (weights 0
   * and up). A fact with nothing to say (no type, no temperament) is left out
   * rather than shown as a dash; status, queen and empty weight always show,
   * because "no queen" and "not yet composed" are what a beekeeper needs to
   * see.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   *
   * @return array[]
   *   Tile descriptors keyed `hivelog_*`, shaped like
   *   hook_hivelog_hive_stat_tiles()'s, except `url` is optional.
   */
  public function coreTilesForHive(Hive $hive): array {
    $label = function (string $field) use ($hive): string {
      $value = $hive->get($field)->getString();
      $allowed = $hive->get($field)->getFieldDefinition()->getSetting('allowed_values') ?? [];
      return $value === '' ? '' : (string) ($allowed[$value] ?? $value);
    };

    $tiles = [];
    $status = $label('status');
    if ($status !== '') {
      $tiles['hivelog_status'] = ['value' => $status, 'label' => $this->t('Status'), 'weight' => -50];
    }
    $construction = implode(' · ', array_filter([$label('hive_type'), $label('hive_material')]));
    if ($construction !== '') {
      $tiles['hivelog_construction'] = [
        'value' => $construction,
        'label' => $this->t('Type · Material'),
        'weight' => -40,
      ];
    }
    $temperament = $label('temperament');
    if ($temperament !== '') {
      $tiles['hivelog_temperament'] = ['value' => $temperament, 'label' => $this->t('Temperament'), 'weight' => -30];
    }
    $tiles['hivelog_queen'] = $this->queenTile($hive) + ['label' => $this->t('Queen'), 'weight' => -20];
    $tiles['hivelog_empty_weight'] = $this->emptyWeightTile($hive)
      + ['label' => $this->t('Empty weight'), 'weight' => -10];
    return $tiles;
  }

  /**
   * The Queen tile: the active queen's year and colour, or an invitation.
   *
   * The sub-line is the queen's name and breed.
   */
  protected function queenTile(Hive $hive): array {
    $queen = $hive->getActiveQueen();
    if (!$queen) {
      // A hive without a colony is not missing a queen (task 0269): no
      // warning, and no invitation to add one.
      if (!$hive->isActive()) {
        return ['value' => (string) $this->t('No queen')];
      }
      $add = Url::fromRoute('hivelog.queen.add', ['hive' => $hive->id()]);
      return [
        'value' => (string) $this->t('No queen'),
        'sublabel' => $this->t('Add a queen'),
        'sublabel_variant' => 'warning',
      ] + ($add->access() ? ['url' => $add] : []);
    }

    $year = $queen->get('queen_year')->getString();
    $colour = $queen->get('queen_colour')->getString() !== '' ? $queen->getColourName() : '';
    $value = implode(' · ', array_filter([$year, $colour]));
    $breed = $queen->get('breed')->getString();
    $tile = [
      'value' => $value !== '' ? $value : (string) $queen->label(),
      // The queen's name, and the breed when recorded; the Introduced date is on
      // the queen's own page (task 0257 dropped the hive page's queen table).
      'sublabel' => implode(' · ', array_filter([
        (string) $queen->label(),
        $breed ? (string) ($queen->get('breed')->getFieldDefinition()->getSetting('allowed_values')[$breed] ?? $breed) : '',
      ])),
    ];
    if ($queen->access('view')) {
      $tile['url'] = $queen->toUrl();
    }
    return $tile;
  }

  /**
   * The Empty weight tile: the tare weight, or why it is not known.
   *
   * Both unknown states link to the components section: an empty hive has not
   * been composed, and a hive with a part that has no weight is incomplete
   * (`Hive::getEmptyWeightKg()` is NULL for both, never a partial sum).
   */
  protected function emptyWeightTile(Hive $hive): array {
    $url = Url::fromRoute('entity.hive.canonical', ['hive' => $hive->id()], ['fragment' => 'components']);
    $kg = $hive->getEmptyWeightKg();
    if ($kg !== NULL) {
      return [
        'value' => rtrim(rtrim(number_format($kg, 3, '.', ''), '0'), '.') . ' kg',
        'url' => $url,
      ];
    }
    return [
      'value' => (string) $this->t('Unknown'),
      'sublabel' => $hive->hasComponents() ? $this->t('Incomplete') : $this->t('Not yet composed'),
      'sublabel_variant' => 'warning',
      'url' => $url,
    ];
  }

  /**
   * Gets a hive's tile descriptors as data, sorted by weight.
   *
   * The same descriptors buildForHive() lays out, for a caller that is not a
   * web page (the mobile API, task 0202). Nothing is rendered; each value is
   * exactly what the hook implementation returned.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   *
   * @return array[]
   *   Tile descriptors keyed by the contributing module's own key.
   */
  public function tilesForHive(Hive $hive): array {
    return $this->sortTiles($this->moduleHandler->invokeAll('hivelog_hive_stat_tiles', [$hive]));
  }

  /**
   * Builds the stat-tile row for an Apiary canonical page.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary being displayed.
   *
   * @return array
   *   A render array, or an empty array if no implementation
   *   contributed a tile for this apiary.
   */
  public function buildForApiary(Apiary $apiary): array {
    $build = $this->build($this->coreTilesForApiary($apiary) + $this->moduleHandler->invokeAll('hivelog_apiary_stat_tiles', [$apiary]));
    if ($build) {
      // The core tiles count records of several types and read what the viewer
      // may see, so the row changes with any of them.
      $cache = new CacheableMetadata();
      $cache->addCacheContexts(['user.permissions'])->addCacheableDependency($apiary);
      $types = [
        'hive', 'hive_inspection', 'calendar_action', 'apiary_action_log', 'hive_action_log',
        'inventory_item', 'inventory_purchase', 'inventory_usage', 'harvest_yield', 'product',
      ];
      foreach ($types as $type) {
        $cache->addCacheTags($this->entityTypeManager->getDefinition($type)->getListCacheTags());
      }
      $cache->applyTo($build);
    }
    return $build;
  }

  /**
   * Gets core's activity tiles for an apiary (task 0263), as descriptors.
   *
   * The dashboard's figures, for this one apiary: active hives, open seasonal
   * tasks (critical when some are overdue), inspections this month and net
   * year to date. Weighted -50 to -20, before every submodule tile. Net YTD is
   * left out unless the viewer may open the apiary's financial report, the same
   * rule as the route.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary.
   *
   * @return array[]
   *   Tile descriptors keyed `hivelog_*`.
   */
  public function coreTilesForApiary(Apiary $apiary): array {
    $id = (int) $apiary->id();
    $year = (int) date('Y');
    $week = (int) date('W');
    $cache = new CacheableMetadata();

    $active_hives = (int) $this->entityTypeManager->getStorage('hive')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $id)
      ->condition('status', 'active')
      ->count()
      ->execute();
    $seasonal = $this->alertCollector->collectSeasonalAlerts([$id => $apiary], $year, $week, $cache);

    $tiles = [
      'hivelog_hives' => [
        'value' => (string) $active_hives,
        'label' => $this->t('Active hives'),
        'url' => Url::fromRoute('entity.apiary.canonical', ['apiary' => $id], ['fragment' => 'hives']),
        'weight' => -50,
      ],
    ];

    $calendar = Url::fromRoute('hivelog.apiary.calendar_action.collection', ['apiary' => $id]);
    $tiles['hivelog_open_tasks'] = [
      'value' => (string) $seasonal['open_total'],
      'label' => $this->t('Open seasonal tasks'),
      'weight' => -40,
    ] + ($calendar->access() ? ['url' => $calendar] : [])
      + ($seasonal['open_overdue'] ? [
        'sublabel' => $this->t('@n overdue', ['@n' => $seasonal['open_overdue']]),
        'sublabel_variant' => 'critical',
      ] : []);

    $tiles['hivelog_inspections_month'] = [
      'value' => (string) $this->inspectionsThisMonth([$id]),
      'label' => $this->t('Inspections this month'),
      'weight' => -30,
    ];

    $report = Url::fromRoute('hivelog.apiary.inventory_cost_report', ['apiary' => $id]);
    if ($report->access()) {
      $totals = $this->classResolver->getInstanceFromDefinition(InventoryReportController::class)
        ->computeApiaryYearTotals($apiary, $year);
      $tiles['hivelog_net_ytd'] = [
        'value' => number_format($totals['net'], 0),
        'label' => $this->t('Net YTD'),
        'url' => $report,
        'weight' => -20,
      ];
    }
    return $tiles;
  }

  /**
   * Counts inspections dated this month under the given apiaries.
   *
   * Counted under the apiary each was written under (its stamp), so a hive
   * that has since moved does not take its history with it (task 0231). Shared
   * by the dashboard and the apiary page (task 0263).
   *
   * @param array<int|string> $apiary_ids
   *   The apiary ids.
   */
  public function inspectionsThisMonth(array $apiary_ids): int {
    if (!$apiary_ids) {
      return 0;
    }
    return (int) $this->entityTypeManager->getStorage('hive_inspection')->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->condition('inspection_date', date('Y-m-01'), '>=')
      ->condition('inspection_date', date('Y-m-01', strtotime('first day of next month')), '<')
      ->count()
      ->execute();
  }

  /**
   * Sorts tile descriptors by their weight, keeping their keys.
   */
  protected function sortTiles(array $tiles): array {
    uasort($tiles, fn(array $a, array $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    return $tiles;
  }

  /**
   * Assembles the shared grid from a set of tile descriptors.
   *
   * @param array $tiles
   *   Tile descriptors, keyed by the contributing module's own unique
   *   key — shaped per hook_hivelog_hive_stat_tiles()'s own docblock.
   *
   * @return array
   *   A render array, or an empty array if $tiles is empty.
   */
  protected function build(array $tiles): array {
    if (!$tiles) {
      return [];
    }

    $tiles = $this->sortTiles($tiles);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-stat-tiles']],
    ];
    foreach ($tiles as $key => $tile) {
      $build[$key] = [
        '#type' => 'component',
        '#component' => 'hivelog:stat-tile',
        '#props' => [
          'value' => $tile['value'],
          'label' => (string) $tile['label'],
          'url' => isset($tile['url']) ? $tile['url']->toString() : '',
          'sublabel' => (string) ($tile['sublabel'] ?? ''),
          'sublabel_variant' => $tile['sublabel_variant'] ?? 'default',
        ],
      ];
    }

    return $build;
  }

}
