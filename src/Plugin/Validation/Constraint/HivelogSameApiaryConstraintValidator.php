<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogSameApiary.
 */
class HivelogSameApiaryConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof EntityReferenceFieldItemListInterface || $items->isEmpty()) {
      return;
    }
    assert($constraint instanceof HivelogSameApiaryConstraint);

    $entity = $items->getEntity();
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($constraint->against)) {
      return;
    }
    $sibling = $entity->get($constraint->against)->entity;
    $sibling_apiary = $sibling ? $this->apiaryId($sibling) : NULL;
    if ($sibling_apiary === NULL) {
      return;
    }

    foreach ($items->referencedEntities() as $referenced) {
      $apiary = $this->apiaryId($referenced);
      if ($apiary !== NULL && $apiary !== $sibling_apiary) {
        $this->context->addViolation($constraint->message);
      }
    }
  }

  /**
   * Gets the apiary an entity belongs to (an apiary is its own).
   */
  protected function apiaryId(EntityInterface $entity): ?int {
    if ($entity->getEntityTypeId() === 'apiary') {
      return (int) $entity->id();
    }
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField('apiary')) {
      return NULL;
    }
    $id = $entity->get('apiary')->target_id;
    return $id === NULL ? NULL : (int) $id;
  }

}
