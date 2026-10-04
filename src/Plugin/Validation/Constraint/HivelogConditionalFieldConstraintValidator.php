<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogConditionalField.
 */
class HivelogConditionalFieldConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof FieldItemListInterface) {
      return;
    }
    assert($constraint instanceof HivelogConditionalFieldConstraint);

    $entity = $items->getEntity();
    assert($entity instanceof ContentEntityInterface);
    if (!$entity->hasField($constraint->when)) {
      return;
    }
    $controlling = $this->mainValue($entity->get($constraint->when));
    if ($constraint->is === TRUE) {
      $triggered = (bool) $controlling;
    }
    else {
      $triggered = in_array((string) $controlling, array_map('strval', (array) $constraint->is), TRUE);
    }
    if ($constraint->negate) {
      $triggered = !$triggered;
    }
    if (!$triggered) {
      return;
    }

    $filled = $this->mainValue($items) !== NULL;
    if (($constraint->require === 'present' && !$filled) || ($constraint->require === 'empty' && $filled)) {
      $this->context->addViolation($constraint->message);
    }
  }

  /**
   * Gets a field's main value, or NULL when it holds nothing.
   *
   * Reads the main property directly rather than `isEmpty()`: an unselected
   * entity reference widget submits `target_id => ''`, which a reference
   * item does not itself treat as empty, and a numeric 0 is a real value.
   * Blank strings count as nothing.
   */
  protected function mainValue(FieldItemListInterface $items): mixed {
    $first = $items->first();
    if ($first === NULL) {
      return NULL;
    }
    $property = $items->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
    $value = $first->get($property)->getValue();
    if (is_string($value)) {
      $value = trim($value);
    }
    return $value === '' ? NULL : $value;
  }

}
