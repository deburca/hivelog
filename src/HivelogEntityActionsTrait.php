<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Builds a detail page's page-owned Edit/Delete button group.
 *
 * Extracted from `HivelogDetailPageTrait` in task 0118 so a canonical
 * page that doesn't otherwise want that trait's section-building
 * machinery — Apiary, Hive, and the three submodule "minimal" canonical
 * pages (SensorDevice, ApiClient, AiProviderConfig) — can get page-owned
 * Edit/Delete without also having to implement `formatFieldValue()`,
 * `HivelogDetailPageTrait`'s abstract method. Every consuming controller
 * must extend `\Drupal\Core\Controller\ControllerBase` (for `$this->t()`).
 *
 * Page-owned buttons are this module's only mechanism for entity actions
 * (ADR-0012, AGENTS.md "Routing, controllers and forms") — a theme or
 * toolbar placing local tasks is never guaranteed, which is exactly what
 * task 0118 found: on `cms2`, Apiary and Hive had no page-owned buttons
 * at all, relying entirely on the Navigation module's top bar for Edit /
 * Delete.
 */
trait HivelogEntityActionsTrait {

  /**
   * Builds Edit and Delete action links for a detail page.
   *
   * The Delete button checks the plain `delete` operation deliberately,
   * not `delete_route` — per `HivelogDeleteBlockRelationshipsTest::
   * testDeleteRouteAccessStaysAllowedWhileDeleteOpIsBlocked()`'s own
   * docblock, a BLOCK-blocked entity's Delete button/tab is *meant* to
   * disappear (ADR-0103/task 0141); `delete_route` exists only so a
   * stale link or direct URL still reaches the delete form's own
   * "Can't delete yet" explanation instead of a bare 403, not so the
   * button stays visible.
   */
  protected function buildActions(FieldableEntityInterface $entity): array {
    $buttons = [];
    if ($entity->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $entity->toUrl('edit-form')->toString()];
    }
    if ($entity->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    if (empty($buttons)) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#weight' => -10,
    ];
  }

}
