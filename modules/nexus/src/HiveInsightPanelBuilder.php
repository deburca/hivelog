<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\Utility\SimpleBulletText;
use Drupal\nexus\Entity\HiveInsight;

/**
 * Builds the read-only "AI Insight" panel for Hive/Apiary canonical pages.
 *
 * Backs hook_hivelog_hive_view_panels()/hook_hivelog_apiary_view_panels()
 * (nexus.module) — see
 * docs/project-management/decisions/0099-submodule-canonical-page-panel-hook.md
 * for why this is a hook implementation rather than hivelog core calling
 * into nexus directly. Mirrors
 * `\Drupal\nanoprobe\SensorPanelBuilder`'s own shape (access-filtered
 * query, hook-invoked, module-prefixed render key).
 *
 * Strictly read-only per task 0092 — `HiveInsight` is machine-written
 * only (by `NexusInsightGenerator`, task 0103), so no add/edit affordance
 * belongs on this panel at all.
 */
class HiveInsightPanelBuilder {

  use StringTranslationTrait;

  /**
   * How old a HiveInsight can be before this panel flags it as stale.
   *
   * Not specified as an exact figure by any ADR — [[0088-ai-insights-implementation]]
   * §4 says only "roughly 48 hours"; nexus_cron() is expected to run
   * daily, so anything past ~2 missed runs' worth of headroom means the
   * cron job likely isn't running at all, or is failing for this
   * hive/config, not just running a little late.
   */
  protected const STALE_THRESHOLD_SECONDS = 48 * 3600;

  /**
   * The panel container's HTML id — the stat tile's anchor-link target.
   *
   * Shared between the Hive and Apiary pages since only one of these
   * panels ever appears on a given page.
   */
  public const PANEL_ANCHOR_ID = 'nexus-hive-insight';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds the AI Insight panel for the Hive's dedicated Insights page.
   *
   * Lived on the Hive canonical page itself until task 0111 moved it
   * (alongside nanoprobe's Sensors panel) to a separate page, once real
   * content — a full recommendation, its signals, a trend chart per
   * sensor — made the canonical page too busy for its own "at a glance"
   * purpose; the stat tile `buildHiveStatTile()` builds is what stayed
   * behind, linking here.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being displayed.
   *
   * @return array
   *   A render array keyed `nexus_hive_insight`, or an empty array if
   *   the parent apiary hasn't opted in, no insight exists yet, or the
   *   current user can't view the one that does.
   */
  public function buildHivePanel(Hive $hive): array {
    /** @var \Drupal\hivelog\Entity\Apiary|null $apiary */
    $apiary = $hive->get('apiary')->entity;
    if (!$apiary || !$apiary->get('ai_insights_enabled')->value) {
      return [];
    }

    $insight = $this->loadLatestInsight(['scope' => 'hive', 'hive' => $hive->id()]);
    return $this->buildPanel($insight);
  }

  /**
   * Builds the AI Insight panel for an Apiary canonical page.
   *
   * Apiary-scoped insights only — a hive-scoped insight for one of this
   * apiary's hives appears on that hive's own page instead, not rolled
   * up here, mirroring `SensorPanelBuilder::buildApiaryPanel()`'s own
   * scoping. Per [[0088-ai-insights-implementation]] §5, nothing
   * produces apiary-scoped rows yet (deferred to Phase 2) — this panel
   * is wired up now regardless, so it simply stays empty until then
   * rather than needing a second implementation later.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary being displayed.
   *
   * @return array
   *   A render array keyed `nexus_hive_insight`, or an empty array.
   */
  public function buildApiaryPanel(Apiary $apiary): array {
    if (!$apiary->get('ai_insights_enabled')->value) {
      return [];
    }

    $insight = $this->loadLatestInsight(['scope' => 'apiary', 'apiary' => $apiary->id()]);
    return $this->buildPanel($insight);
  }

  /**
   * Builds the AI Insight stat tile descriptor for a Hive canonical page.
   *
   * Backs hook_hivelog_hive_stat_tiles() (nexus.module) — task 0110. The
   * tile links to the full panel already on this same page
   * (`buildHivePanel()`, anchored at `self::PANEL_ANCHOR_ID`) rather than
   * a separate page: `HiveInsight` has exactly one recommendation and a
   * bullet-point `signals` string, not a structured checklist of
   * discrete to-do items, so there's nothing a dedicated page would show
   * that the existing panel doesn't already.
   *
   * @param \Drupal\hivelog\Entity\Hive $hive
   *   The hive being displayed.
   *
   * @return array
   *   A single-item tile descriptor array (see
   *   hook_hivelog_hive_stat_tiles()'s own docblock), or an empty array
   *   if there's no insight to summarise.
   */
  public function buildHiveStatTile(Hive $hive): array {
    $insight = $this->loadLatestInsight(['scope' => 'hive', 'hive' => $hive->id()]);
    return $this->buildStatTile($insight, Url::fromRoute('entity.hive.insights', ['hive' => $hive->id()], ['fragment' => self::PANEL_ANCHOR_ID]));
  }

  /**
   * Builds the AI Insight stat tile descriptor for an Apiary canonical page.
   *
   * @param \Drupal\hivelog\Entity\Apiary $apiary
   *   The apiary being displayed.
   *
   * @return array
   *   A single-item tile descriptor array, or an empty array.
   */
  public function buildApiaryStatTile(Apiary $apiary): array {
    $insight = $this->loadLatestInsight(['scope' => 'apiary', 'apiary' => $apiary->id()]);
    return $this->buildStatTile($insight, Url::fromRoute('entity.apiary.canonical', ['apiary' => $apiary->id()], ['fragment' => self::PANEL_ANCHOR_ID]));
  }

  /**
   * Assembles one tile descriptor from an insight, or nothing.
   *
   * @param \Drupal\nexus\Entity\HiveInsight|null $insight
   *   The insight to summarise, or NULL.
   * @param \Drupal\Core\Url $anchor_url
   *   Where the tile links — the full panel, wherever it now lives (the
   *   Hive Insights page for a hive-scoped insight, task 0111; the same
   *   Apiary canonical page for an apiary-scoped one, unchanged).
   *
   * @return array
   *   `['nexus_ai_insight' => [...]]`, or an empty array if $insight is
   *   NULL.
   */
  protected function buildStatTile(?HiveInsight $insight, Url $anchor_url): array {
    if (!$insight) {
      return [];
    }

    $verdict = $insight->get('verdict')->value;
    $generated = (int) $insight->get('generated')->value;

    return [
      'nexus_ai_insight' => [
        'value' => HiveInsight::VERDICTS[$verdict] ?? $verdict,
        'label' => $this->t('AI Insight'),
        'url' => $anchor_url,
        'sublabel' => $this->t('Checked @time ago', ['@time' => $this->dateFormatter->formatTimeDiffSince($generated)]),
        'sublabel_variant' => $this->verdictTileVariant($verdict),
        'weight' => 0,
      ],
    ];
  }

  /**
   * Maps a verdict to one of the stat tile's three sublabel variants.
   *
   * Same severity ordering as verdictMessageClass(), translated to the
   * stat-tile component's own three-value scheme (default|critical|
   * warning — it has no positive/status variant of its own).
   */
  protected function verdictTileVariant(string $verdict): string {
    return match ($verdict) {
      'act_now' => 'critical',
      'inspect_soon' => 'warning',
      default => 'default',
    };
  }

  /**
   * Loads the single most recent HiveInsight matching $conditions.
   *
   * @param array $conditions
   *   Field-name => value conditions for the hive_insight query.
   *
   * @return \Drupal\nexus\Entity\HiveInsight|null
   *   The latest matching insight, or NULL if none exists or the
   *   current user cannot view it.
   */
  protected function loadLatestInsight(array $conditions): ?HiveInsight {
    $storage = $this->entityTypeManager->getStorage('hive_insight');
    $query = $storage->getQuery()->accessCheck(TRUE)->sort('generated', 'DESC')->range(0, 1);
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    $ids = $query->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\nexus\Entity\HiveInsight $insight */
    $insight = $storage->load(reset($ids));
    return $insight && $insight->access('view', $this->currentUser) ? $insight : NULL;
  }

  /**
   * Assembles the panel render array for one insight.
   *
   * @param \Drupal\nexus\Entity\HiveInsight|null $insight
   *   The insight to render, or NULL.
   *
   * @return array
   *   A render array keyed `nexus_hive_insight`, or an empty array if
   *   $insight is NULL.
   */
  protected function buildPanel(?HiveInsight $insight): array {
    if (!$insight) {
      return [];
    }

    $verdict = $insight->get('verdict')->value;
    $generated = (int) $insight->get('generated')->value;
    $is_stale = ($this->time->getRequestTime() - $generated) > self::STALE_THRESHOLD_SECONDS;

    $build = [
      'nexus_hive_insight' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nexus-hive-insight-panel'],
          'id' => self::PANEL_ANCHOR_ID,
        ],
        '#weight' => 6,
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('AI Insight'),
        ],
        'verdict' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['messages', $this->verdictMessageClass($verdict)]],
          '#value' => HiveInsight::VERDICTS[$verdict] ?? $verdict,
        ],
        'recommendation' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $insight->get('recommendation')->value,
        ],
        'signals' => [
          '#type' => 'markup',
          '#markup' => SimpleBulletText::render((string) $insight->get('signals')->value),
        ],
        'generated' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => $is_stale ? ['class' => ['nexus-hive-insight-panel__stale']] : [],
          '#value' => $is_stale
            ? $this->t('Last checked @time ago — this may be out of date.', ['@time' => $this->dateFormatter->formatTimeDiffSince($generated)])
            : $this->t('Checked @time ago.', ['@time' => $this->dateFormatter->formatTimeDiffSince($generated)]),
        ],
      ],
    ];

    $confidence = $insight->get('confidence')->value;
    if ($confidence) {
      $build['nexus_hive_insight']['confidence'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Confidence: @level', ['@level' => HiveInsight::CONFIDENCE_LEVELS[$confidence] ?? $confidence]),
        '#weight' => 5,
      ];
    }

    return $build;
  }

  /**
   * Maps a verdict to one of Drupal core's three message severity classes.
   *
   * `act_now` is the most urgent of the three (something needs doing),
   * so it borrows the `error` styling despite not being an actual error;
   * `inspect_soon` is a milder heads-up (`warning`); `all_clear` is the
   * positive case (`status`). No dedicated four-way severity styling
   * exists in Drupal core, and inventing one for three fixed values
   * wasn't worth a new CSS component.
   */
  protected function verdictMessageClass(string $verdict): string {
    return match ($verdict) {
      'act_now' => 'messages--error',
      'inspect_soon' => 'messages--warning',
      'all_clear' => 'messages--status',
      default => 'messages--status',
    };
  }

}
