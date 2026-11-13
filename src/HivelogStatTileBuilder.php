<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
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
 * has no core tiles here (its fact strip is `HivelogStatTileStripTrait`).
 */
class HivelogStatTileBuilder {

  use StringTranslationTrait;

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
    return $this->build($this->moduleHandler->invokeAll('hivelog_apiary_stat_tiles', [$apiary]));
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
