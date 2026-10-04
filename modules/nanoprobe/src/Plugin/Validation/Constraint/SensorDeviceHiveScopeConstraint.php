<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A sensor device's `hive` must agree with its `scope`.
 *
 * Attached to the `hive` field: required when scope is "hive", and empty
 * when scope is "apiary". One plugin rather than two
 * `HivelogConditionalField` constraints, because a field holds at most one
 * constraint per plugin ID.
 */
#[Constraint(
  id: 'SensorDeviceHiveScope',
  label: new TranslatableMarkup('Sensor device hive matches scope', [], ['context' => 'Validation'])
)]
class SensorDeviceHiveScopeConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $requiredMessage = 'Hive is required when scope is "Hive".',
    public string $forbiddenMessage = 'Hive must be left empty when scope is "Apiary".',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
