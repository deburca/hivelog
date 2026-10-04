<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A hive component's quantity cannot exceed what is still available.
 *
 * ADR-0106 §2 Amendment. Availability is apiary-wide (purchased minus what
 * is assigned to every hive), excluding the component's own prior quantity
 * on an edit so it never counts against itself.
 */
#[Constraint(
  id: 'HivelogHiveComponentQuantity',
  label: new TranslatableMarkup('Hive component quantity available', [], ['context' => 'Validation'])
)]
class HivelogHiveComponentQuantityConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = 'Only @available of "@item" @is available, not @requested.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
