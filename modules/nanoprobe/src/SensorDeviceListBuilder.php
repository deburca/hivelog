<?php

declare(strict_types=1);

namespace Drupal\nanoprobe;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hivelog\HivelogListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder for Sensor Device entities.
 *
 * Mostly mirrors `\Drupal\collective\ApiClientListBuilder`'s shape (the
 * `hivelog:entity-table` SDC component, its own "Add" heading) — reusing
 * core's `HivelogListBuilder` base class, since `nanoprobe` depends on
 * `hivelog`. One deliberate difference: `load()` is overridden to filter
 * by per-entity `access('view')`, the way `SensorPanelBuilder::
 * loadAccessibleDevices()` already does. `ApiClient`/`AiProviderConfig`
 * never needed this — their "own" access is a flat owner check on a
 * handful of site-level rows — but `SensorDevice` access is genuinely
 * apiary-scoped and multi-tenant, and `EntityListBuilder::load()`'s
 * default `accessCheck(TRUE)` on the query doesn't actually filter rows
 * for an entity type with no `query_access` handler.
 */
class SensorDeviceListBuilder extends HivelogListBuilder {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a new SensorDeviceListBuilder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RendererInterface $renderer, AccountInterface $current_user) {
    parent::__construct($entity_type, $storage);
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('renderer'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    return array_filter($entities, fn(EntityInterface $entity) => $entity->access('view', $this->currentUser));
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['location'] = $this->t('Apiary / Hive');
    $header['scope'] = $this->t('Scope');
    $header['device_type'] = $this->t('Device Type');
    $header['enabled'] = $this->t('Enabled');
    $header['last_seen'] = $this->t('Last Seen');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\nanoprobe\Entity\SensorDevice $entity */
    $row['label'] = $entity->toLink()->toString();

    $hive = $entity->get('hive')->entity;
    $apiary = $entity->get('apiary')->entity;
    $row['location'] = $hive ? $hive->toLink()->toString() : ($apiary ? $apiary->toLink()->toString() : $this->t('Not set'));

    $scope = $entity->get('scope')->value;
    $row['scope'] = $entity->get('scope')->getSetting('allowed_values')[$scope] ?? $scope;

    $device_type = $entity->get('device_type')->value;
    $row['device_type'] = $device_type
      ? ($entity->get('device_type')->getSetting('allowed_values')[$device_type] ?? $device_type)
      : $this->t('Not set');

    $row['enabled'] = $entity->get('enabled')->value ? $this->t('Yes') : $this->t('No');

    $last_seen = $entity->get('last_seen')->value;
    $row['last_seen'] = $last_seen ? $this->dateFormatter()->format((int) $last_seen) : $this->t('Never');

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
          $row['location'],
          $row['scope'],
          $row['device_type'],
          $row['enabled'],
          $row['last_seen'],
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
            'label' => (string) $this->t('Add Sensor Device'),
            'url' => Url::fromRoute('entity.sensor_device.add_form')->toString(),
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
