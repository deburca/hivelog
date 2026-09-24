<?php

declare(strict_types=1);

namespace Drupal\nexus;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogListBuilder;
use Drupal\nexus\Entity\AiProviderConfig;

/**
 * Provides a list builder for AI Provider Config entities.
 *
 * Mirrors `\Drupal\collective\ApiClientListBuilder`'s shape exactly (the
 * `hivelog:entity-table` SDC component, its own "Add" heading) — reusing
 * core's `HivelogListBuilder` base class, since `nexus` depends on
 * `hivelog`.
 */
class AiProviderConfigListBuilder extends HivelogListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['mode'] = $this->t('Mode');
    $header['enabled'] = $this->t('Enabled');
    $header['last_run'] = $this->t('Last Run');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\nexus\Entity\AiProviderConfig $entity */
    $row['label'] = $entity->toLink()->toString();
    $row['mode'] = AiProviderConfig::MODES[$entity->get('mode')->value] ?? $entity->get('mode')->value;
    $row['enabled'] = $entity->get('enabled')->value ? $this->t('Yes') : $this->t('No');

    $last_run = $entity->get('last_run')->value;
    $row['last_run'] = $last_run ? $this->dateFormatter()->format((int) $last_run) : $this->t('Never');

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeadingActions(): array {
    return [
      [
        'label' => (string) $this->t('Add AI Provider Config'),
        'url' => Url::fromRoute('entity.ai_provider_config.add_form')->toString(),
        'variant' => 'primary',
      ],
    ];
  }

  /**
   * The date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter.
   */
  protected function dateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
