<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A field must be filled in, or left empty, depending on a sibling field.
 *
 * - `when`: the controlling sibling field.
 * - `is`: the value(s) of `when` that trigger the rule, or TRUE for any
 *   truthy value (a checked boolean).
 * - `negate`: trigger when `when` is NOT one of `is` instead.
 * - `require`: `present` (the field must be filled) or `empty` (must be left
 *   empty). A numeric 0 counts as filled.
 */
#[Constraint(
  id: 'HivelogConditionalField',
  label: new TranslatableMarkup('Conditionally required field', [], ['context' => 'Validation'])
)]
class HivelogConditionalFieldConstraint extends SymfonyConstraint {

  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $when = '',
    public mixed $is = TRUE,
    public bool $negate = FALSE,
    public string $require = 'present',
    public string $message = 'This field is required.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
