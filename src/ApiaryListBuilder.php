<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Form\HivelogApiaryFilterForm;

/**
 * Provides a list builder for Apiary entities.
 *
 * Filtered by `HivelogApiaryFilterForm` (task 0155).
 */
class ApiaryListBuilder extends HivelogListBuilder {

  use StringTranslationTrait;

  /**
   * Number of apiaries shown per page.
   *
   * @var int
   */
  protected $limit = 20;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['cbr'] = $this->t('CBR');
    $header['location'] = $this->t('Location');
    $header['owner'] = $this->t('Owner');
    return $header;
  }

  /**
   * {@inheritdoc}
   *
   * The Owner and CBR columns come from the owner memberships and their users,
   * so the cached table is also invalidated by those (task 0232).
   */
  public function render() {
    $build = parent::render();
    if (isset($build['table'])) {
      $build['table']['#cache']['tags'] = array_merge(
        $build['table']['#cache']['tags'] ?? [],
        ['apiary_membership_list', 'user_list']
      );
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSortableColumns(): array {
    return [
      'name' => 'name',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    // Owners are the owner memberships, the one held longest first; `uid` is
    // only "created by" now (task 0224) and the CBR number is the primary
    // owner's (task 0232).
    assert($entity instanceof Apiary);
    $owners = [];
    foreach ($entity->getOwnerMemberships() as $membership) {
      $account = $membership->getMember();
      if ($account) {
        $owners[] = $account->getDisplayName();
      }
    }
    $row['name'] = $entity->toLink()->toString();
    $row['cbr'] = $entity->getCbrNumber() ?: '—';
    $row['location'] = $entity->get('location')->value
      ? mb_strimwidth($entity->get('location')->value, 0, 60, '...')
      : '';
    $row['owner'] = implode(', ', $owners);

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * "Add Apiary" plus a cross-link to the Queens collection — a real
   * workflow shortcut (queens are only reachable from the Apiary list
   * otherwise), kept per task 0126's heading cross-link review.
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add Apiary'),
        'url' => Url::fromRoute('entity.apiary.add_form')->toString(),
        'variant' => 'primary',
      ],
      [
        'label' => (string) $this->t('View all Queens'),
        'url' => Url::fromRoute('entity.queen.collection')->toString(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterForm(): array {
    return $this->formBuilder->getForm(HivelogApiaryFilterForm::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function applyFilters(QueryInterface $query): void {
    HivelogApiaryFilterForm::apply($query, $this->currentFilters());
  }

  /**
   * {@inheritdoc}
   */
  protected function hasActiveFilters(): bool {
    return (bool) $this->currentFilters();
  }

  /**
   * The current request's apiary filter values, or `[]` with no request.
   */
  protected function currentFilters(): array {
    $request = $this->requestStack->getCurrentRequest();
    return $request ? HivelogApiaryFilterForm::extract($request) : [];
  }

}
