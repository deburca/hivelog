<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A referenced record must belong to the same apiary as a sibling reference.
 *
 * Attached to the reference field being checked (e.g. a hive component's
 * `item`); `against` names the sibling reference on the same entity (`hive`,
 * `calendar_action`, or `apiary` itself) whose apiary it must match. Each
 * call site passes its own `message` so the wording names the right thing.
 */
#[Constraint(
  id: 'HivelogSameApiary',
  label: new TranslatableMarkup('Same apiary', [], ['context' => 'Validation'])
)]
class HivelogSameApiaryConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $against = 'apiary',
    public string $message = 'The selected record must belong to the same apiary.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
