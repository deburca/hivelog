<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;

/**
 * Builds the stat-tile row for the Hive/Apiary canonical pages.
 *
 * Mirrors `HivelogAppNavBuilder`'s own shape exactly (task 0105): every
 * implementation of hook_hivelog_hive_stat_tiles()/
 * hook_hivelog_apiary_stat_tiles() returns plain tile descriptors, this
 * class builds every tile's actual `hivelog:stat-tile` component markup
 * uniformly and lays them out in one shared grid — `hivelog` core
 * itself contributes no tiles of its own here (unlike the dashboard's
 * six, which are specific to that page); this row exists purely to let
 * optional submodules add an "at a glance" summary to a page core
 * already owns.
 */
class HivelogStatTileBuilder {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
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
    return $this->build($this->moduleHandler->invokeAll('hivelog_hive_stat_tiles', [$hive]));
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
    return $this->build($this->moduleHandler->invokeAll('hivelog_apiary_stat_tiles', [$apiary]));
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

    uasort($tiles, fn(array $a, array $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

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
          'url' => $tile['url']->toString(),
          'sublabel' => (string) ($tile['sublabel'] ?? ''),
          'sublabel_variant' => $tile['sublabel_variant'] ?? 'default',
        ],
      ];
    }

    return $build;
  }

}
