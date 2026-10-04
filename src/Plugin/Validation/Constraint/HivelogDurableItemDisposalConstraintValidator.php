<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogDurableItemDisposal.
 */
class HivelogDurableItemDisposalConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof FieldItemListInterface || $items->isEmpty()) {
      return;
    }
    assert($constraint instanceof HivelogDurableItemDisposalConstraint);

    $entity = $items->getEntity();
    assert($entity instanceof ContentEntityInterface);
    /** @var \Drupal\hivelog\Entity\InventoryItem|null $item */
    $item = $entity->get('item')->entity;
    if ($item && $item->get('item_type')->value !== 'durable') {
      $this->context->addViolation($constraint->message);
    }
  }

}
