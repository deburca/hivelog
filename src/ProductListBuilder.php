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
 * Provides a list builder for Product entities.
 *
 * Builds the table using the hivelog:entity-table SDC component (rather
 * than the inherited #type => 'table') and its own "Add Product" heading,
 * matching every other list page in the module — see
 * InventoryItemListBuilder::render().
 */
class ProductListBuilder extends EntityListBuilder {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new ProductListBuilder.
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
    $header['unit'] = $this->t('Unit');
    $header['expected_unit_price'] = $this->t('Expected Unit Price');
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

    $row['unit'] = $entity->get('unit')->value;

    $price = $entity->get('expected_unit_price')->value;
    $row['expected_unit_price'] = $price !== NULL && $price !== '' ? number_format((float) $price, 2) : '';

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
          $row['unit'] ?? '',
          $row['expected_unit_price'] ?? '',
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
        '#value' => $this->t('Products'),
        '#attributes' => ['class' => ['hivelog-list-heading__title']],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hivelog-list-heading__action']],
        'add' => [
          '#type' => 'component',
          '#component' => 'hivelog:button',
          '#props' => [
            'label' => (string) $this->t('Add Product'),
            'url' => Url::fromRoute('entity.product.add_form')->toString(),
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

}
