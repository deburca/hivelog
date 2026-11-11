<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\ConstraintValidator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Plugin\Validation\Constraint\HivelogOneActiveQueenPerHiveConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HivelogOneActiveQueenPerHive constraint.
 */
class HivelogOneActiveQueenPerHiveConstraintValidator extends ConstraintValidator {

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$constraint instanceof HivelogOneActiveQueenPerHiveConstraint) {
      return;
    }

    // Only runs on the `hive` reference field of Queen.
    $entity = $items->getEntity();
    if ($entity->getEntityTypeId() !== 'queen') {
      return;
    }

    // The invariant applies when this queen is active.
    if ($entity->get('status')->value !== 'active') {
      return;
    }

    $hive_id = $items->target_id;
    if (!$hive_id) {
      return;
    }

    // Look for another active queen on the same hive, excluding self.
    $conflict_ids = $this->entityTypeManager
      ->getStorage('queen')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('hive', $hive_id)
      ->condition('status', 'active')
      ->execute();

    if (!$conflict_ids) {
      return;
    }

    unset($conflict_ids[$entity->id()]);

    if (empty($conflict_ids)) {
      return;
    }

    // There is another active queen on this hive. Report on the field.
    $this->context->addViolation($constraint->message);
  }

}
