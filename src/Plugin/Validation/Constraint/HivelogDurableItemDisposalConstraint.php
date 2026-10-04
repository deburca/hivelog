<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A disposal date can only be recorded for a purchase of a durable item.
 */
#[Constraint(
  id: 'HivelogDurableItemDisposal',
  label: new TranslatableMarkup('Disposal only for durable items', [], ['context' => 'Validation'])
)]
class HivelogDurableItemDisposalConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = 'A disposal date can only be recorded for a purchase of a durable item.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
