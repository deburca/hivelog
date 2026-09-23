<?php

declare(strict_types=1);

namespace Drupal\assimilate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * The one real guardrail against assimilate contaminating a production site.
 *
 * Task 0107 requires "a real, working guardrail against accidental
 * production use... not documentation alone". This checks for any
 * `Apiary` with `ai_insights_enabled = TRUE` other than assimilate's own
 * demo apiary (tracked via State, excluded once it exists) — a real
 * beekeeper who has opted an apiary into AI insights is exactly the
 * signal that this is a genuine, in-use site, not a sandbox.
 *
 * Consulted from two places: `assimilate_requirements()`'s `install`
 * phase (a `REQUIREMENT_ERROR` there is Drupal's own idiomatic way to
 * refuse to let a module be installed at all — checked by both the
 * module-install UI and `drush pm:install` before `hook_install()` ever
 * runs, so this is a functional block, not a warning nobody reads), and
 * `assimilate_cron()` (re-checked on every run, since a site could
 * install assimilate first and only later have a real apiary opt into AI
 * insights — the more insidious contamination case).
 */
class ProductionGuardrail {

  use StringTranslationTrait;

  /**
   * The State key `DemoDataProvisioner` stores the demo apiary's id under.
   */
  public const DEMO_APIARY_ID_STATE_KEY = 'assimilate.demo_apiary_id';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateInterface $state,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Checks whether it's safe for assimilate to install/run right now.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   NULL if safe. Otherwise, a human-readable reason it's not —
   *   suitable to show directly as a requirements-check value or log it
   *   from `assimilate_cron()`.
   */
  public function blockingReason(): ?TranslatableMarkup {
    $storage = $this->entityTypeManager->getStorage('apiary');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('ai_insights_enabled', TRUE);

    $demo_apiary_id = $this->state->get(self::DEMO_APIARY_ID_STATE_KEY);
    if ($demo_apiary_id) {
      $query->condition('id', $demo_apiary_id, '<>');
    }

    $ids = $query->execute();
    if (empty($ids)) {
      return NULL;
    }

    return $this->t('This site has @count real apiary/apiaries with AI insights enabled — a sign of genuine use. Assimilate fabricates mock sensor data and must never run alongside real AI-insight data.', [
      '@count' => count($ids),
    ]);
  }

}
