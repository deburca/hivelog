<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\hivelog\Entity\Hive;
use Drupal\hivelog\HiveMover;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a hive with parts attached is moved only by the move dialog.
 */
class HivelogHiveMovedViaDialogConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected HiveMover $mover,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('entity_type.manager'), $container->get('hivelog.hive_mover'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof EntityReferenceFieldItemListInterface) {
      return;
    }
    assert($constraint instanceof HivelogHiveMovedViaDialogConstraint);

    $hive = $items->getEntity();
    if ($hive->isNew() || $hive->id() === NULL) {
      return;
    }
    $stored = $this->entityTypeManager->getStorage('hive')->loadUnchanged($hive->id());
    if (!$stored instanceof Hive || (string) $stored->get('apiary')->target_id === (string) $items->target_id) {
      return;
    }
    if ($this->mover->dependents($stored)) {
      $this->context->addViolation($constraint->message);
    }
  }

}
