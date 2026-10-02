<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Drupal\hivelog\Form\HivelogQueenFilterForm;

/**
 * Provides a list builder for Queen entities.
 *
 * Columns follow issue #51: colour, hive, date of introduction.
 * Filtered by `HivelogQueenFilterForm` (task 0155).
 */
class QueenListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Queen');
    $header['colour'] = $this->t('Colour');
    $header['hive'] = $this->t('Hive');
    $header['introduced'] = $this->t('Introduced');
    $header['status'] = $this->t('Status');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSortableColumns(): array {
    return [
      'name' => 'name',
      'colour' => 'queen_colour',
      'introduced' => 'introduction_date',
      'status' => 'status',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink()->toString();

    $colour = $entity->get('queen_colour')->value;
    $row['colour'] = $colour ? ($entity->get('queen_colour')->getSetting('allowed_values')[$colour] ?? $colour) : '';

    $hive = $entity->get('hive')->entity;
    $row['hive'] = $hive ? $hive->toLink()->toString() : '';

    $row['introduced'] = $entity->get('introduction_date')->value ?? '';

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * Self-built rather than relying on the core Local Actions block, since
   * this page moved onto the site's front-end main menu where that block
   * isn't guaranteed to be placed.
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Queen'),
        'url' => Url::fromRoute('entity.queen.add_form')->toString(),
        'variant' => 'primary',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterForm(): array {
    return $this->formBuilder->getForm(HivelogQueenFilterForm::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function applyFilters(QueryInterface $query): void {
    HivelogQueenFilterForm::apply($query, $this->currentFilters());
  }

  /**
   * {@inheritdoc}
   */
  protected function hasActiveFilters(): bool {
    return (bool) $this->currentFilters();
  }

  /**
   * The current request's queen filter values, or `[]` with no request.
   */
  protected function currentFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    return $request ? HivelogQueenFilterForm::extract($request) : [];
  }

}
