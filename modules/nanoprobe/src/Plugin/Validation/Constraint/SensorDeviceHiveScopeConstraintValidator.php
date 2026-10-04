<?php

declare(strict_types=1);

namespace Drupal\nanoprobe\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates SensorDeviceHiveScope.
 */
class SensorDeviceHiveScopeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof FieldItemListInterface) {
      return;
    }
    assert($constraint instanceof SensorDeviceHiveScopeConstraint);

    $entity = $items->getEntity();
    assert($entity instanceof ContentEntityInterface);
    $scope = $entity->get('scope')->value;
    // Reads `target_id` directly: an unselected reference widget submits
    // `target_id => ''`, which the item's own isEmpty() does not treat as
    // empty (the same pitfall SensorDevice::preSave() documents).
    $has_hive = (bool) $items->target_id;

    if ($scope === 'hive' && !$has_hive) {
      $this->context->addViolation($constraint->requiredMessage);
    }
    if ($scope === 'apiary' && $has_hive) {
      $this->context->addViolation($constraint->forbiddenMessage);
    }
  }

}
