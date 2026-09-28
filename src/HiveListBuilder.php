<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Form\HivelogHiveFilterForm;

/**
 * Provides a list builder for Hive entities.
 *
 * No context-free add route exists for Hive (always added from an
 * apiary's own page), so the heading has no Add button — see AGENTS.md
 * "Routing, controllers and forms". It does carry a "View Apiaries"
 * cross-link (task 0159), the same kind of navigational shortcut
 * `InventoryPurchaseListBuilder`'s "View Inventory Items" already is, not
 * an add-route workaround. Filtered by the same `HivelogHiveFilterForm`
 * as the apiary page's embedded hive table (task 0132) — built with no
 * parent apiary, so its Reset targets this collection route instead of
 * an apiary page.
 */
class HiveListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Hive');
    $header['apiary'] = $this->t('Apiary');
    $header['breed'] = $this->t('Breed');
    $header['status'] = $this->t('Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink()->toString();
    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';
    // Breed lives on the active queen, not the hive — see
    // Hive::getActiveQueen().
    $queen = $entity->getActiveQueen();
    $breed = $queen ? $queen->get('breed')->value : NULL;
    $row['breed'] = $breed ? ($queen->get('breed')->getSetting('allowed_values')[$breed] ?? $breed) : '';
    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('View Apiaries'),
        'url' => Url::fromRoute('entity.apiary.collection')->toString(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterForm(): array {
    return $this->formBuilder->getForm(HivelogHiveFilterForm::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function applyFilters(QueryInterface $query): void {
    HivelogHiveFilterForm::apply($query, $this->currentFilters());
  }

  /**
   * {@inheritdoc}
   */
  protected function hasActiveFilters(): bool {
    return (bool) $this->currentFilters();
  }

  /**
   * The current request's hive filter values, or `[]` with no request.
   */
  protected function currentFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    return $request ? HivelogHiveFilterForm::extract($request) : [];
  }

}
