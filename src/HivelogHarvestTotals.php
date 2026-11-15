<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\HarvestYield;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Entity\Product;

/**
 * Totals of a hive's directly recorded harvests, one product at a time.
 *
 * A product's unit is free text (kg, g, jar, each) and nothing converts
 * between units, so quantities of different products are never added
 * together (task 0284): 10 kg of honey and 4 jars of cut comb are not
 * "14 kg". Everything here is per product. Potential income (quantity at
 * the price snapshotted when the harvest was recorded) is the one figure
 * that can be compared across products, and is only used to rank them.
 *
 * Used by the hive page's "Harvest this year" tile and its Harvest History
 * table, so the two cannot disagree about what a product's total is.
 */
final class HivelogHarvestTotals {

  /**
   * A hive's harvest totals per product, the most valuable product first.
   *
   * Only harvests recorded directly (no action log) that the current user
   * may view. Two products with the same name and unit are one entry: a
   * hive that moved apiary has "Honey (kg)" from each, and that is one
   * column to a beekeeper. The same name in another unit stays separate.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive.
   * @param int|null $year
   *   Limit to harvests dated in this year, or NULL for every year.
   *
   * @return array<string, array{name: string, unit: string, quantity: float, income: float, count: int, by_year: array<string, float>}>
   *   Entries keyed by product name and unit, ordered by potential income,
   *   then number of harvests, then name. `by_year` is keyed by "YYYY",
   *   newest first.
   */
  public static function perProduct(EntityTypeManagerInterface $entity_type_manager, Hive $hive, ?int $year = NULL): array {
    if ($hive->isNew()) {
      return [];
    }
    $storage = $entity_type_manager->getStorage('harvest_yield');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('hive', $hive->id())
      ->notExists('hive_action_log')
      ->notExists('apiary_action_log');
    if ($year !== NULL) {
      $query->condition('harvest_date', $year . '-01-01', '>=')
        ->condition('harvest_date', $year . '-12-31', '<=');
    }

    $totals = [];
    foreach ($storage->loadMultiple($query->execute()) as $harvest) {
      if (!$harvest instanceof HarvestYield || !$harvest->access('view')) {
        continue;
      }
      $product = $harvest->get('product')->entity;
      if (!$product instanceof Product) {
        continue;
      }
      $quantity = (float) $harvest->get('quantity')->value;
      if ($quantity <= 0) {
        continue;
      }
      $name = (string) $product->label();
      $unit = trim((string) $product->get('unit')->value);
      $key = mb_strtolower(trim($name)) . '|' . mb_strtolower($unit);
      $totals[$key] ??= [
        'name' => $name,
        'unit' => $unit,
        'quantity' => 0.0,
        'income' => 0.0,
        'count' => 0,
        'by_year' => [],
      ];
      $harvest_year = substr((string) $harvest->get('harvest_date')->value, 0, 4);
      $totals[$key]['quantity'] += $quantity;
      $totals[$key]['income'] += $quantity * (float) $harvest->get('unit_price_snapshot')->value;
      $totals[$key]['count']++;
      $totals[$key]['by_year'][$harvest_year] = ($totals[$key]['by_year'][$harvest_year] ?? 0.0) + $quantity;
    }

    foreach ($totals as &$total) {
      krsort($total['by_year'], SORT_STRING);
    }
    unset($total);
    // Highest income first, then most harvests, then by name.
    uasort($totals, static function (array $a, array $b): int {
      return $b['income'] <=> $a['income']
        ?: $b['count'] <=> $a['count']
        ?: strcasecmp($a['name'], $b['name']);
    });
    return $totals;
  }

  /**
   * Formats a quantity with its unit: "7.5 kg", "4 jar", "12".
   */
  public static function format(float $quantity, string $unit = ''): string {
    return trim(HarvestYield::trimQuantity(number_format($quantity, 3, '.', '')) . ' ' . $unit);
  }

}
