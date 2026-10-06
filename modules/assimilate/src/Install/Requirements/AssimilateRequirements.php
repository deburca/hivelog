<?php

declare(strict_types=1);

namespace Drupal\assimilate\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * The install-time guardrail: refuse to install on a site with real data.
 *
 * Task 0107 requires a real, working guardrail against accidental production
 * use, not documentation alone. An error here is Drupal's own way to refuse an
 * install outright: the module-install UI (and `drush pm:install`) check it
 * before `hook_install()` ever runs.
 *
 * Drupal 11.3 and later run install requirements from a class like this one
 * (in `src/Install/Requirements/`) and skip the procedural
 * hook_requirements() of a module that marks it `#[LegacyRequirementsHook]`,
 * which assimilate_requirements() does for older versions. Drupal 12 removes
 * the procedural hook, and with it this guardrail would have vanished silently.
 *
 * This class is loaded by core before the module is installed, when the
 * module's own classes and services are not registered, so it is deliberately
 * self-contained and uses only services core always has. The same check is in
 * `ProductionGuardrail`, which the installed module uses.
 */
class AssimilateRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    // Assimilate depends on hivelog, which may be installed in the same batch
    // and not yet there: with no apiary type there can be no real apiary.
    if (!\Drupal::entityTypeManager()->hasDefinition('apiary')) {
      return [];
    }
    $query = \Drupal::entityTypeManager()->getStorage('apiary')->getQuery()
      ->accessCheck(FALSE)
      ->condition('ai_insights_enabled', TRUE);
    $demo_apiary_id = \Drupal::state()->get('assimilate.demo_apiary_id');
    if ($demo_apiary_id) {
      $query->condition('id', $demo_apiary_id, '<>');
    }
    $count = count($query->execute());
    if ($count === 0) {
      return [];
    }
    return [
      'assimilate_production_guardrail' => [
        'title' => t('Assimilate: production guardrail'),
        'value' => t('@count real apiary/apiaries already have AI insights enabled.', ['@count' => $count]),
        'description' => t('Assimilate fabricates mock sensor data for demonstration purposes and must never run on a site with real AI-insight data. Disable AI insights on every real apiary first, or do not install this module here.'),
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

}
