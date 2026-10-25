<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A hive that has components or sensors cannot change apiary except by a move.
 *
 * Changing a saved hive's `apiary` field directly would leave its components
 * and sensors in the old apiary and skip the choices the move dialog asks for
 * (task 0231). A hive with nothing attached can be changed directly, subject to
 * the right to write in the target apiary.
 */
#[Constraint(
  id: 'HivelogHiveMovedViaDialog',
  label: new TranslatableMarkup('Hive moved through the move dialog', [], ['context' => 'Validation'])
)]
class HivelogHiveMovedViaDialogConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = 'This hive has components or sensors attached, so it must be moved with the move dialog, which asks what happens to them.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
