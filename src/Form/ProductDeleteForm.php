<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Form handler for Product delete forms.
 *
 * Mirrors `InventoryItemDeleteForm`'s own WARN row (task 0144, ADR-0103
 * #26): lists each affected `HarvestYield`'s action log individually
 * rather than a bare count. See
 * docs/project-management/tasks/0045-warn-before-deleting-referenced-items-and-products.md
 * and docs/project-management/tasks/0144-delete-warn-historical-references.md.
 */
class ProductDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function warnDescription(): ?TranslatableMarkup {
    return $this->t('If you delete anyway, these records will show as "Unknown product" wherever they appear, but will still count toward past yield reports.');
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRowItems(array $row): array {
    return match ($row['adr_row']) {
      '26' => $this->buildYieldLogItems(),
      default => parent::buildRowItems($row),
    };
  }

  /**
   * One detail-list item per distinct action log with a yield of this product.
   *
   * Deduplicated by type+ID, same reasoning as
   * `InventoryItemDeleteForm::buildUsageLogItems()` — a single reported
   * action can record more than one product's `HarvestYield`.
   */
  protected function buildYieldLogItems(): array {
    $storage = $this->entityTypeManager->getStorage('harvest_yield');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('product', $this->getEntity()->id())
      ->execute();

    $logs = [];
    foreach ($storage->loadMultiple($ids) as $yield) {
      $log = $this->resolveActionLog($yield);
      if ($log) {
        $logs[$log->getEntityTypeId() . ':' . $log->id()] = $log;
      }
    }

    return $this->truncatedItems(
      array_map([$this, 'buildActionLogItem'], array_values($logs)),
      $this->apiaryCalendarUrl(),
    );
  }

  /**
   * This product's apiary's full-calendar page, for "and N more" (0144).
   */
  protected function apiaryCalendarUrl(): ?Url {
    $entity = $this->getEntity();
    // @phpstan-ignore-next-line
    $apiary = $entity->get('apiary')->entity;
    if (!$apiary) {
      return NULL;
    }
    return Url::fromRoute('hivelog.apiary.calendar_action.collection', ['apiary' => $apiary->id()]);
  }

}
