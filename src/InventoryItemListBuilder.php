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
 * Provides a list builder for Inventory Item entities.
 *
 * Builds the table using the hivelog:entity-table SDC component (rather
 * than the inherited #type => 'table') and its own "Add Inventory Item"
 * heading, matching every other list page in the module — see
 * ApiaryListBuilder::render(). The heading is self-built rather than
 * relying on the core Local Actions block, since this page moved onto the
 * site's front-end main menu where that block isn't guaranteed to be
 * placed.
 */
class InventoryItemListBuilder extends EntityListBuilder {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new InventoryItemListBuilder.
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
    $header['name'] = $this->t('Name');
    $header['apiary'] = $this->t('Apiary');
    $header['category'] = $this->t('Category');
    $header['unit'] = $this->t('Unit');
    $header['item_type'] = $this->t('Type');
    $header['stock'] = $this->t('Stock on Hand');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * Builds operations as plain button links instead of the default
   * dropbutton widget, matching every other list on the module.
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $entity->toLink()->toString();

    $apiary = $entity->get('apiary')->entity;
    $row['apiary'] = $apiary ? $apiary->toLink()->toString() : '';

    $category = $entity->get('category')->value;
    $row['category'] = $category
      ? ($entity->get('category')->getSetting('allowed_values')[$category] ?? $category)
      : '';

    $row['unit'] = $entity->get('unit')->value;

    $item_type = $entity->get('item_type')->value;
    $row['item_type'] = $entity->get('item_type')->getSetting('allowed_values')[$item_type] ?? $item_type;

    /** @var \Drupal\hivelog\Entity\InventoryItem $entity */
    $stock = $entity->getStockOnHand();
    $row['stock'] = $stock === NULL ? '' : rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . $entity->get('unit')->value;

    $status = $entity->get('status')->value;
    $row['status'] = $entity->get('status')->getSetting('allowed_values')[$status] ?? $status;

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
          $row['name'],
          $row['apiary'] ?? '',
          $row['category'] ?? '',
          $row['unit'] ?? '',
          $row['item_type'] ?? '',
          $row['stock'] ?? '',
          $row['status'] ?? '',
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
        '#value' => $this->t('Inventory Items'),
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
                'label' => (string) $this->t('Add Inventory Item'),
                'url' => Url::fromRoute('entity.inventory_item.add_form')->toString(),
                'variant' => 'primary',
              ],
              [
                'label' => (string) $this->t('View Purchases'),
                'url' => Url::fromRoute('entity.inventory_purchase.collection')->toString(),
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
