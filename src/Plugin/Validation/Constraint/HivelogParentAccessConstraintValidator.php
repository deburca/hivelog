<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogParentAccess.
 */
class HivelogParentAccessConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof EntityReferenceFieldItemListInterface || $items->isEmpty()) {
      return;
    }
    assert($constraint instanceof HivelogParentAccessConstraint);

    $entity = $items->getEntity();
    $previous = $this->previousTargetIds($entity, $items->getFieldDefinition()->getName());

    // Unchanged references are not re-checked, and a dangling one is the
    // entity reference constraint's to report (it is simply not returned).
    foreach ($items->referencedEntities() as $parent) {
      if (in_array((string) $parent->id(), $previous, TRUE)) {
        continue;
      }
      if (!$parent->access($constraint->operation)) {
        $this->context->addViolation($constraint->message, [
          '@type' => (string) $parent->getEntityType()->getSingularLabel(),
        ]);
      }
    }
  }

  /**
   * Gets the stored target ids of a reference field, as strings.
   */
  protected function previousTargetIds(EntityInterface $entity, string $field_name): array {
    if ($entity->isNew() || $entity->id() === NULL) {
      return [];
    }
    $stored = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
    if (!$stored instanceof FieldableEntityInterface || !$stored->hasField($field_name)) {
      return [];
    }
    $ids = [];
    foreach ($stored->get($field_name)->getValue() as $value) {
      if (isset($value['target_id'])) {
        $ids[] = (string) $value['target_id'];
      }
    }
    return $ids;
  }

}
