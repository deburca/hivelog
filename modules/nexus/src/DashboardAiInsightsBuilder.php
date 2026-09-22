<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\nexus\Entity\HiveInsight;

/**
 * Builds the dashboard's "AI Insights" section.
 *
 * Backs hook_hivelog_dashboard_sections() (nexus.module) — see
 * hivelog.api.php and
 * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md.
 * Kept as its own dashboard section, never merged into "Needs
 * attention" — that queue's row shape (chip + title + a Done/Ignored
 * report action) doesn't fit a reasoned recommendation or the "all
 * clear" state, per [[0083-ai-assisted-apiary-insights]] §3.
 *
 * The concrete answer to this project's original "no inspection
 * currently needed" example — a positive, visible confirmation that
 * monitoring ran and found nothing wrong, which nothing else in
 * hivelog provides, per [[0088-ai-insights-implementation]] §4.
 */
class DashboardAiInsightsBuilder {

  use StringTranslationTrait;

  /**
   * How old a HiveInsight can be before it stops counting as "clear today".
   *
   * Matches `HiveInsightPanelBuilder::STALE_THRESHOLD_SECONDS` — the
   * same "is this specific recommendation still fresh" question this
   * class asks for the positive (`all_clear`) case: a stale all-clear
   * isn't a real current confirmation, so it's excluded from the count
   * rather than silently counted as reassurance it can no longer back
   * up. A hive with no insight at all is excluded from both this count
   * and the action-row list below — it simply hasn't been monitored
   * yet, which is a third state this task's own acceptance criteria
   * doesn't ask this section to represent.
   */
  protected const STALE_THRESHOLD_SECONDS = 48 * 3600;

  /**
   * Sort priority for each verdict — lower sorts first (most urgent).
   */
  protected const VERDICT_SORT_ORDER = [
    'act_now' => 0,
    'inspect_soon' => 1,
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds the "AI Insights" dashboard section.
   *
   * @param \Drupal\hivelog\Entity\Apiary[] $apiaries
   *   Every apiary the current user may view, keyed by entity id.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cacheability collector.
   *
   * @return array
   *   A render array keyed `nexus_ai_insights`, or an empty array if no
   *   apiary the current user can see has `ai_insights_enabled = TRUE`
   *   — an opt-in feature with nothing opted into contributes nothing,
   *   not an empty state.
   */
  public function build(array $apiaries, CacheableMetadata $cache): array {
    $opted_in = array_filter($apiaries, fn(Apiary $apiary) => (bool) $apiary->get('ai_insights_enabled')->value);
    if (empty($opted_in)) {
      return [];
    }

    $cache->addCacheTags($this->entityTypeManager->getDefinition('apiary')->getListCacheTags());
    $cache->addCacheTags($this->entityTypeManager->getDefinition('hive_insight')->getListCacheTags());

    $hives = $this->loadAccessibleHives(array_keys($opted_in));
    foreach ($opted_in as $apiary) {
      $cache->addCacheableDependency($apiary);
    }

    $action_rows = [];
    $all_clear_count = 0;
    $now = $this->time->getRequestTime();

    foreach ($hives as $hive) {
      $insight = $this->loadLatestHiveInsight($hive->id());
      if (!$insight) {
        continue;
      }
      $cache->addCacheableDependency($insight);

      $verdict = $insight->get('verdict')->value;
      if (isset(self::VERDICT_SORT_ORDER[$verdict])) {
        $action_rows[] = $this->buildActionRow($hive, $insight, $verdict);
        continue;
      }

      $generated = (int) $insight->get('generated')->value;
      if (($now - $generated) <= self::STALE_THRESHOLD_SECONDS) {
        $all_clear_count++;
      }
    }

    usort($action_rows, fn(array $a, array $b) => $a['#context']['sort'] <=> $b['#context']['sort']);

    $section = [
      'nexus_ai_insights' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-ai-insights']],
        '#weight' => 5,
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['class' => ['hivelog-dashboard__block-heading']],
          '#value' => $this->t('AI Insights'),
        ],
      ],
    ];

    foreach ($action_rows as $i => $row) {
      unset($row['#context']['sort']);
      $section['nexus_ai_insights']['row_' . $i] = $row;
    }

    $section['nexus_ai_insights']['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['hivelog-ai-insights__summary']],
      '#value' => $this->formatPlural(
        $all_clear_count,
        '1 hive all clear today.',
        '@count hives all clear today.'
      ),
    ];

    return $section;
  }

  /**
   * Builds one action row for an act_now/inspect_soon hive.
   */
  protected function buildActionRow(Hive $hive, HiveInsight $insight, string $verdict): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="hivelog-ai-insights__row hivelog-ai-insights__row--{{ verdict }}"><span class="hivelog-ai-insights__chip">{{ chip }}</span><span class="hivelog-ai-insights__text">{{ title }} — {{ recommendation }}</span></div>',
      '#context' => [
        'verdict' => $verdict,
        'chip' => HiveInsight::VERDICTS[$verdict] ?? $verdict,
        'title' => $hive->toLink()->toRenderable(),
        'recommendation' => $insight->get('recommendation')->value,
        'sort' => [self::VERDICT_SORT_ORDER[$verdict], mb_strtolower($hive->label())],
      ],
    ];
  }

  /**
   * Loads every hive under the given apiaries that the current user may view.
   *
   * @param int[] $apiary_ids
   *   Apiary ids to match.
   *
   * @return \Drupal\hivelog\Entity\Hive[]
   *   Accessible hives.
   */
  protected function loadAccessibleHives(array $apiary_ids): array {
    $storage = $this->entityTypeManager->getStorage('hive');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('apiary', $apiary_ids, 'IN')
      ->execute();
    if (empty($ids)) {
      return [];
    }

    return array_filter(
      $storage->loadMultiple($ids),
      fn(Hive $hive) => $hive->access('view')
    );
  }

  /**
   * Loads the single most recent hive-scoped HiveInsight for a hive.
   *
   * Query-level `accessCheck(TRUE)` alone isn't trusted, matching every
   * other loader in this codebase (e.g.
   * `HiveInsightPanelBuilder::loadLatestInsight()`,
   * `SensorPanelBuilder::loadAccessibleDevices()`) — `hive_insight` has
   * no `query_access` handler, so the query itself doesn't run
   * `HiveInsightAccessControlHandler::checkAccess()`'s actual logic; an
   * explicit per-entity `access('view')` check is required.
   */
  protected function loadLatestHiveInsight(int|string $hive_id): ?HiveInsight {
    $storage = $this->entityTypeManager->getStorage('hive_insight');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('scope', 'hive')
      ->condition('hive', $hive_id)
      ->sort('generated', 'DESC')
      ->range(0, 1)
      ->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\nexus\Entity\HiveInsight $insight */
    $insight = $storage->load(reset($ids));
    return $insight && $insight->access('view') ? $insight : NULL;
  }

}
