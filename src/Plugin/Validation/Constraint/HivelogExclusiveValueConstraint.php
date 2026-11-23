<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * One value of a multi-valued field may only stand alone.
 *
 * For a list such as disease signs, where "None" cannot be ticked together
 * with an actual sign.
 */
#[Constraint(
  id: 'HivelogExclusiveValue',
  label: new TranslatableMarkup('Exclusive value', [], ['context' => 'Validation'])
)]
class HivelogExclusiveValueConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $value = '',
    public string $message = 'This value cannot be combined with another.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
