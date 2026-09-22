<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\nexus\Entity\AiProviderConfig;

/**
 * Collects `AiProviderConfig` staleness "Needs attention" alert rows.
 *
 * Backs hook_hivelog_needs_attention_alerts() (nexus.module) — see
 * hivelog.api.php and
 * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md
 * for why this is a hook implementation rather than
 * `DashboardController` calling into `nexus` directly. Reuses
 * [[0074-sensor-data-ingestion-architecture]] §7's device-offline
 * threshold pattern — the one piece of task 0093 that belongs in the
 * existing "Needs attention" queue rather than the new AI Insights
 * section, since it's a plain threshold rule about the config's own
 * health, not a reasoned recommendation. Mirrors
 * `\Drupal\nanoprobe\SensorAlertCollector` closely, though
 * `AiProviderConfig` is a site-level entity, not apiary/hive-scoped, so
 * its alert rows carry no apiary/hive context line.
 */
class ProviderHealthAlertCollector {

  use StringTranslationTrait;

  /**
   * How stale `last_run` must be before a config counts as unhealthy.
   *
   * Proposed by task 0093 as "more than ~36h since the last successful
   * run" — nexus_cron() is expected to run daily, so this is roughly
   * "missed a full day's run", the same reasoning
   * `HiveInsightPanelBuilder::STALE_THRESHOLD_SECONDS` (48h) uses for a
   * different question ("is this specific recommendation still fresh
   * enough to act on"). Deliberately a different, shorter threshold —
   * this one is "is the pipeline itself healthy", which should be
   * flagged sooner than any one hive's insight going stale.
   */
  protected const STALE_THRESHOLD_SECONDS = 36 * 3600;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Collects every stale-`AiProviderConfig` alert row.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Apiaries the current user may view, keyed by entity id — used only
   *   to decide whether the current user is looking at hivelog at all
   *   (mirrors `SensorAlertCollector::collectAlerts()`'s own early
   *   return); `AiProviderConfig` itself has no apiary to filter by.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cacheability collector.
   *
   * @return array[]
   *   Alert rows, shaped like
   *   `DashboardController::collectLowStockAlerts()`'s own rows.
   */
  public function collectAlerts(array $apiaries, CacheableMetadata $cache): array {
    if (empty($apiaries)) {
      return [];
    }

    $configs = $this->loadAccessibleEnabledConfigs();
    if (empty($configs)) {
      return [];
    }

    $cache->addCacheTags($this->entityTypeManager->getDefinition('ai_provider_config')->getListCacheTags());

    $alerts = [];
    foreach ($configs as $config) {
      $cache->addCacheableDependency($config);
      $alert = $this->checkStaleness($config);
      if ($alert !== NULL) {
        $alerts[] = $alert;
      }
    }

    return $alerts;
  }

  /**
   * Loads enabled AiProviderConfig entities the current user may view.
   *
   * @return \Drupal\nexus\Entity\AiProviderConfig[]
   *   Accessible, enabled configs.
   */
  protected function loadAccessibleEnabledConfigs(): array {
    $storage = $this->entityTypeManager->getStorage('ai_provider_config');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('enabled', 1)
      ->execute();
    if (empty($ids)) {
      return [];
    }

    $configs = $storage->loadMultiple($ids);
    return array_filter(
      $configs,
      fn(AiProviderConfig $config) => $config->access('view')
    );
  }

  /**
   * Staleness rule: `last_run` older than the threshold.
   *
   * A config that has never run at all (`last_run` empty) is not
   * "stale" — it's simply newly provisioned and hasn't had its first
   * `nexus_cron()` pass yet, exactly mirroring
   * `SensorAlertCollector::checkDeviceOffline()`'s own reasoning for a
   * device that's never reported: alerting immediately on every
   * freshly-created config would false-alarm before the daily job has
   * even had a chance to run once.
   */
  protected function checkStaleness(AiProviderConfig $config): ?array {
    if ($config->get('last_run')->isEmpty()) {
      return NULL;
    }
    $last_run = (int) $config->get('last_run')->value;
    $age = $this->time->getRequestTime() - $last_run;
    if ($age < self::STALE_THRESHOLD_SECONDS) {
      return NULL;
    }

    $config_url = $config->access('update')
      ? Url::fromRoute('entity.ai_provider_config.canonical', ['ai_provider_config' => $config->id()])->toString()
      : Url::fromRoute('entity.ai_provider_config.collection')->toString();

    return [
      'severity' => 'warning',
      'chip' => $this->t('AI provider stale'),
      'title' => $config->label(),
      'context' => [
        '#type' => 'inline_template',
        '#template' => '<span class="hivelog-attention__ctx">{{ detail }}</span>',
        '#context' => [
          'detail' => $this->t('Last run @time ago', ['@time' => $this->dateFormatter->formatTimeDiffSince($last_run)]),
        ],
      ],
      'action_label' => $this->t('View Config'),
      'action_url' => $config_url,
      'sort' => [2, mb_strtolower((string) $config->label())],
    ];
  }

}
