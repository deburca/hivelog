<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
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
   * Builds the AI Insight panel for a Hive canonical page.
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
        '#attributes' => ['class' => ['nexus-hive-insight-panel']],
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
