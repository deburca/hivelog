<?php

declare(strict_types=1);

namespace Drupal\assimilate\Hook;

use Drupal\assimilate\ProductionGuardrail;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assimilate's entry on the status report while it is installed.
 *
 * The object-oriented replacement for the `runtime` phase of
 * assimilate_requirements() (Drupal 11.3 and later skip that function). The
 * install phase is `Install\Requirements\AssimilateRequirements`.
 */
class AssimilateRequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'assimilate.production_guardrail')]
    protected ProductionGuardrail $guardrail,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   *
   * Always visible while installed: an error if the situation has arisen since
   * install (a real apiary opted in later), otherwise a standing reminder of
   * what this module is.
   *
   * @return array<string, array<string, mixed>>
   *   The requirements, keyed by name.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $count = $this->guardrail->realApiaryCount();
    return [
      'assimilate_dev_only' => [
        'title' => $this->t('Assimilate (development/demo only)'),
        'value' => $count > 0
          ? $this->t('@count real apiary/apiaries have AI insights enabled — assimilate_cron() will not run until this is resolved.', ['@count' => $count])
          : $this->t('No real apiary currently has AI insights enabled.'),
        'description' => $this->t("Assimilate fabricates mock sensor data for demonstration purposes. It must never be enabled on a beekeeper's production site."),
        'severity' => $count > 0 ? RequirementSeverity::Error : RequirementSeverity::Warning,
      ],
    ];
  }

}
