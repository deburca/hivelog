<?php

declare(strict_types=1);

namespace Drupal\hivelog\Form;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Form handler for Inventory Item delete forms.
 *
 * Overrides `buildRowItems()` for its own two WARN rows (task 0144,
 * ADR-0103 #22/#23) to list each affected purchase/usage individually
 * — by label and link, not a bare count — per that task's own
 * acceptance criteria. Deleting doesn't touch those rows (entity
 * reference fields just go empty), but it does blank out the item's
 * name on every past report and log line that used it; not a hard
 * block, since durable catalog cleanup is a legitimate need and a
 * mistakenly created, never-used item should stay a single-step
 * delete with no added friction. See
 * docs/project-management/tasks/0045-warn-before-deleting-referenced-items-and-products.md
 * and docs/project-management/tasks/0144-delete-warn-historical-references.md.
 */
class InventoryItemDeleteForm extends HivelogEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function warnDescription(): ?TranslatableMarkup {
    return $this->t('If you delete anyway, these records will show as "Unknown item" wherever they appear, but will still count toward past cost reports.');
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRowItems(array $row): array {
    return match ($row['adr_row']) {
      '22' => $this->buildPurchaseItems(),
      '23' => $this->buildUsageLogItems(),
      default => parent::buildRowItems($row),
    };
  }

  /**
   * One detail-list item per `InventoryPurchase` referencing this item.
   */
  protected function buildPurchaseItems(): array {
    $storage = $this->entityTypeManager->getStorage('inventory_purchase');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('item', $this->getEntity()->id())
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($ids) as $purchase) {
      $items[] = $purchase->hasLinkTemplate('canonical')
        ? ['#markup' => Link::fromTextAndUrl($purchase->label(), $purchase->toUrl())->toString()]
        : ['#markup' => (string) $purchase->label()];
    }

    return $this->truncatedItems($items, Url::fromRoute('entity.inventory_purchase.collection'));
  }

  /**
   * One detail-list item per distinct action log with usage of this item.
   *
   * Several `InventoryUsage` rows can share one log (a single reported
   * action can use more than one inventory item), so logs are
   * deduplicated by type+ID before rendering — the WARN detail list
   * names the log actually worth revisiting, not every usage row.
   */
  protected function buildUsageLogItems(): array {
    $storage = $this->entityTypeManager->getStorage('inventory_usage');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('item', $this->getEntity()->id())
      ->execute();

    $logs = [];
    foreach ($storage->loadMultiple($ids) as $usage) {
      $log = $this->resolveActionLog($usage);
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
   * This item's apiary's full-calendar page, for "and N more" (task 0144).
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
