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
   * How old a HiveInsight can be before an `all_clear` stops showing.
   *
   * Matches `HiveInsightPanelBuilder::STALE_THRESHOLD_SECONDS` — the
   * same "is this specific recommendation still fresh" question this
   * class asks for the positive (`all_clear`) case: a stale all-clear
   * isn't a real current confirmation, so its tile is withheld rather
   * than silently offering reassurance it can no longer back up.
   * act_now/inspect_soon insights are never withheld for staleness —
   * old actionable information is still actionable. A hive with no
   * insight at all gets no tile either way; it simply hasn't been
   * monitored yet.
   */
  protected const STALE_THRESHOLD_SECONDS = 48 * 3600;

  /**
   * Sort priority for each verdict — lower sorts first (most urgent).
   *
   * `all_clear` sorts last — every insight gets its own tile now (task
   * 0110), so a confirmed-fine hive still needs a defined position
   * relative to the actionable ones, just the least urgent one.
   */
  protected const VERDICT_SORT_ORDER = [
    'act_now' => 0,
    'inspect_soon' => 1,
    'all_clear' => 2,
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

    // One stat tile per hive with a current insight — task 0110. Every
    // insight gets a tile, not just the actionable ones: a plain
    // aggregate "N hives all clear today" line, with nothing shown at
    // all when N is 0, read ambiguously (does 0 mean everything's fine,
    // or that nothing has been checked yet?) — real user-reported
    // confusion. Individual tiles make the actual state legible at a
    // glance, and the section's own empty-state text (below) covers the
    // genuine "nothing has been analysed yet" case explicitly instead of
    // leaving it to be inferred from an absent count.
    $candidates = [];
    $now = $this->time->getRequestTime();

    foreach ($hives as $hive) {
      $insight = $this->loadLatestHiveInsight($hive->id());
      if (!$insight) {
        continue;
      }
      $cache->addCacheableDependency($insight);

      $verdict = $insight->get('verdict')->value;
      $sort_key = self::VERDICT_SORT_ORDER[$verdict] ?? count(self::VERDICT_SORT_ORDER);

      // A stale act_now/inspect_soon insight is still real, actionable
      // information — only a stale *all_clear* is withheld, since an
      // out-of-date "nothing's wrong" is exactly the reassurance this
      // section must never give without backing it up (mirrors
      // HiveInsightPanelBuilder's own staleness reasoning, applied here
      // only to the positive verdict).
      if ($verdict === 'all_clear') {
        $generated = (int) $insight->get('generated')->value;
        if (($now - $generated) > self::STALE_THRESHOLD_SECONDS) {
          continue;
        }
      }

      $candidates[] = [$sort_key, mb_strtolower($hive->label()), $hive, $insight, $verdict];
    }

    usort($candidates, fn(array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

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

    if ($candidates) {
      $tiles = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-stat-tiles']],
      ];
      foreach ($candidates as $i => [, , $hive, $insight, $verdict]) {
        $tiles['tile_' . $i] = $this->buildInsightTile($hive, $insight, $verdict);
      }
      $section['nexus_ai_insights']['tiles'] = $tiles;
    }
    else {
      $section['nexus_ai_insights']['empty'] = [
        '#markup' => '<p>' . $this->t('No AI insights yet — insights are generated on cron for each hive whose apiary has opted in.') . '</p>',
      ];
    }

    return $section;
  }

  /**
   * Builds one hive's insight as a `hivelog:stat-tile` component.
   *
   * Value is the verdict itself, sublabel a truncated recommendation —
   * a compact teaser; the hive's own canonical page (this tile's link)
   * shows the full recommendation and signals via `HiveInsightPanelBuilder`'s
   * own panel.
   */
  protected function buildInsightTile(Hive $hive, HiveInsight $insight, string $verdict): array {
    $recommendation = (string) $insight->get('recommendation')->value;
    $sublabel = mb_strlen($recommendation) > 70 ? mb_substr($recommendation, 0, 69) . '…' : $recommendation;

    return [
      '#type' => 'component',
      '#component' => 'hivelog:stat-tile',
      '#props' => [
        'value' => HiveInsight::VERDICTS[$verdict] ?? $verdict,
        'label' => $hive->label(),
        'url' => $hive->toUrl()->toString(),
        'sublabel' => $sublabel,
        'sublabel_variant' => $this->verdictTileVariant($verdict),
      ],
    ];
  }

  /**
   * Maps a verdict to one of the stat tile's three sublabel variants.
   *
   * Same mapping `HiveInsightPanelBuilder::verdictTileVariant()` uses —
   * small enough, and specific enough to each class's own verdict
   * source, that duplicating it beats extracting a shared trait for
   * five lines.
   */
  protected function verdictTileVariant(string $verdict): string {
    return match ($verdict) {
      'act_now' => 'critical',
      'inspect_soon' => 'warning',
      default => 'default',
    };
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
