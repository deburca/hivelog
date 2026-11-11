<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that at most one active queen exists per hive.
 *
 * Attach to the `hive` reference field on Queen to enforce the invariant that a
 * single hive can have only one live queen at a time: setting this queen's
 * status to 'active' while another active queen already occupies the same hive
 * is rejected with a 422 pointing at this field. The constraint fires when the
 * `hive` value changes or the queen's own `status` changes to 'active'; if the
 * existing queen is already active on that hive the rule does nothing.
 *
 * @Constraint(
 *   id = "HivelogOneActiveQueenPerHive",
 *   label = @Translation("Only one active queen per hive", context = "Validation"),
 *   type = "field"
 * )
 */
#[Autoconfigure]
final class HivelogOneActiveQueenPerHiveConstraint extends Constraint {

  /**
   * The message shown when the invariant is broken.
   *
   * @var string
   */
  public string $message = 'This hive already has an active queen: one hive may have only one live queen at a time.';

}
