<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogExclusiveValue.
 */
class HivelogExclusiveValueConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof FieldItemListInterface || $items->count() < 2) {
      return;
    }
    assert($constraint instanceof HivelogExclusiveValueConstraint);
    $values = array_column($items->getValue(), 'value');
    if (in_array($constraint->value, $values, TRUE)) {
      $this->context->addViolation($constraint->message);
    }
  }

}
