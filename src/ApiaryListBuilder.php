<?php

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Provides a list builder for Apiary entities.
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
    $header['cbr'] = $this->t('CBR');
    $header['name'] = $this->t('Name');
    $header['location'] = $this->t('Location');
    $header['owner'] = $this->t('Owner');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $owner = $entity->getOwner();
    $row['cbr'] = $this->extractCbr($owner) ?: '—';
    $row['name'] = $entity->toLink()->toString();
    $row['location'] = $entity->get('location')->value
      ? mb_strimwidth($entity->get('location')->value, 0, 60, '...')
      : '';
    $row['owner'] = $owner ? $owner->getDisplayName() : '';

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
   * Extracts a trimmed CBR number from a user, if any.
   */
  protected function extractCbr(?UserInterface $user): string {
    if (!$user || !$user->hasField('cbr_number')) {
      return '';
    }
    return trim((string) $user->get('cbr_number')->value);
  }

}
