<?php

declare(strict_types=1);

namespace Drupal\hivelog\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hivelog\Entity\Apiary;
use Drupal\hivelog\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Product pages.
 */
class ProductController extends ControllerBase {

  /**
   * Constructs a ProductController.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
  ) {
    // $entityTypeManager / $entityFormBuilder are untyped properties
    // inherited from ControllerBase; assign them rather than redeclaring
    // them with types.
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
    );
  }

  /**
   * Provides the add form for a product within an apiary context.
   */
  public function addForm(Apiary $apiary) {
    $product = $this->entityTypeManager->getStorage('product')->create([
      'apiary' => $apiary->id(),
    ]);
    return $this->entityFormBuilder->getForm($product, 'add');
  }

  /**
   * Displays a product with its fields grouped into readable sections.
   */
  public function view(Product $product) {
    $build = [
      'actions' => $this->buildActions($product),
    ];

    $build += [
      'overview' => $this->buildSection($this->t('Overview'), $product, [
        'apiary',
        'name',
        'unit',
        'expected_unit_price',
        'status',
      ]),
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($product);
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Title callback for the product view page.
   */
  public function title(Product $product) {
    return $product->label();
  }

  /**
   * Builds Edit and Delete action links for the product view.
   */
  protected function buildActions(Product $product): array {
    $buttons = [];
    if ($product->access('update')) {
      $buttons[] = ['label' => (string) $this->t('Edit'), 'url' => $product->toUrl('edit-form')->toString()];
    }
    if ($product->access('delete')) {
      $buttons[] = [
        'label' => (string) $this->t('Delete'),
        'url' => $product->toUrl('delete-form')->toString(),
        'variant' => 'danger',
      ];
    }
    if (empty($buttons)) {
      return [];
    }
    return [
      '#type' => 'component',
      '#component' => 'hivelog:button-group',
      '#props' => ['buttons' => $buttons],
      '#weight' => -10,
    ];
  }

  /**
   * Builds a consistently formatted product section.
   */
  protected function buildSection($title, Product $product, array $fields): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hivelog-product-section'],
      ],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $this->buildRows($product, $fields),
        '#attributes' => [
          'class' => ['hivelog-product-table'],
        ],
        '#attached' => ['library' => ['hivelog/tables']],
      ],
    ];
  }

  /**
   * Builds rows for a section table.
   */
  protected function buildRows(Product $product, array $fields): array {
    $rows = [];

    foreach ($fields as $field_name) {
      $rows[] = [
        [
          'data' => [
            '#plain_text' => (string) $product->get($field_name)->getFieldDefinition()->getLabel(),
          ],
        ],
        [
          'data' => $this->buildFieldValue($product, $field_name),
        ],
      ];
    }

    return $rows;
  }

  /**
   * Builds the display value for a single product field.
   */
  protected function buildFieldValue(Product $product, string $field_name): array {
    $field = $product->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#plain_text' => (string) $this->t('—'),
      ];
    }

    switch ($field_name) {
      case 'apiary':
        return $field->entity ? $field->entity->toLink()->toRenderable() : [
          '#plain_text' => (string) $this->t('—'),
        ];

      case 'status':
        $allowed_values = $field->getSetting('allowed_values');
        return [
          '#plain_text' => (string) ($allowed_values[$field->value] ?? $field->value),
        ];

      case 'expected_unit_price':
        return [
          '#plain_text' => number_format((float) $field->value, 2),
        ];

      default:
        return [
          '#plain_text' => (string) $field->value,
        ];
    }
  }

}
