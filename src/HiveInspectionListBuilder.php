<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\hivelog\Form\HivelogInspectionFilterForm;

/**
 * Provides a list builder for Hive Inspection entities.
 *
 * No context-free add route exists for HiveInspection (always added from
 * a hive's own page), so the collection page has no heading action — see
 * AGENTS.md "Routing, controllers and forms". Filtered by the same
 * `HivelogInspectionFilterForm` as the hive page's embedded inspections
 * table (task 0132) — built with no parent hive, so its Reset targets
 * this collection route instead of a hive page.
 */
class HiveInspectionListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['date'] = $this->t('Date');
    $header['hive'] = $this->t('Hive');
    $header['weight'] = $this->t('Weight');
    $header['queen'] = $this->t('Queen Seen');
    $header['honey'] = $this->t('Honey');
    $header['inspector'] = $this->t('Inspector');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['date'] = $entity->toLink($entity->get('inspection_date')->value ?: $this->t('N/A'))->toString();
    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink()->toString() : '';
    $weight = $entity->get('weight')->value;
    $row['weight'] = $weight !== NULL ? $weight . ' kg' : '';
    $row['queen'] = $entity->get('queen_seen')->value ? $this->t('Yes') : $this->t('No');
    $honey = $entity->get('honey_stores')->value;
    $row['honey'] = $honey ? ($entity->get('honey_stores')->getSetting('allowed_values')[$honey] ?? $honey) : '';
    $row['inspector'] = $entity->getOwner() ? $entity->getOwner()->getDisplayName() : '';
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterForm(): array {
    return $this->formBuilder->getForm(HivelogInspectionFilterForm::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function applyFilters(QueryInterface $query): void {
    HivelogInspectionFilterForm::apply($query, $this->currentFilters());
  }

  /**
   * {@inheritdoc}
   */
  protected function hasActiveFilters(): bool {
    return (bool) $this->currentFilters();
  }

  /**
   * The current request's inspection filter values, or `[]` with no request.
   */
  protected function currentFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    return $request ? HivelogInspectionFilterForm::extract($request) : [];
  }

}
