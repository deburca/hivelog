<?php

declare(strict_types=1);

namespace Drupal\hivelog\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates HivelogHiveComponentQuantity.
 */
class HivelogHiveComponentQuantityConstraintValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    if (!$items instanceof FieldItemListInterface || $items->isEmpty()) {
      return;
    }
    assert($constraint instanceof HivelogHiveComponentQuantityConstraint);

    $component = $items->getEntity();
    assert($component instanceof ContentEntityInterface);
    /** @var \Drupal\hivelog\Entity\InventoryItem|null $item */
    $item = $component->get('item')->entity;
    /** @var \Drupal\hivelog\Entity\Hive|null $hive */
    $hive = $component->get('hive')->entity;
    if (!$item) {
      return;
    }
    // A cross-apiary item is the same-apiary constraint's to report;
    // availability is apiary-wide, so it would be a second, confusing error.
    if ($hive && (int) $item->get('apiary')->target_id !== (int) $hive->get('apiary')->target_id) {
      return;
    }

    $requested = (float) $items->value;
    $exclude_id = $component->isNew() ? NULL : (int) $component->id();
    $available = $item->getAvailableForHiveAssignmentQuantity($exclude_id);
    if ($requested > $available) {
      $this->context->addViolation($constraint->message, [
        '@available' => $this->formatQuantity($available),
        '@item' => (string) $item->label(),
        '@is' => (string) ($available == 1 ? $this->t('is') : $this->t('are')),
        '@requested' => $this->formatQuantity($requested),
      ]);
    }
  }

  /**
   * Formats a quantity without trailing zeros.
   */
  protected function formatQuantity(float $quantity): string {
    return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
  }

}
