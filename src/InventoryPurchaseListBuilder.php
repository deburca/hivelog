<?php

declare(strict_types=1);

namespace Drupal\hivelog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder for Inventory Purchase entities.
 *
 * Builds the table using the hivelog:entity-table SDC component and its
 * own "Add Purchase" heading — see InventoryItemListBuilder for the full
 * rationale (mirrors ApiaryListBuilder/QueenListBuilder).
 */
class InventoryPurchaseListBuilder extends EntityListBuilder {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new InventoryPurchaseListBuilder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RendererInterface $renderer) {
    parent::__construct($entity_type, $storage);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['item'] = $this->t('Item');
    $header['apiary'] = $this->t('Apiary');
    $header['purchase_date'] = $this->t('Date');
    $header['quantity'] = $this->t('Quantity');
    $header['unit_price'] = $this->t('Unit Price');
    $header['total_cost'] = $this->t('Total Cost');
    $header['supplier'] = $this->t('Supplier');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * Builds operations as plain button links instead of the default
   * dropbutton widget, matching every other list on the module.
   */
  public function buildRow(EntityInterface $entity) {
    $item = $entity->get('item')->entity;
    $row['item'] = $item ? $item->toLink()->toString() : '';

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';

    $row['purchase_date'] = $entity->get('purchase_date')->value ?? '';
    $row['quantity'] = $entity->get('quantity')->value ?? '';
    $row['unit_price'] = $entity->get('unit_price')->value ?? '';
    $row['total_cost'] = $entity->get('total_cost')->value ?? '';
    $row['supplier'] = $entity->get('supplier')->value ?? '';

    $buttons = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $entity->toUrl('edit-form')->toString()];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $entity->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    $row['operations']['data'] = [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => [
        'buttons' => $buttons,
      ],
    ];

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
          $row['item'],
          $row['apiary'] ?? '',
          $row['purchase_date'] ?? '',
          $row['quantity'] ?? '',
          $row['unit_price'] ?? '',
          $row['total_cost'] ?? '',
          $row['supplier'] ?? '',
          $ops_html,
        ],
      ];
    }

    $build['heading'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hivelog-list-heading']],
      '#weight' => -90,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Inventory Purchases'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'buttons' => [
          '#type' => 'component',
          '#component' => 'hivelog:button-group',
          '#props' => [
            'buttons' => [
              [
                'label' => (string) $this->t('Add Purchase'),
                'url' => Url::fromRoute('entity.inventory_purchase.add_form')->toString(),
                'variant' => 'primary',
              ],
              [
                'label' => (string) $this->t('View Inventory Items'),
                'url' => Url::fromRoute('entity.inventory_item.collection')->toString(),
              ],
            ],
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

}
