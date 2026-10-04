<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A field's value must not be lower than a sibling field's value.
 *
 * Works for any pair that compares correctly as scalars: ISO `Y-m-d` date
 * strings, or numbers such as week numbers. Empty on either side passes.
 */
#[Constraint(
  id: 'HivelogNotBefore',
  label: new TranslatableMarkup('Not before another field', [], ['context' => 'Validation'])
)]
class HivelogNotBeforeConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $other = '',
    public string $message = 'This value cannot be before the other value.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
