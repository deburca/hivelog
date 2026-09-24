<?php

declare(strict_types=1);

namespace Drupal\collective;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder for API Client entities.
 *
 * Mirrors `\Drupal\hivelog\ProductListBuilder`'s shape exactly (the
 * `hivelog:entity-table` SDC component, its own "Add" heading) — reusing
 * core's `HivelogListBuilder` base class, since `collective` depends on
 * `hivelog`.
 */
class ApiClientListBuilder extends HivelogListBuilder {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new ApiClientListBuilder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RendererInterface $renderer) {
    parent::__construct($entity_type, $storage);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('renderer'),
    );
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['enabled'] = $this->t('Enabled');
    $header['last_run'] = $this->t('Last Run');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->toLink()->toString();
    $row['enabled'] = $entity->get('enabled')->value ? $this->t('Yes') : $this->t('No');

    $last_run = $entity->get('last_run')->value;
    $row['last_run'] = $last_run ? $this->dateFormatter()->format((int) $last_run) : $this->t('Never');

    $row['operations']['data'] = $this->buildOperations($entity);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $headers = array_map('strval', array_values($this->buildHeader()));
    $rows = [];
    foreach ($this->load() as $entity) {
      $row = $this->buildRow($entity);
      if (!$row) {
        continue;
      }
      $ops = $row['operations']['data'] ?? [];
      $ops_html = !empty($ops) ? $this->renderer->renderInIsolation($ops) : '';

      $rows[] = [
        'cells' => [
          $row['label'],
          $row['enabled'],
          $row['last_run'],
          $ops_html,
        ],
      ];
    }

    $build['heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => -90,
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add API Client'),
            'url' => Url::fromRoute('entity.api_client.add_form')->toString(),
            'variant' => 'primary',
          ],
        ],
      ],
      '#attached' => ['library' => ['hivelog/buttons']],
    ];

    $build['table'] = [
      '#type' => 'component',
      '#component' => 'hivelog:entity-table',
      '#props' => [
        'headers' => $headers,
        'rows' => $rows,
        'empty_message' => (string) $this->t('There are no @label yet.', [
          '@label' => $this->entityType->getPluralLabel(),
        ]),
      ],
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    return $build;
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
