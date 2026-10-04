<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogNotBefore.
 */
class HivelogNotBeforeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof FieldItemListInterface || $items->isEmpty()) {
      return;
    }
    assert($constraint instanceof HivelogNotBeforeConstraint);

    $entity = $items->getEntity();
    assert($entity instanceof ContentEntityInterface);
    if (!$entity->hasField($constraint->other)) {
      return;
    }
    $other = $entity->get($constraint->other)->value;
    $value = $items->value;
    // Anything but a scalar is an unprocessed widget value, not comparable.
    if (!is_scalar($value) || !is_scalar($other) || $value === '' || $other === '') {
      return;
    }
    $before = is_numeric($value) && is_numeric($other)
      ? (float) $value < (float) $other
      : (string) $value < (string) $other;
    if ($before) {
      $this->context->addViolation($constraint->message);
    }
  }

}
