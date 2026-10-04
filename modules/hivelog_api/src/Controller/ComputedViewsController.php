<?php

declare(strict_types=1);

namespace Drupal\hivelog_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\HivelogAlertCollector;
use Drupal\hivelog\HivelogCalendarChecklistBuilder;
use Drupal\hivelog\HivelogStatTileBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only endpoints for values no entity stores (ADR-0107 §1, task 0202).
 *
 * Alerts, stat tiles and the latest insight are computed, so the generic
 * JSON:API cannot serve them. Each endpoint calls the same service the web
 * page uses (the alert collector, the stat tile builder, the insight hook)
 * and only turns the result into data, so the app can never disagree with the
 * website. Access is the web page's: alerts cover the apiaries the user may
 * view, and a hive endpoint needs `view` on that hive.
 */
class ComputedViewsController extends ControllerBase {

  public function __construct(
    protected HivelogAlertCollector $alertCollector,
    protected HivelogCalendarChecklistBuilder $checklistBuilder,
    protected HivelogStatTileBuilder $statTileBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('hivelog.alert_collector'),
      $container->get('hivelog.calendar_checklist_builder'),
      $container->get('hivelog.stat_tile_builder'),
    );
  }

  /**
   * The "Needs attention" queue for the current user.
   */
  public function alerts(): CacheableJsonResponse {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheTags($this->entityTypeManager()->getDefinition('apiary')->getListCacheTags());

    $year = (int) date('Y');
    $week = (int) date('W');
    $apiaries = $this->checklistBuilder->viewableApiaries($this->currentUser());
    $alerts = $apiaries ? $this->alertCollector->collect($apiaries, $year, $week, $cache)['alerts'] : [];

    // Alerts are week-relative, so a cached answer cannot outlive the day.
    $cache->setCacheMaxAge(max(1, (new \DateTimeImmutable('tomorrow'))->getTimestamp() - time()));

    $data = array_map(fn(array $alert) => $this->serialiseAlert($alert), $alerts);
    return $this->respond([
      'data' => $data,
      'meta' => [
        'count' => count($data),
        'critical' => count(array_filter($data, fn($row) => $row['severity'] === 'critical')),
        'year' => $year,
        'week' => $week,
      ],
    ], $cache);
  }

  /**
   * A hive's "at a glance" stat tiles, plus its empty weight.
   */
  public function hiveStatTiles(Hive $hive): CacheableJsonResponse {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheableDependency($hive);

    $tiles = [];
    foreach ($this->statTileBuilder->tilesForHive($hive) as $key => $tile) {
      $tiles[] = [
        'key' => $key,
        'label' => (string) $tile['label'],
        'value' => $tile['value'],
        'sublabel' => (string) ($tile['sublabel'] ?? ''),
        'sublabel_variant' => $tile['sublabel_variant'] ?? 'default',
        'web_url' => isset($tile['url']) ? $tile['url']->setAbsolute()->toString() : NULL,
      ];
    }

    // Tiles depend on things the hive's own tags do not cover (sensor
    // readings, components, insights), so keep a short lifetime.
    $cache->setCacheMaxAge(300);

    return $this->respond([
      'data' => $tiles,
      'meta' => ['empty_weight_kg' => $hive->getEmptyWeightKg()],
    ], $cache);
  }

  /**
   * A hive's latest insight, when an insight module provides one.
   */
  public function hiveInsight(Hive $hive): CacheableJsonResponse {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheableDependency($hive);

    $insight = $this->moduleHandler()->invokeAll('hivelog_api_hive_insight', [$hive, $cache]);
    $cache->setCacheMaxAge(300);

    return $this->respond(['data' => $insight ?: NULL], $cache);
  }

  /**
   * Turns an alert row into plain data.
   *
   * @param array $alert
   *   A row from HivelogAlertCollector (see hook_hivelog_needs_attention_alerts()).
   */
  protected function serialiseAlert(array $alert): array {
    $subject = $alert['subject'] ?? [];
    $action = $subject['calendar_action'] ?? NULL;
    return [
      'severity' => $alert['severity'],
      'chip' => (string) $alert['chip'],
      'title' => (string) $alert['title'],
      'kind' => $alert['kind'] ?? 'other',
      'detail' => isset($subject['detail']) ? (string) $subject['detail'] : NULL,
      'apiary' => $this->reference($subject['apiary'] ?? NULL),
      'hive' => $this->reference($subject['hive'] ?? NULL),
      'calendar_action' => $action ? [
        'id' => $action->uuid(),
        'scope' => $action->get('scope')->value,
      ] : NULL,
    ];
  }

  /**
   * Describes a record by the id and name an API client knows it by.
   */
  protected function reference(?EntityInterface $entity): ?array {
    return $entity ? ['id' => $entity->uuid(), 'name' => (string) $entity->label()] : NULL;
  }

  /**
   * Builds a cacheable JSON response.
   */
  protected function respond(array $document, CacheableMetadata $cache): CacheableJsonResponse {
    $response = new CacheableJsonResponse($document);
    $response->addCacheableDependency($cache);
    return $response;
  }

}
